package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"io/ioutil"
	"math"
	"math/rand"
	"net"
	"net/http"
	"os"
	"regexp"
	"strconv"
	"strings"

	"backend/database"
	"backend/results"
)

const (
	// chunk size is 1 mib
	chunkSize = 1048576
)

var (
	// generate random data for download test on start to minimize runtime overhead
	randomData = getRandomData(chunkSize)

	// get server location from ipinfo.io from start to minimize API access
	serverLat, serverLng = getServerLocation()
	// for testing
	// serverLat, serverLng = 22.7702, 112.9578

	// load settings.txt
	settings = loadSettings()

	chunks     int
	enableCORS = false
)

func main() {
	fmt.Println("Loading settings.txt")
	if settings["chunks"] == "" {
		chunks = 4
	} else {
		v, err := strconv.ParseInt(settings["chunks"], 10, 64)
		if err != nil {
			fmt.Printf("Invalid chunks setting: %s", settings["chunks"])
		}
		chunks = int(v)
	}

	if b, err := strconv.ParseBool(settings["enable_cors"]); err != nil {
		fmt.Printf("Error parsing enable_cors value: %s\n", err)
		fmt.Println("WARNING: CORS will be disabled")
	} else {
		enableCORS = b
	}

	if b, err := strconv.ParseBool(settings["redact_ip_addresses"]); err != nil {
		fmt.Printf("Error parsing redact_ip_addresses value: %s\n", err)
		fmt.Println("WARNING: Redact IP addresses will be disabled")
	} else {
		results.RedactIPAddress = b
	}

	database.SetDBInfo(settings)

	if chunks > 1024 {
		panic("chunks can't be more than 1024")
	}

	fmt.Printf("Starting backend server on port %s\n", settings["listen_port"])
	// TODO: security
	http.Handle("/", setCORS(http.HandlerFunc(pages)))
	http.Handle("/empty", disableCache(setCORS(http.HandlerFunc(empty))))
	http.Handle("/garbage", disableCache(setCORS(http.HandlerFunc(garbage))))
	http.Handle("/getIP", disableCache(setCORS(http.HandlerFunc(getIP))))
	http.Handle("/results/", disableCache(setCORS(http.HandlerFunc(results.DrawPNG))))
	http.Handle("/results/telemetry", disableCache(setCORS(http.HandlerFunc(results.Record))))
	http.Handle("/stats", disableCache(setCORS(http.HandlerFunc(results.Stats))))
	http.ListenAndServe(":"+settings["listen_port"], nil)
}

func loadSettings() map[string]string {
	b, err := ioutil.ReadFile("settings.txt")
	if err != nil {
		fmt.Printf("Error reading settings.txt: %s\n", err)
		os.Exit(1)
	}

	ret := make(map[string]string)

	r := bufio.NewReader(bytes.NewReader(b))
	for {
		line, _, err := r.ReadLine()
		if err != nil {
			if err == io.EOF {
				break
			}
			fmt.Printf("Error reading settings.txt: %s\n", err)
			os.Exit(2)
		}

		if !bytes.HasPrefix(line, []byte("#")) {
			parts := strings.Split(string(bytes.TrimSpace(line)), "=")
			switch len(parts) {
			case 0:
				continue
			case 1:
				ret[parts[0]] = ""
			case 2:
				ret[parts[0]] = parts[1]
			case 3:
				fmt.Printf("%+v", parts)
			}
		}
	}

	return ret
}

func pages(w http.ResponseWriter, r *http.Request) {
	// forbid access to settings.txt
	if strings.Contains(r.RequestURI, "settings.txt") {
		http.NotFound(w, r)
		return
	}

	http.FileServer(http.Dir("assets")).ServeHTTP(w, r)
}

func empty(w http.ResponseWriter, r *http.Request) {
	if enableCORS {
		w.Header().Set("Access-Control-Allow-Headers", "Content-Encoding, Content-Type")
	}

	io.Copy(ioutil.Discard, r.Body)
	r.Body.Close()

	w.Header().Set("Connection", "keep-alive")
	w.WriteHeader(http.StatusOK)
}

func garbage(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Description", "File Transfer")
	w.Header().Set("Content-Type", "application/octet-stream")
	w.Header().Set("Content-Disposition", "attachment; filename=random.dat")
	w.Header().Set("Content-Transfer-Encoding", "binary")

	for i := 0; i < chunks; i++ {
		if _, err := w.Write(randomData); err != nil {
			fmt.Printf("Error writing back to client at chunk number %d: %s\n", i, err)
			break
		}
	}
}

