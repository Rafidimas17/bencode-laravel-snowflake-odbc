<?php
namespace Bencode\SnowflakeOdbc;

use Illuminate\Database\Connection;
use Exception;

class SnowflakeOdbcConnection extends Connection
{
    protected $odbcConnection;

    public function setOdbcConnection($odbcConn)
    {
        $this->odbcConnection = $odbcConn;
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (!$this->odbcConnection) {
            throw new Exception("ODBC connection not set.");
        }

        // simple replace bindings
        foreach ($bindings as $key => $value) {
            $bindings[$key] = is_numeric($value) ? $value : "'".$value."'";
        }
        $sql = vsprintf(str_replace("?", "%s", $query), $bindings);

        $stmt = odbc_exec($this->odbcConnection, $sql);
        if (!$stmt) {
            throw new Exception("ODBC query failed: " . odbc_errormsg($this->odbcConnection));
        }

        $rows = [];
        while ($row = odbc_fetch_array($stmt)) {
            $rows[] = $row;
        }

        return $rows;
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new SnowflakeOdbcQueryGrammar);
    }

    protected function getDoctrineDriver()
    {
        return null;
    }
}
