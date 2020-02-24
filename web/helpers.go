package web

import (
	"crypto/rand"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"math"
	"net/http"
	"strconv"
	"strings"

	"backend/config"
	"backend/results"

	log "github.com/sirupsen/logrus"
)

var (
	// get server location from ipinfo.io from start to minimize API access
	serverLat, serverLng = getServerLocation()
	// for testing
	// serverLat, serverLng = 22.7702, 112.9578
	// serverLat, serverLng = 23.018, 113.7487
)

func getRandomData(length int) []byte {
	data := make([]byte, length)
	if _, err := rand.Read(data); err != nil {
		log.Fatalf("Failed to generate random data: %s", err)
	}
	return data
}

func getIPInfoURL(address string) string {
	apiKey := config.LoadedConfig().IPInfoAPIKey

	ipInfoURL := `https://ipinfo.io/%s/json`
	if address != "" {
		ipInfoURL = fmt.Sprintf(ipInfoURL, address)
	} else {
		ipInfoURL = "https://ipinfo.io/json"
	}

	if apiKey != "" {
		ipInfoURL += "?token=" + apiKey
	}

	return ipInfoURL
}

func getIPInfo(addr string) (string, results.IPInfoResponse) {
	var ret results.IPInfoResponse
	resp, err := http.DefaultClient.Get(getIPInfoURL(addr))
	if err != nil {
		log.Errorf("Error getting response from ipinfo.io: %s", err)
		return "", ret
	}

	raw, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Errorf("Error reading response from ipinfo.io: %s", err)
		return "", ret
	}
	defer resp.Body.Close()

	if err := json.Unmarshal(raw, &ret); err != nil {
		log.Errorf("Error parsing response from ipinfo.io: %s", err)
	}

	return string(raw), ret
}

func getServerLocation() (float64, float64) {
	var ret results.IPInfoResponse
	resp, err := http.DefaultClient.Get(getIPInfoURL(""))
	if err != nil {
		log.Errorf("Error getting repsonse from ipinfo.io: %s", err)
		return 0, 0
	}
	raw, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Errorf("Error reading response from ipinfo.io: %s", err)
		return 0, 0
	}
	defer resp.Body.Close()

	if err := json.Unmarshal(raw, &ret); err != nil {
		log.Errorf("Error parsing response from ipinfo.io: %s", err)
		return 0, 0
	}

	var lat, lng float64
	if ret.Location != "" {
		lat, lng = parseLocationString(ret.Location)
	}

	log.Infof("Fetched server coordinates: %.6f, %.6f", lat, lng)

	return lat, lng
}

func parseLocationString(location string) (float64, float64) {
	parts := strings.Split(location, ",")
	if len(parts) != 2 {
		log.Errorf("Unknown location format: %s", location)
		return 0, 0
	}

	lat, err := strconv.ParseFloat(parts[0], 64)
	if err != nil {
		log.Errorf("Error parsing latitude: %s", parts[0])
		return 0, 0
	}

	lng, err := strconv.ParseFloat(parts[1], 64)
	if err != nil {
		log.Errorf("Error parsing longitude: %s", parts[0])
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

	return fmt.Sprintf("%d%s", round(dist), unitString)
}

func round(v float64) int {
	r := int(math.Round(v))
	return 10 * ((r + 9) / 10)
}
