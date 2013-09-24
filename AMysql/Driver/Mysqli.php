<?php
/**
 * The driver for the Mysqli library
 * 
 * @abstract
 * @package amysql
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
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

    public function numRows($result)
    {
        return $result instanceof Mysqli_Result ? $result->num_rows : false;
    }

    public function fetchAssoc($result)
    {
        return $result->fetch_assoc();
    }

    public function fetchRow($result)
    {
        return $result->fetch_row();
    }

    public function fetchArray($result)
    {
        return $result->fetch_array();
    }

    public function fetchObject($result, $className, array $params)
    {
        if ($params) {
            return $result->fetch_object($className, $params);
        }
        return $result->fetch_object($className);
    }

    public function result($result, $row = 0, $field = 0)
    {
        if ($result->num_rows <= $row) {
            // mysql_result compatibility, sort of...
            trigger_error("Unable to jump to row $row", E_WARNING);
            return false;
        }
        /**
         * @todo optimize
         **/
        $result->data_seek($row);
        $array = $result->fetch_array(MYSQLI_BOTH);
        if (!array_key_exists($field, $array)) {
            // mysql_result compatibility, sort of...
            trigger_error("Unable to access field `$field` of row $row", E_WARNING);
            return false;
        }
        $ret = $array[$field];
        $result->data_seek(0);
        return $ret;
    }

    public function dataSeek($result, $row)
    {
        return $result->data_seek($row);
    }

    public function free($result)
    {
        return $result->free();
    }
}
