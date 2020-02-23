package results

import (
	"encoding/json"
	"fmt"
	"image"
	"image/color"
	"image/draw"
	"image/png"
	"io/ioutil"
	"math/rand"
	"net"
	"net/http"
	"regexp"
	"strings"
	"time"

	"github.com/golang/freetype"
	"github.com/golang/freetype/truetype"
	"github.com/oklog/ulid/v2"
	"golang.org/x/image/font"

	"backend/database"
	"backend/database/schema"
)

const (
	watermark = "LibreSpeed"
)

var (
	RedactIPAddress bool

	ipv4Regex     = regexp.MustCompile(`(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)`)
	ipv6Regex     = regexp.MustCompile(`(([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4})?:)?((25[0-5]|(2[0-4]|1?[0-9])?[0-9])\.){3}(25[0-5]|(2[0-4]|1?[0-9])?[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1?[0-9])?[0-9])\.){3}(25[0-5]|(2[0-4]|1?[0-9])?[0-9]))`)
	hostnameRegex = regexp.MustCompile(`"hostname":"([^\\\\"]|\\\\")*"`)

	colorLabel     = color.RGBA{40, 40, 40, 255}
	colorDownload  = color.RGBA{96, 96, 170, 255}
	colorUpload    = color.RGBA{96, 96, 96, 255}
	colorPing      = color.RGBA{170, 96, 96, 255}
	colorJitter    = color.RGBA{170, 96, 96, 255}
	colorMeasure   = color.RGBA{40, 40, 40, 255}
	colorISP       = color.RGBA{40, 40, 40, 255}
	colorWatermark = color.RGBA{160, 160, 160, 255}
	colorSeparator = color.RGBA{192, 192, 192, 255}
)

type Result struct {
	ProcessedString string `json:"processedString"`
	RawISPInfo      string `json:"rawIspInfo"`
}

type IPInfoResponse struct {
	IP           string `json:"ip"`
	Hostname     string `json:"hostname"`
	City         string `json:"city"`
	Region       string `json:"region"`
	Country      string `json:"country"`
	Location     string `json:"loc"`
	Organization string `json:"org"`
	Postal       string `json:"postal"`
	Timezone     string `json:"timezone"`
	Readme       string `json:"readme"`
}

func (r *Result) GetISPInfo() (IPInfoResponse, error) {
	var ret IPInfoResponse
	err := json.Unmarshal([]byte(r.RawISPInfo), &ret)
	return ret, err
}

