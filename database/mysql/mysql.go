package mysql

import (
	"database/sql"
	"fmt"
	"os"

	_ "github.com/go-sql-driver/mysql"

	"backend/database/schema"
)

const (
	connectionStringTemplate = `%s:%s@%s/%s`
)

type MySQL struct {
	db *sql.DB
}

func Open(hostname, username, password, database string) *MySQL {
	connStr := fmt.Sprintf(connectionStringTemplate, username, password, hostname, database)
	conn, err := sql.Open("mysql", connStr)
	if err != nil {
		fmt.Printf("Cannot open MySQL database: %s\n", err)
		os.Exit(1)
	}
	return &MySQL{db: conn}
}

func (p *MySQL) Insert(data *schema.TelemetryData) (sql.Result, error) {
	stmt := `INSERT INTO speedtest_users (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, log, uuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);`
	return p.db.Exec(stmt, data.IPAddress, data.ISPInfo, data.Extra, data.UserAgent, data.Language, data.Download, data.Upload, data.Ping, data.Jitter, data.Log, data.UUID)
}

func (p *MySQL) FetchByUUID(uuid string) (*schema.TelemetryData, error) {
	var record schema.TelemetryData
	row := p.db.QueryRow(`SELECT * FROM speedtest_users WHERE uuid = ?`, uuid)
	if row != nil {
		if err := row.Scan(&record); err != nil {
			return nil, err
		}
	}
	return &record, nil
}
