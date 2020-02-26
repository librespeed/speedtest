package bolt

import (
	"encoding/json"
	"errors"
	"time"

	"go.etcd.io/bbolt"

	"backend/database/schema"

	_ "github.com/go-sql-driver/mysql"
	log "github.com/sirupsen/logrus"
)

const (
	bucketName = `speedtest`
)

type Bolt struct {
	db *bbolt.DB
}

func Open(databaseFile string) *Bolt {
	db, err := bbolt.Open(databaseFile, 0666, nil)
	if err != nil {
		log.Fatalf("Cannot open BoltDB database file: %s", err)
	}
	return &Bolt{db: db}
}

func (p *Bolt) Insert(data *schema.TelemetryData) error {
	return p.db.Update(func(tx *bbolt.Tx) error {
		data.Timestamp = time.Now()
		b, _ := json.Marshal(data)
		bucket, err := tx.CreateBucketIfNotExists([]byte(bucketName))
		if err != nil {
			return err
		}
		return bucket.Put([]byte(data.UUID), b)
	})
}

func (p *Bolt) FetchByUUID(uuid string) (*schema.TelemetryData, error) {
	var record schema.TelemetryData
	err := p.db.View(func(tx *bbolt.Tx) error {
		bucket := tx.Bucket([]byte(bucketName))
		if bucket == nil {
			return errors.New("data bucket doesn't exist yet")
		}
		b := bucket.Get([]byte(uuid))
		return json.Unmarshal(b, &record)
	})
	return &record, err
}

func (p *Bolt) FetchLast100() ([]schema.TelemetryData, error) {
	var records []schema.TelemetryData
	err := p.db.View(func(tx *bbolt.Tx) error {
		var record schema.TelemetryData
		bucket := tx.Bucket([]byte(bucketName))
		if bucket == nil {
			return errors.New("data bucket doesn't exist yet")
		}

		cursor := bucket.Cursor()
		_, b := cursor.Last()

		for len(records) < 100 {
			if err := json.Unmarshal(b, &record); err != nil {
				return err
			}
			records = append(records, record)

			_, b = cursor.Prev()
			if b == nil {
				break
			}
		}

		return nil
	})
	return records, err
}