func getIP(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var ret results.Result

	clientIP, _, _ := net.SplitHostPort(r.RemoteAddr)
	strings.ReplaceAll(clientIP, "::ffff:", "")

	isSpecialIP := true
	switch {
	case clientIP == "::1":
		ret.ProcessedString = clientIP + " - localhost IPv6 access"
	case strings.HasPrefix(clientIP, "fe80:"):
		ret.ProcessedString = clientIP + " - link-local IPv6 access"
	case strings.HasPrefix(clientIP, "127."):
		ret.ProcessedString = clientIP + " - localhost IPv4 access"
	case strings.HasPrefix(clientIP, "10."):
		ret.ProcessedString = clientIP + " - private IPv4 access"
	case regexp.MustCompile(`^172\.(1[6-9]|2\d|3[01])\.`).MatchString(clientIP):
		ret.ProcessedString = clientIP + " - private IPv4 access"
	case strings.HasPrefix(clientIP, "192.168"):
		ret.ProcessedString = clientIP + " - private IPv4 access"
	case strings.HasPrefix(clientIP, "169.254"):
		ret.ProcessedString = clientIP + " - link-local IPv4 access"
	case regexp.MustCompile(`^100\.([6-9][0-9]|1[0-2][0-7])\.`).MatchString(clientIP):
		ret.ProcessedString = clientIP + " - CGNAT IPv4 access"
	default:
		isSpecialIP = false
	}

	if isSpecialIP {
		b, _ := json.Marshal(&ret)
		if _, err := w.Write(b); err != nil {
			fmt.Printf("Error writing to client: %s\n", err)
		}
		return
	}

	rawIspInfo, ispInfo := getIPInfo(clientIP)
	ret.RawISPInfo = rawIspInfo

	removeRegexp := regexp.MustCompile(`AS\d+\s`)
	isp := removeRegexp.ReplaceAllString(ispInfo.Organization, "")

	if isp == "" {
		isp = "Unknown ISP"
	}

	if ispInfo.Country != "" {
		isp += ", " + ispInfo.Country
	}

	if ispInfo.Location != "" {
		isp += ", (" + calculateDistance(ispInfo.Location, settings["distance_unit"]) + ")"
	}

	ret.ProcessedString = clientIP + " - " + isp

	b, _ := json.Marshal(&ret)
	if _, err := w.Write(b); err != nil {
		fmt.Printf("Error writing response: %s\n", err)
	}
}

func getRandomData(length int) []byte {
	data := make([]byte, length)
	_, err := rand.Read(data)
	if err != nil {
		fmt.Printf("Error generating random data: %s\n", err)
	}
	return data
}

func disableCache(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, s-maxage=0")
		w.Header().Add("Cache-Control", "post-check=0, pre-check=0")
		w.Header().Set("Pragma", "no-cache")
		next.ServeHTTP(w, r)
	})
}

func setCORS(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if enableCORS {
			w.Header().Set("Access-Control-Allow-Origin", "*")
			w.Header().Set("Access-Control-Allow-Methods", "GET, POST")
		}
		next.ServeHTTP(w, r)
	})
}

func getIPInfoURL(address string) string {
	ipInfoURL := `https://ipinfo.io/%s/json`
	if address != "" {
		ipInfoURL = fmt.Sprintf(ipInfoURL, address)
	} else {
		ipInfoURL = "https://ipinfo.io/json"
	}

	if settings["ipinfo_api_key"] != "" {
		ipInfoURL += "?token=" + settings["ipinfo_api_key"]
	}

	return ipInfoURL
}

func getIPInfo(addr string) (string, results.IPInfoResponse) {
	var ret results.IPInfoResponse
	resp, err := http.DefaultClient.Get(getIPInfoURL(addr))
	if err != nil {
		fmt.Printf("Error getting response from ipinfo.io: %s\n", err)
		return "", ret
	}

	raw, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		fmt.Printf("Error reading response from ipinfo.io: %s\n", err)
		return "", ret
	}
	defer resp.Body.Close()

	if err := json.Unmarshal(raw, &ret); err != nil {
		fmt.Printf("Error parsing response from ipinfo.io: %s\n", err)
	}

	return string(raw), ret
}

func getServerLocation() (float64, float64) {
	var ret results.IPInfoResponse
	resp, err := http.DefaultClient.Get(getIPInfoURL(""))
	if err != nil {
		fmt.Printf("Error getting repsonse from ipinfo.io: %s\n", err)
		return 0, 0
	}
	raw, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		fmt.Printf("Error reading response from ipinfo.io: %s\n", err)
		return 0, 0
	}
	defer resp.Body.Close()

	if err := json.Unmarshal(raw, &ret); err != nil {
		fmt.Printf("Error parsing response from ipinfo.io: %s\n", err)
		return 0, 0
	}

	var lat, lng float64
	if ret.Location != "" {
		lat, lng = parseLocationString(ret.Location)
	}

	fmt.Printf("Fetched server coordinates: %.6f, %.6f\n", lat, lng)

	return lat, lng
}

func parseLocationString(location string) (float64, float64) {
	parts := strings.Split(location, ",")
	if len(parts) != 2 {
		fmt.Printf("Unknown location format: %s\n", location)
		return 0, 0
	}

	lat, err := strconv.ParseFloat(parts[0], 64)
	if err != nil {
		fmt.Printf("Error parsing latitude: %s\n", parts[0])
		return 0, 0
	}

	lng, err := strconv.ParseFloat(parts[1], 64)
	if err != nil {
		fmt.Printf("Error parsing longitude: %s\n", parts[0])
		return 0, 0
	}

	return lat, lng
}

func calculateDistance(clientLocation string, unit string) string {
	clientLat, clientLng := parseLocationString(clientLocation)

	radlat1 := float64(math.Pi * serverLat / 180)
	radlat2 := float64(math.Pi * clientLat / 180)

	theta := float64(serverLng - clientLng)
	radtheta := float64(math.Pi * theta / 180)

	dist := math.Sin(radlat1)*math.Sin(radlat2) + math.Cos(radlat1)*math.Cos(radlat2)*math.Cos(radtheta)

	if dist > 1 {
		dist = 1
	}

	dist = math.Acos(dist)
	dist = dist * 180 / math.Pi
	dist = dist * 60 * 1.1515

	unitString := " mi"
	switch unit {
	case "K":
		dist = dist * 1.609344
		unitString = " km"
	case "N":
		dist = dist * 0.8684
		unitString = " NM"
	}

	return strconv.FormatFloat(dist, 'f', 2, 64) + unitString
}
