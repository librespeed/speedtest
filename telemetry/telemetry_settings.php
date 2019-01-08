<?php

$db_type="mysql"; //Type of db: "mysql", "sqlite" or "postgresql"
$stats_password = getenv('STATS_PASSWORD') ?? "PASSWORD"; //password to login to stats.php. Change this!!!
$enable_id_obfuscation=false; //if set to true, test IDs will be obfuscated to prevent users from guessing URLs of other tests

// Sqlite3 settings
$Sqlite_db_file = "../../telemetry.sql";

// Mysql settings
$MySql_username = getenv('MYSQL_USER') ?? "USERNAME";
$MySql_password = getenv('MYSQL_PASSWORD') ?? "PASSWORD";
$MySql_hostname = getenv('MYSQL_HOST') ?? "DB_HOSTNAME";
$MySql_databasename = getenv('MYSQL_DATABASE') ?? "DB_NAME";

// Postgresql settings
$PostgreSql_username = getenv('POSTGRESQL_USER') ?? "USERNAME";
$PostgreSql_password = getenv('POSTGRESQL_PASSWORD') ?? "PASSWORD";
$PostgreSql_hostname = getenv('POSTGRESQL_HOST') ?? "DB_HOSTNAME";
$PostgreSql_databasename = getenv('POSTGRESQL_DATABASE') ?? "DB_NAME";


//IMPORTANT: DO NOT ADD ANYTHING BELOW THIS PHP CLOSING TAG, NOT EVEN EMPTY LINES!
?>