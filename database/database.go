package database

import (
	"database/sql"

	"backend/config"
	"backend/database/mysql"
	"backend/database/postgresql"
	"backend/database/schema"
)

var (
	DB DataAccess
)

type DataAccess interface {
	Insert(*schema.TelemetryData) (sql.Result, error)
	FetchByUUID(string) (*schema.TelemetryData, error)
	FetchLast100() ([]schema.TelemetryData, error)
}

func SetDBInfo(conf *config.Config) {
	switch conf.DatabaseType {
	case "postgresql":
		DB = postgresql.Open(conf.DatabaseHostname, conf.DatabaseUsername, conf.DatabasePassword, conf.DatabaseName)
	case "mysql":
		DB = mysql.Open(conf.DatabaseHostname, conf.DatabaseUsername, conf.DatabasePassword, conf.DatabaseName)
	}
}
