package mysql

import (
	"database/sql"
	"fmt"

	"backend/database/schema"

	_ "github.com/go-sql-driver/mysql"
	log "github.com/sirupsen/logrus"
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
		log.Fatalf("Cannot open MySQL database: %s", err)
	}
	return &MySQL{db: conn}
}

func (p *MySQL) Insert(data *schema.TelemetryData) error {
	stmt := `INSERT INTO speedtest_users (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, log, uuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);`
	_, err := p.db.Exec(stmt, data.IPAddress, data.ISPInfo, data.Extra, data.UserAgent, data.Language, data.Download, data.Upload, data.Ping, data.Jitter, data.Log, data.UUID)
	return err
}

func (p *MySQL) FetchByUUID(uuid string) (*schema.TelemetryData, error) {
	var record schema.TelemetryData
	row := p.db.QueryRow(`SELECT * FROM speedtest_users WHERE uuid = ?`, uuid)
	if row != nil {
		var id string
		if err := row.Scan(&id, &record.Timestamp, &record.IPAddress, &record.ISPInfo, &record.Extra, &record.UserAgent, &record.Language, &record.Download, &record.Upload, &record.Ping, &record.Jitter, &record.Log, &record.UUID); err != nil {
			return nil, err
		}
	}
	return &record, nil
}

func (p *MySQL) FetchLast100() ([]schema.TelemetryData, error) {
	var records []schema.TelemetryData
	rows, err := p.db.Query(`SELECT * FROM speedtest_users ORDER BY "timestamp" DESC LIMIT 100;`)
	if err != nil {
		return nil, err
	}
	if rows != nil {
		var id string

		for rows.Next() {
			var record schema.TelemetryData
			if err := rows.Scan(&id, &record.Timestamp, &record.IPAddress, &record.ISPInfo, &record.Extra, &record.UserAgent, &record.Language, &record.Download, &record.Upload, &record.Ping, &record.Jitter, &record.Log, &record.UUID); err != nil {
				return nil, err
			}
			records = append(records, record)
		}
	}
	return records, nil
}
