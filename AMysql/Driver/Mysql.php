<?php
/**
 * The driver for the old mysql_* functions
 * 
 * @abstract
 * @package amysql
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Driver_Mysql extends AMysql_Driver_Abstract
{
    public function selectDb($db)
    {
        return mysql_select_db($db, $this->link);
    }

    public function query($sql) {

        $link = $this->link;
        $this->lastQueryTime = null;

        $startTime = microtime(true);
        $result = mysql_query($sql, $link);
        $duration = microtime(true) - $startTime;
        $this->lastQueryTime = $duration;

        $this->lastAffectedRows = mysql_affected_rows($link);
        $this->lastInsertId = mysql_insert_id($link);
        $this->lastError = '';
        $this->lastErrno = 0;
        if (false === $result) {
            $this->lastError = mysql_error($link);
            $this->lastErrno = mysql_errno($link);
        }
        return $result;
    }

    public function numRows($result)
    {
        return mysql_num_rows($result);
    }

    public function fetchAssoc($result)
    {
        return mysql_fetch_assoc($result);
    }

    public function fetchRow($result)
    {
        return mysql_fetch_row($result);
    }

    public function fetchArray($result)
    {
        return mysql_fetch_array($result);
    }

    public function fetchObject($result, $className, array $params)
    {
        if ($params) {
            return mysql_fetch_object($result, $className, $params);
        }
        return mysql_fetch_object($result, $className);
    }

    public function result($result, $row = 0, $field = 0)
    {
        return mysql_result($result, $row, $field);
    }

    public function realEscapeString($string)
    {
        return mysql_real_escape_string($string, $this->link);
    }

    public function dataSeek($result, $row)
    {
        return mysql_data_seek($result, $row);
    }

    public function setCharset($charset)
    {
        static $fe;
        if (!isset($fe)) {
            $fe = function_exists('mysql_set_charset');
        }

        if (!$fe) {
            return mysql_query("SET CHARACTER SET '$charset'", $this->link);
        }
        return mysql_set_charset($charset, $this->link);
    }

    public function free($result)
    {
        return mysql_free_result($result);
    }

    public function getError()
    {
        return mysql_error($this->link);
    }

    public function getErrno()
    {
        return mysql_errno($this->link);
    }

    public function getConnectionError()
    {
        return mysql_error();
    }

    public function getConnectionErrno()
    {
        return mysql_errno();
    }
}
