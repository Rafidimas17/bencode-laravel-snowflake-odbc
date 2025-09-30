<?php

namespace Bencode\SnowflakeOdbc;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;
use Exception;

class SnowflakeOdbcDriver extends Connector implements ConnectorInterface
{
    /**
     * Establish a PDO ODBC connection.
     */
   public function connect(array $config)
{
    $dsn      = $config['dsn'] ?? '';
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';

    try {
        $pdoDsn = "odbc:" . $dsn;
        $pdo = new PDO($pdoDsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $odbcConn = odbc_connect($dsn, $username, $password);
        if (!$odbcConn) {
            throw new Exception("ODBC connection failed: " . odbc_errormsg());
        }

    } catch (Exception $e) {
        throw new Exception("Snowflake PDO/ODBC connection failed: " . $e->getMessage());
    }

    $connection = new SnowflakeOdbcConnection($pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config);
    $connection->setOdbcConnection($odbcConn);

    return $connection;
}

}
