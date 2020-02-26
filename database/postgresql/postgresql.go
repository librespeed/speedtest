package postgresql

import (
	"database/sql"
	"fmt"

	"backend/database/schema"

	_ "github.com/lib/pq"
	log "github.com/sirupsen/logrus"
)

const (
	connectionStringTemplate = `postgres://%s:%s@%s/%s?sslmode=disable`
)

type PostgreSQL struct {
	db *sql.DB
}

func Open(hostname, username, password, database string) *PostgreSQL {
	connStr := fmt.Sprintf(connectionStringTemplate, username, password, hostname, database)
	conn, err := sql.Open("postgres", connStr)
	if err != nil {
		log.Fatalf("Cannot open PostgreSQL database: %s", err)
	}
	return &PostgreSQL{db: conn}
}

func (p *PostgreSQL) Insert(data *schema.TelemetryData) error {
	stmt := `INSERT INTO speedtest_users (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, log, uuid) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11) RETURNING id;`
	_, err := p.db.Exec(stmt, data.IPAddress, data.ISPInfo, data.Extra, data.UserAgent, data.Language, data.Download, data.Upload, data.Ping, data.Jitter, data.Log, data.UUID)
	return err
}

func (p *PostgreSQL) FetchByUUID(uuid string) (*schema.TelemetryData, error) {
	var record schema.TelemetryData
	row := p.db.QueryRow(`SELECT * FROM speedtest_users WHERE uuid = $1`, uuid)
	if row != nil {
		var id string
		if err := row.Scan(&id, &record.Timestamp, &record.IPAddress, &record.ISPInfo, &record.Extra, &record.UserAgent, &record.Language, &record.Download, &record.Upload, &record.Ping, &record.Jitter, &record.Log, &record.UUID); err != nil {
			return nil, err
		}
	}
	return &record, nil
}

func (p *PostgreSQL) FetchLast100() ([]schema.TelemetryData, error) {
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
