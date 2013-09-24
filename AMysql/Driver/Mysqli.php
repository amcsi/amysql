<?php
class AMysql_Driver_Mysqli extends AMysql_Driver_Abstract
{
    public function query($sql) {

        $link = $this->link;
        $this->lastQueryTime = null;

        if ($this->profileQueries) {
            $startTime = microtime(true);
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $success = $stmt->execute();
            }
            $duration = microtime(true) - $startTime;
            $this->lastQueryTime = $duration;
        }
        else {
            $stmt = $link->prepare($sql);
            if ($stmt) {
                $success = $stmt->execute();
            }
        }
        $result = $stmt ? $stmt->get_result() : false;
        if (!$result && $success) {
            /**
             * In mysqli, result_metadata will return a falsy value
             * even for successful SELECT queries, so for compatibility
             * let's set the result to true if it isn't an object (is false),
             * but the query was successful.
             */
            $result = true;
        }
        $this->lastInsertId = $stmt->insert_id;
        $this->lastAffectedRows = $stmt->affected_rows;
        $this->lastError = '';
        $this->lastErrno = 0;
        if (false === $result) {
            $this->lastError = $link->error;
            $this->lastErrno = $link->errno;
        }
        return $result;
    }
}
