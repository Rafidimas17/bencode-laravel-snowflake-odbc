<?php

namespace Bencode\SnowflakeOdbc;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;
use Exception;

class SnowflakeOdbcDriver extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     */
    public function connect(array $config)
    {
        $dsn = $config['dsn'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $database = $config['database'] ?? '';
        $prefix = $config['prefix'] ?? '';
        $options = $config['options'] ?? [];
       
        $odbcConn = $this->createOdbcConnection($dsn, $username, $password, $config);        
        $pdo = $this->createCompatiblePdo();

        $connection = new SnowflakeOdbcConnection($pdo, $database, $prefix, $config);
        $connection->setOdbcConnection($odbcConn);

        return $connection;
    }

    /**
     * Create ODBC connection with retry logic
     */
    private function createOdbcConnection(string $dsn, string $username, string $password, array $config = [])
    {
        $maxRetries = $config['retries'] ?? 3;
        $retryDelay = $config['retry_delay'] ?? 2;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {                
                $conn = odbc_connect($dsn, $username, $password);
                
                if (!$conn) {
                    throw new Exception("ODBC connection failed: " . odbc_errormsg());
                }

                // Test the connection
                $testQuery = @odbc_exec($conn, 'SELECT CURRENT_VERSION()');
                if (!$testQuery) {
                    throw new Exception("Snowflake connection test failed: " . odbc_errormsg($conn));
                }
                odbc_free_result($testQuery);

                return $conn;
            } catch (Exception $e) {
                $attempt++;

                if ($attempt < $maxRetries) {
                    error_log("[SnowflakeOdbcDriver] Connection attempt {$attempt} failed: {$e->getMessage()}");
                    usleep((int) ($retryDelay * 1_000_000));
                } else {
                    throw new Exception("Snowflake ODBC connection failed after {$maxRetries} attempts: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create a minimal PDO instance for Laravel compatibility
     */
    private function createCompatiblePdo(): PDO
    {
        // Create a PDO instance for SQLite in memory as a placeholder
        // This maintains compatibility with Laravel's database layer
        return new PDO('sqlite::memory:');
    }

    /**
     * Execute a query with connection management (optional helper method)
     */
    public function execute(string $sql, callable $processor, array $config = []): array
    {
        return $this->withConnection(function($conn) use ($sql, $processor) {
            $result = odbc_exec($conn, $sql);
            
            if (!$result) {
                $error = odbc_errormsg($conn);
                throw new Exception("Failed query: " . $error);
            }

            $data = [];
            $count = 0;

            while (odbc_fetch_row($result)) {
                $processedRow = $processor($result, $count);
                
                if ($processedRow !== null) {
                    $data[] = $processedRow;
                }
                
                $count++;
                
                // Memory management can be added here if needed
                if ($count % 1000 === 0) {
                    $this->checkMemoryAndGC();
                }
            }

            odbc_free_result($result);
            return $data;
        }, $config);
    }

    /**
     * Execute operations with managed connection (optional helper method)
     */
    public function withConnection(callable $callback, array $config = []): mixed
    {
        $dsn = $config['dsn'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $conn = $this->createOdbcConnection($dsn, $username, $password, $config);
        
        try {
            return $callback($conn);
        } finally {
            odbc_close($conn);
            $this->forceMemoryCleanup();
        }
    }

    /**
     * Memory management
     */
    private function checkMemoryAndGC(): void
    {
        if (memory_get_usage(true) > 100 * 1024 * 1024) { // 100MB threshold
            gc_collect_cycles();
        }
    }

    /**
     * Force memory cleanup
     */
    private function forceMemoryCleanup(): void
    {
        gc_collect_cycles();
    }
}