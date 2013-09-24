<?php
class AMysql_Driver_Mysql extends AMysql_Driver_Abstract
{
    public function query($sql) {

        $link = $this->link;
        $this->lastQueryTime = null;

        if ($this->profileQueries) {
            $startTime = microtime(true);
            $result = mysql_query($sql, $link);
            $duration = microtime(true) - $startTime;
            $this->lastQueryTime = $duration;
        }
        else {
            $result = mysql_query($sql, $link);
        }

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
}
