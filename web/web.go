package web

import (
	"encoding/json"
	"io"
	"io/ioutil"
	"net"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"github.com/go-chi/chi"
	"github.com/go-chi/chi/middleware"
	"github.com/go-chi/cors"
	"github.com/go-chi/render"
	log "github.com/sirupsen/logrus"

	"backend/config"
	"backend/results"
)

const (
	// chunk size is 1 mib
	chunkSize = 1048576
)

var (
	// generate random data for download test on start to minimize runtime overhead
	randomData = getRandomData(chunkSize)
)

func ListenAndServe(conf *config.Config) error {
	r := chi.NewMux()
	r.Use(middleware.RealIP)

	cs := cors.New(cors.Options{
		AllowedOrigins: []string{"*"},
		AllowedMethods: []string{"GET", "POST", "OPTIONS"},
		AllowedHeaders: []string{"*"},
	})

	r.Use(cs.Handler)
	r.Use(middleware.NoCache)
	r.Use(middleware.Logger)

	log.Infof("Starting backend server on port %s", conf.Port)
	r.Get("/*", pages)
	r.HandleFunc("/empty", empty)
	r.Get("/garbage", garbage)
	r.Get("/getIP", getIP)
	r.Get("/results/", results.DrawPNG)
	r.Post("/results/telemetry", results.Record)
	r.HandleFunc("/stats", results.Stats)
	return http.ListenAndServe(net.JoinHostPort(conf.BindAddress, conf.Port), r)
}

func pages(w http.ResponseWriter, r *http.Request) {
	if r.RequestURI == "/" {
		r.RequestURI = "/index.html"
	}

	uri := strings.Split(r.RequestURI, "?")[0]
	if strings.HasSuffix(uri, ".html") || strings.HasSuffix(uri, ".js") {
		http.FileServer(http.Dir("assets")).ServeHTTP(w, r)
	} else {
		w.WriteHeader(http.StatusForbidden)
	}
}

func empty(w http.ResponseWriter, r *http.Request) {
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

	conf := config.LoadedConfig()
	ckSize := r.FormValue("ckSize")

	chunks := conf.DownloadChunks
	if ckSize != "" {
		i, err := strconv.ParseInt(ckSize, 10, 64)
		if err == nil && i > 0 && i < 1024 {
			chunks = int(i)
		} else {
			log.Errorf("Invalid chunk size: %s", ckSize)
			log.Warn("Will use default value %d", chunks)
		}
	}

	for i := 0; i < chunks; i++ {
		if _, err := w.Write(randomData); err != nil {
			log.Errorf("Error writing back to client at chunk number %d: %s", i, err)
			break
		}
	}
}

func getIP(w http.ResponseWriter, r *http.Request) {
	var ret results.Result

	clientIP := r.RemoteAddr
	if strings.Contains(clientIP, ":") {
		ip, _, _ := net.SplitHostPort(r.RemoteAddr)
		clientIP = ip
	}

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
			log.Errorf("Error writing to client: %s", err)
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
		isp += " (" + calculateDistance(ispInfo.Location, config.LoadedConfig().DistanceUnit) + ")"
	}

	ret.ProcessedString = clientIP + " - " + isp

	render.JSON(w, r, ret)
}
