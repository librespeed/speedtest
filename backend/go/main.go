package main

import (
	"encoding/json"
	"fmt"
	"io"
	"io/ioutil"
	"math"
	"math/rand"
	"net"
	"net/http"
	"regexp"
	"strconv"
	"strings"
)

const (
	// settings

	// backend listen port
	backendPort = "8989"

	// download test chunks
	chunks = 4

	// download test chunk size
	chunkSize = 1048576

	// ipinfo.io API key, if applicable
	ipInfoAPIKey = ""

	// distance unit used in frontend, available options: M (miles), K (kilometers), N (nautical miles)
	distanceUnit = "K"

	// enable CORS
	enableCORS = false
)

// ---------------------------------------
// don't change anything below this line
// ---------------------------------------
var (
	// generate random data for download test on start to minimize runtime overhead
	randomData = getRandomData(chunkSize)

	// get server location from ipinfo.io from start to minimize API access
	// serverLat, serverLng = getServerLocation()
	serverLat, serverLng = 0.0, 0.0
)

type IPInfoResponse struct {
	IP           string `json:"ip"`
	City         string `json:"city"`
	Region       string `json:"region"`
	Country      string `json:"country"`
	Location     string `json:"loc"`
	Organization string `json:"org"`
	Timezone     string `json:"timezone"`
	Readme       string `json:"readme"`
}

func main() {
	if chunks > 1024 {
		panic("chunks can't be more than 1024")
	}

	http.HandleFunc("/", pages)
	http.HandleFunc("/empty", empty)
	http.HandleFunc("/garbage", garbage)
	http.HandleFunc("/getIP", getIP)
	http.ListenAndServe(":"+backendPort, nil)
}

func pages(w http.ResponseWriter, r *http.Request) {
	if enableCORS {
		setCORSHeader(&w)
	}

	http.FileServer(http.Dir(".")).ServeHTTP(w, r)
}

func empty(w http.ResponseWriter, r *http.Request) {
	if enableCORS {
		setCORSHeader(&w)
		w.Header().Set("Access-Control-Allow-Headers", "Content-Encoding, Content-Type")
	}

	io.Copy(ioutil.Discard, r.Body)
	r.Body.Close()

	setNoCache(&w)
	w.Header().Set("Connection", "keep-alive")
	w.WriteHeader(http.StatusOK)
}

func garbage(w http.ResponseWriter, r *http.Request) {
	if enableCORS {
		setCORSHeader(&w)
	}

	w.Header().Set("Content-Description", "File Transfer")
	w.Header().Set("Content-Type", "application/octet-stream")
	w.Header().Set("Content-Disposition", "attachment; filename=random.dat")
	w.Header().Set("Content-Transfer-Encoding", "binary")
	setNoCache(&w)

	for i := 0; i < chunks; i++ {
		if _, err := w.Write(randomData); err != nil {
			fmt.Printf("Error writing back to client at chunk number %d: %s\n", i, err)
			break
		}
	}
}

func getIP(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	if enableCORS {
		setCORSHeader(&w)
	}
	setNoCache(&w)

	type result struct {
		ProcessedString string `json:"processedString"`
		RawISPInfo      string `json:"rawIspInfo"`
	}

	var ret result

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
		isp += ", (" + calculateDistance(ispInfo.Location, distanceUnit) + ")"
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

func setNoCache(w *http.ResponseWriter) {
	(*w).Header().Set("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0, s-maxage=0")
	(*w).Header().Add("Cache-Control", "post-check=0, pre-check=0")
	(*w).Header().Set("Pragma", "no-cache")
}

func setCORSHeader(w *http.ResponseWriter) {
	(*w).Header().Set("Access-Control-Allow-Origin", "*")
	(*w).Header().Set("Access-Control-Allow-Methods", "GET, POST")
}

func getIPInfoURL(address string) string {
	ipInfoURL := `https://ipinfo.io/%s/json`
	if address != "" {
		ipInfoURL = fmt.Sprintf(ipInfoURL, address)
	} else {
		ipInfoURL = "https://ipinfo.io/json"
	}

	if ipInfoAPIKey != "" {
		ipInfoURL += "?token=" + ipInfoAPIKey
	}

	return ipInfoURL
}

func getIPInfo(addr string) (string, IPInfoResponse) {
	var ret IPInfoResponse
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
	var ret IPInfoResponse
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
