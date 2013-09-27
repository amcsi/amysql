<?php
/**
 * The driver for the pg_* functions
 * 
 * @abstract
 * @package amysql
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Driver_Postgresql extends AMysql_Driver_Abstract
{
    public function selectDb($db)
    {
        return pg_select_db($db, $this->link);
    }

    public function query($sql) {

        $link = $this->link;
        $this->lastQueryTime = null;

        if ($this->profileQueries) {
            $startTime = microtime(true);
            $result = pg_query($sql, $link);
            $duration = microtime(true) - $startTime;
            $this->lastQueryTime = $duration;
        }
        else {
            $result = pg_query($sql, $link);
        }

        $this->lastAffectedRows = pg_affected_rows($link);
        $this->lastInsertId = pg_insert_id($link);
        $this->lastError = '';
        $this->lastErrno = 0;
        if (false === $result) {
            $this->lastError = pg_error($link);
            $this->lastErrno = pg_errno($link);
        }
        return $result;
    }

    public function numRows($result)
    {
        return pg_num_rows($result);
    }

    public function fetchAssoc($result)
    {
        return pg_fetch_assoc($result);
    }

    public function fetchRow($result)
    {
        return pg_fetch_row($result);
    }

    public function fetchArray($result)
    {
        return pg_fetch_array($result);
    }

    public function fetchObject($result, $className, array $params)
    {
        if ($params) {
            return pg_fetch_object($result, $className, $params);
        }
        return pg_fetch_object($result, $className);
    }

    public function result($result, $row = 0, $field = 0)
    {
        return pg_result($result, $row, $field);
    }

    public function realEscapeString($string)
    {
        return pg_real_escape_string($string, $this->link);
    }

    public function dataSeek($result, $row)
    {
        return pg_data_seek($result, $row);
    }

    public function setCharset($charset)
    {
        static $fe;
        if (!isset($fe)) {
            $fe = function_exists('pg_set_charset');
        }

        if (!$fe) {
            return pg_query("SET CHARACTER SET '$charset'", $this->link);
        }
        return pg_set_charset($charset, $this->link);
    }

    public function free($result)
    {
        return pg_free_result($result);
    }

    public function getError()
    {
        return pg_error($this->link);
    }

    public function getErrno()
    {
        return pg_errno($this->link);
    }

    public function getConnectionError()
    {
        return pg_error();
    }

    public function getConnectionErrno()
    {
        return pg_errno();
    }
}

