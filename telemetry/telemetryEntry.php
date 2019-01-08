<?php
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'telemetry_settings.php');

class TelemetryEntry {

    public $id;
    public $datetime;

    public $ip;
    public $ispinfo;
    public $useragent;
    public $locale;
    public $downloadspeed;
    public $uploadspeed;
    public $ping;
    public $jitter;
    public $log;
    public $extra;

    public function __construct($data = null)
    {
        if (is_array($data)) {
            if (isset($data['id'])) $this->id = $data['id'];
            if (isset($data['timestamp'])) $this->datetime = $data['timestamp'];

            $this->ip = $data['ip'];
            $this->ispinfo = $data['ispinfo'];
            $this->useragent = $data['ua'];
            $this->locale = $data['lang'];
            $this->downloadspeed = $data['dl'];
            $this->uploadspeed = $data['ul'];
            $this->ping = $data['ping'];
            $this->jitter = $data['jitter'];
            $this->log = $data['log'];
            $this->extra = $data['extra'];
        }
    }

    public function __set($name, $value) {
        if ($name == "timestamp") {
            $this->datetime = $value;
        } elseif ($name == "ua") {
            $this->useragent = $value;
        } else if ($name == "ul") {
            $this->uploadspeed = $value;
        } else if ($name == "dl") {
            $this->downloadspeed = $value;
        } else if ($name == "lang") {
            $this->locale = $value;
        }
    }
}

interface ITelemetryRepository
{
    public function add(TelemetryEntry $entry);
    public function findAll();
    public function find($id);
    public function update(TelemetryEntry $entry);
}


class TelemetryRepository implements ITelemetryRepository
{
    private $connection;

    public function __construct(PDO $connection = null) {
        $this->connection = $connection;

        // if ($this->connection === null) {
        //     $this->connection = new PDO(
        //             'mysql:host=localhost;dbname=pdo_example',
        //             'root',
        //             'root'
        //         );
        //     $this->connection->setAttribute(
        //         PDO::ATTR_ERRMODE,
        //         PDO::ERRMODE_EXCEPTION
        //     );
        // }
    }

    public function add(TelemetryEntry $entry) {
        // If the ID is set, we're updating an existing record
        if (isset($entry->id)) {
            return $this->update($entry);
        }

        $stmt = $this->connection->prepare('
            INSERT INTO speedtest_users
                (ip, ispinfo, extra, ua, lang, dl, ul, ping, jitter, log)
            VALUES
                (:ip, :ispinfo, :extra, :ua, :lang, :dl, :ul, :ping, :jitter, :log)
        ');
        $stmt->bindParam(':ip', $entry->ip);
        $stmt->bindParam(':ispinfo', $entry->ispinfo);
        $stmt->bindParam(':extra', $entry->extra);
        $stmt->bindParam(':ua', $entry->useragent);
        $stmt->bindParam(':lang', $entry->locale);
        $stmt->bindParam(':dl', $entry->downloadspeed);
        $stmt->bindParam(':ul', $entry->uploadspeed);
        $stmt->bindParam(':ping', $entry->ping);
        $stmt->bindParam(':jitter', $entry->jitter);
        $stmt->bindParam(':log', $entry->log);
        return $stmt->execute() ? $this->connection->lastInsertId() : $stmt->errorInfo();
    }

    public function update(TelemetryEntry $entry)
    {
        if (!isset($user->id)) {
            // We can't update a record unless it exists...
            throw new LogicException(
                'Cannot update TelemetryEntry that does not yet exist in the database.'
            );
        }
        $stmt = $this->connection->prepare('
            UPDATE speedtest_users
            SET
                ip = :ip
                ispinfo = :ispinfo
                extra = :extra
                ua = :ua
                lang = :lang
                dl = :dl
                ul = :ul
                ping = :ping
                jitter = :jitter
                log = :log
            WHERE id = :id
        ');
        $stmt->bindParam(':ip', $entry->ip);
        $stmt->bindParam(':ispinfo', $entry->ispinfo);
        $stmt->bindParam(':extra', $entry->extra);
        $stmt->bindParam(':ua', $entry->uploadspeed);
        $stmt->bindParam(':lang', $entry->locale);
        $stmt->bindParam(':dl', $entry->downloadspeed);
        $stmt->bindParam(':ul', $entry->uploadspeed);
        $stmt->bindParam(':ping', $entry->ping);
        $stmt->bindParam(':jitter', $entry->jitter);
        $stmt->bindParam(':log', $entry->log);
        return $stmt->execute();
    }

    public function findAll() {
        $stmt = $this->connection->prepare('
            SELECT *
             FROM speedtest_users
             ORDER BY timestamp desc limit 0,100
        ');
        $stmt->execute();

        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 'TelemetryEntry');
        return $stmt->fetchAll();
    }

    public function find($id) {
        $stmt = $this->connection->prepare('
            SELECT *
             FROM speedtest_users
             WHERE id = :id
        ');
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 'TelemetryEntry');
        return $stmt->fetch();
    }
}


$username=null; $password=null;
if ($db_type == "mysql") {
	$dsn = "mysql:host=$MySql_hostname;dbname=$MySql_databasename";
    $username = $MySql_username;
    $password = $MySql_password;
} else if ($db_type == "sqlite") {
	$dsn = "sqlite:$Sqlite_db_file";
} else if ($db_type == "postgresql") {
    $dsn = "pgsql:host=$PostgreSql_hostname;dbname=$PostgreSql_databasename";
    $username = $PostgreSql_username;
    $password = $PostgreSql_password;
} else die();

$connection = new PDO($dsn, $username, $password) or die();
$repository = new TelemetryRepository($connection);

?>