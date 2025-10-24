<?php

namespace Bencode\SnowflakeOdbc;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;
use Exception;

class SnowflakeOdbcDriver extends Connector implements ConnectorInterface
{
    /**
     * Establish a PDO ODBC connection with automatic reconnect.
     */
    public function connect(array $config)
    {
        $dsn      = $config['dsn'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $database = $config['database'] ?? '';
        $prefix   = $config['prefix'] ?? '';
        $maxRetries = $config['retries'] ?? 3;
        $retryDelay = $config['retry_delay'] ?? 2; 
        $pdo = null;
        $odbcConn = null;
        $attempt = 0;

        while ($attempt < $maxRetries) {
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

                $testQuery = @odbc_exec($odbcConn, 'SELECT CURRENT_VERSION()');
                if (!$testQuery) {
                    throw new Exception("Snowflake connection test failed: " . odbc_errormsg($odbcConn));
                }

                break;
            } catch (Exception $e) {
                $attempt++;

                if ($attempt < $maxRetries) {
                    error_log("[SnowflakeOdbcDriver] Connection attempt {$attempt} failed: {$e->getMessage()}");
                   usleep((int) ($retryDelay * 1_000_000));

                } else {                   
                    throw new Exception("Snowflake PDO/ODBC connection failed after {$maxRetries} attempts: " . $e->getMessage());
                }
            }
        }

        $connection = new SnowflakeOdbcConnection($pdo, $database, $prefix, $config);
        $connection->setOdbcConnection($odbcConn);

        return $connection;
    }
}
