<?php

use Bencode\SnowflakeOdbc\SnowflakeOdbcDriver;
use PHPUnit\Framework\TestCase;

class SnowflakeOdbcDriverTest extends TestCase
{
    protected array $config;

    protected function setUp(): void
    {
        // Load konfigurasi dari environment atau manual
        $this->config = [
            'dsn' => 'SnowflakeDSN',
            'username' => 'digitalisasiproduksipengadaan',
            'password' => 'DPPsehatselalu1',
            'database' => 'MIDDLE_DB_SHARED',
            'retries' => 2,
            'retry_delay' => 1,
        ];
    }

    public function test_can_create_driver_instance()
    {
        $driver = new SnowflakeOdbcDriver();
        $this->assertInstanceOf(SnowflakeOdbcDriver::class, $driver);
    }

    public function test_connect_success_or_fail_gracefully()
    {
        $driver = new SnowflakeOdbcDriver();

        try {
            $connection = $driver->connect($this->config);
            $this->assertNotNull($connection, 'Connection should not be null');
            echo "\n✅ Connection established successfully.\n";
        } catch (Exception $e) {
            echo "\n⚠️ Connection failed as expected (no DSN or invalid creds): " . $e->getMessage() . "\n";
            $this->assertStringContainsString('connection failed', strtolower($e->getMessage()));
        }
    }

    public function test_reconnect_logic_when_fail()
    {
        $driver = new SnowflakeOdbcDriver();

        // Pakai DSN palsu untuk memicu retry logic
        $badConfig = $this->config;
        $badConfig['dsn'] = 'InvalidDSN_' . uniqid();

        $start = microtime(true);

        try {
            $driver->connect($badConfig);
            $this->fail('Expected exception not thrown for invalid DSN');
        } catch (Exception $e) {
            $duration = microtime(true) - $start;
            echo "\n⏱️ Total retry duration: {$duration} seconds\n";
            $this->assertStringContainsString('after', $e->getMessage());
        }
    }
public function test_reconnect_ten_times_on_fail()
{
    $driver = new \Bencode\SnowflakeOdbc\SnowflakeOdbcDriver();

    $badConfig = $this->config;
    $badConfig['dsn'] = 'InvalidDSN_' . uniqid();
    $badConfig['retries'] = 10;       // 👈 Reconnect hingga 10 kali
    $badConfig['retry_delay'] = 0.5;  // setengah detik biar gak terlalu lama

    $start = microtime(true);

    try {
        $driver->connect($badConfig);
        $this->fail('Expected exception not thrown for invalid DSN after 10 retries');
    } catch (Exception $e) {
        $duration = microtime(true) - $start;
        echo "\n[🔁 Reconnect Test] Total duration after 10 retries: {$duration} seconds\n";
        $this->assertStringContainsString('after 10 attempts', $e->getMessage());
    }
}

public function test_reconnect_after_disconnect()
{
    $driver = new \Bencode\SnowflakeOdbc\SnowflakeOdbcDriver();

    echo "\n🔌 Step 1: Establishing initial connection...\n";
    $connection = $driver->connect($this->config);

    $this->assertNotNull($connection, 'Initial connection should succeed');
    echo "✅ Connected successfully.\n";

    // Simulasikan koneksi putus dengan menutup ODBC dan PDO
    echo "🔌 Step 2: Simulating disconnect...\n";
    $reflection = new ReflectionClass($connection);
    if ($reflection->hasProperty('odbcConnection')) {
        $prop = $reflection->getProperty('odbcConnection');
        $prop->setAccessible(true);
        $odbcConn = $prop->getValue($connection);

        if ($odbcConn) {
            @odbc_close($odbcConn);
        }
    }

    try {
        // Jalankan query setelah koneksi putus
        echo "⚙️ Step 3: Executing query after disconnect...\n";
        $stmt = @odbc_exec($connection->getOdbcConnection(), 'SELECT 1');
        if (!$stmt) {
            throw new Exception("Lost connection to Snowflake.");
        }
    } catch (Exception $e) {
        echo "⚠️ Connection lost detected: {$e->getMessage()}\n";
        echo "🔁 Step 4: Attempting reconnect...\n";

        // Reconnect pakai driver yang sama
        $newConnection = $driver->connect($this->config);
        $this->assertNotNull($newConnection, 'Reconnect should succeed');
        echo "✅ Reconnected successfully.\n";
    }
}

}

