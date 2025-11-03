<?php

echo "=== Manual ODBC Connection Test ===\n";

$dsn = 'SnowflakeDSN';
$username = 'digitalisasiproduksipengadaan';
$password = 'DPPsehatselalu1';

echo "DSN: {$dsn}\n";
echo "Username: {$username}\n\n";

// Test basic ODBC
echo "1. Checking ODBC extension... ";
if (!extension_loaded('odbc')) {
    die("❌ ODBC extension not loaded!\n");
}
echo "✅ OK\n";

// Test connection
echo "2. Attempting connection... ";
$conn = @odbc_connect($dsn, $username, $password);

if (!$conn) {
    $error = odbc_errormsg();
    echo "❌ FAILED: {$error}\n\n";
    
    echo "=== Troubleshooting Steps ===\n";
    echo "1. Check DSN configuration\n";
    echo "2. Verify Snowflake ODBC driver is installed\n";
    echo "3. Check driver path in odbcinst.ini\n";
    
} else {
    echo "✅ SUCCESS!\n\n";
    
    // Test query
    echo "3. Testing query... ";
    $result = @odbc_exec($conn, 'SELECT CURRENT_VERSION() as version');
    
    if ($result) {
        odbc_fetch_row($result);
        $version = odbc_result($result, 'version');
        echo "✅ Snowflake Version: {$version}\n";
        odbc_free_result($result);
    } else {
        echo "❌ Query failed: " . odbc_errormsg($conn) . "\n";
    }
    
    odbc_close($conn);
}