func Record(w http.ResponseWriter, r *http.Request) {
	ipAddr, _, _ := net.SplitHostPort(r.RemoteAddr)
	userAgent := r.UserAgent()
	language := r.Header.Get("Accept-Language")

	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}

	ispInfo := r.FormValue("ispinfo")
	download := r.FormValue("dl")
	upload := r.FormValue("ul")
	ping := r.FormValue("ping")
	jitter := r.FormValue("jitter")
	logs := r.FormValue("log")
	extra := r.FormValue("extra")

	if RedactIPAddress {
		ipAddr = "0.0.0.0"
		ipv4Regex.ReplaceAllString(ispInfo, "0.0.0.0")
		ipv4Regex.ReplaceAllString(logs, "0.0.0.0")
		ipv6Regex.ReplaceAllString(ispInfo, "0.0.0.0")
		ipv6Regex.ReplaceAllString(logs, "0.0.0.0")
		hostnameRegex.ReplaceAllString(ispInfo, `"hostname":"REDACTED"`)
		hostnameRegex.ReplaceAllString(logs, `"hostname":"REDACTED"`)
	}

	var record schema.TelemetryData
	record.IPAddress = ipAddr
	record.ISPInfo = ispInfo
	record.Extra = extra
	record.UserAgent = userAgent
	record.Language = language
	record.Download = download
	record.Upload = upload
	record.Ping = ping
	record.Jitter = jitter
	record.Log = logs

	t := time.Now()
	entropy := ulid.Monotonic(rand.New(rand.NewSource(t.UnixNano())), 0)
	uuid := ulid.MustNew(ulid.Timestamp(t), entropy)
	record.UUID = uuid.String()

	_, err := database.DB.Insert(&record)
	if err != nil {
		fmt.Printf("Error inserting into database: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	}

	if _, err := w.Write([]byte("id " + uuid.String())); err != nil {
		fmt.Printf("Error writing ID to telemetry request: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
	}
}

func DrawPNG(w http.ResponseWriter, r *http.Request) {
	uuid := r.FormValue("id")
	record, err := database.DB.FetchByUUID(uuid)
	if err != nil {
		fmt.Printf("Error querying database: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	}

	var result Result
	if err := json.Unmarshal([]byte(record.ISPInfo), &result); err != nil {
		fmt.Printf("Error parsing ISP info: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	}

	ispInfo, err := result.GetISPInfo()
	if err != nil {
		fmt.Printf("Error parsing ISP info: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	}

	canvasWidth, canvasHeight := 500, 286
	canvas := image.NewRGBA(image.Rectangle{
		Min: image.Point{},
		Max: image.Point{
			X: canvasWidth,
			Y: canvasHeight,
		},
	})

	draw.Draw(canvas, canvas.Bounds(), image.NewUniform(color.White), image.Point{}, draw.Src)

	// changed to use Clear Sans instead of OpenSans, due to issue:
	// https://github.com/golang/freetype/issues/8
	var fontLight, fontBold *truetype.Font
	if b, err := ioutil.ReadFile("assets/NotoSansDisplay-Light.ttf"); err != nil {
		fmt.Printf("Error opening NotoSansDisplay-Light font: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	} else {
		font, err := freetype.ParseFont(b)
		if err != nil {
			fmt.Printf("Error parsing NotoSansDisplay-Light font: %s\n", err)
			w.WriteHeader(http.StatusInternalServerError)
			return
		}
		fontLight = font
	}

	if b, err := ioutil.ReadFile("assets/NotoSansDisplay-Medium.ttf"); err != nil {
		fmt.Printf("Error opening NotoSansDisplay-Medium font: %s\n", err)
		w.WriteHeader(http.StatusInternalServerError)
		return
	} else {
		font, err := freetype.ParseFont(b)
		if err != nil {
			fmt.Printf("Error parsing NotoSansDisplay-Medium font: %s\n", err)
			w.WriteHeader(http.StatusInternalServerError)
			return
		}
		fontBold = font
	}

	labelFace := truetype.NewFace(fontBold, &truetype.Options{
		Size:    26,
		DPI:     72,
		Hinting: font.HintingFull,
	})

	valueFace := truetype.NewFace(fontLight, &truetype.Options{
		Size:    36,
		DPI:     72,
		Hinting: font.HintingFull,
	})

	smallLabelFace := truetype.NewFace(fontBold, &truetype.Options{
		Size:    20,
		DPI:     72,
		Hinting: font.HintingFull,
	})

	orgFace := truetype.NewFace(fontBold, &truetype.Options{
		Size:    16,
		DPI:     72,
		Hinting: font.HintingFull,
	})

	watermarkFace := truetype.NewFace(fontLight, &truetype.Options{
		Size:    14,
		DPI:     72,
		Hinting: font.HintingFull,
	})

	drawer := &font.Drawer{
		Dst:  canvas,
		Face: labelFace,
	}

	drawer.Src = image.NewUniform(colorLabel)

	// labels
	p := drawer.MeasureString("Ping")
	x := canvasWidth/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 35)
	drawer.DrawString("Ping")

	p = drawer.MeasureString("Jitter")
	x = canvasWidth*3/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 35)
	drawer.DrawString("Jitter")

	p = drawer.MeasureString("Download")
	x = canvasWidth/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 145)
	drawer.DrawString("Download")

	p = drawer.MeasureString("Upload")
	x = canvasWidth*3/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 145)
	drawer.DrawString("Upload")

	drawer.Face = smallLabelFace
	drawer.Src = image.NewUniform(colorMeasure)
	p = drawer.MeasureString("Mbps")
	x = canvasWidth/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 220)
	drawer.DrawString("Mbps")

	p = drawer.MeasureString("Mbps")
	x = canvasWidth*3/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 220)
	drawer.DrawString("Mbps")

	msLength := drawer.MeasureString(" ms")

	// ping value
	drawer.Face = valueFace
	pingValue := strings.Split(record.Ping, ".")[0]
	p = drawer.MeasureString(pingValue)

	x = canvasWidth/4 - (p.Round()+msLength.Round())/2
	drawer.Dot = freetype.Pt(x, 80)
	drawer.Src = image.NewUniform(colorPing)
	drawer.DrawString(pingValue)
	x = x + p.Round()
	drawer.Dot = freetype.Pt(x, 80)
	drawer.Src = image.NewUniform(colorMeasure)
	drawer.Face = smallLabelFace
	drawer.DrawString(" ms")

	// jitter value
	drawer.Face = valueFace
	jitterValue := strings.Split(record.Jitter, ".")[0]
	p = drawer.MeasureString(jitterValue)
	x = canvasWidth*3/4 - (p.Round()+msLength.Round())/2
	drawer.Dot = freetype.Pt(x, 80)
	drawer.Src = image.NewUniform(colorJitter)
	drawer.DrawString(jitterValue)
	drawer.Face = smallLabelFace
	x = x + p.Round()
	drawer.Dot = freetype.Pt(x, 80)
	drawer.Src = image.NewUniform(colorMeasure)
	drawer.DrawString(" ms")

	// download value
	drawer.Face = valueFace
	p = drawer.MeasureString(record.Download)
	x = canvasWidth/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 190)
	drawer.Src = image.NewUniform(colorDownload)
	drawer.DrawString(record.Download)

	// upload value
	p = drawer.MeasureString(record.Upload)
	x = canvasWidth*3/4 - p.Round()/2
	drawer.Dot = freetype.Pt(x, 190)
	drawer.Src = image.NewUniform(colorUpload)
	drawer.DrawString(record.Upload)

	// ISP info
	drawer.Face = orgFace
	drawer.Src = image.NewUniform(colorISP)
	drawer.Dot = freetype.Pt(6, 260)
	removeRegexp := regexp.MustCompile(`AS\d+\s`)
	org := removeRegexp.ReplaceAllString(ispInfo.Organization, "") + ", " + ispInfo.Country
	drawer.DrawString(org)

	// separator
	for i := canvas.Bounds().Min.X; i < canvas.Bounds().Max.X; i++ {
		canvas.Set(i, 265, image.NewUniform(colorSeparator))
	}

	// watermark
	drawer.Face = watermarkFace
	drawer.Src = image.NewUniform(colorWatermark)
	p = drawer.MeasureString(watermark)
	x = canvasWidth - p.Round() - 5
	drawer.Dot = freetype.Pt(x, 280)
	drawer.DrawString(watermark)

	w.Header().Set("Content-Disposition", "inline; filename="+uuid+".png")
	w.Header().Set("Content-Type", "image/png")
	png.Encode(w, canvas)
}
