package main

import (
	"backend/config"
	"backend/database"
	"backend/web"

	log "github.com/sirupsen/logrus"
)

func main() {
	conf := config.Load()

	if conf.DownloadChunks > 1024 {
		log.Fatal("chunks can't be more than 1024")
	}

	database.SetDBInfo(&conf)
	log.Fatal(web.ListenAndServe(&conf))
}
