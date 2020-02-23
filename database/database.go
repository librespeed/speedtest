package database

import (
	"database/sql"

	"backend/database/mysql"
	"backend/database/postgresql"
	"backend/database/schema"
)

var (
	Type     string
	Hostname string
	Username string
	Password string
	Database string

	DB DataAccess
)

type DataAccess interface {
	Insert(*schema.TelemetryData) (sql.Result, error)
	FetchByUUID(string) (*schema.TelemetryData, error)
}

func SetDBInfo(settings map[string]string) {
	for k, v := range settings {
		switch k {
		case "database_type":
			Type = v
		case "database_hostname":
			Hostname = v
		case "database_name":
			Database = v
		case "database_username":
			Username = v
		case "database_password":
			Password = v
		}
	}

	switch Type {
	case "postgresql":
		DB = postgresql.Open(Hostname, Username, Password, Database)
	case "mysql":
		DB = mysql.Open(Hostname, Username, Password, Database)
	}
}
