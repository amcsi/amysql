<?php
/**
 * Abstract class for the SQL drivers.
 * 
 * @abstract
 * @package amysql
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
abstract class AMysql_Driver_Abstract {

    public $profileQueries = false;
    public $link;

    public $lastQueryTime;
    public $lastInsertId;
    public $lastAffectedRows;
    public $lastError;
    public $lastErrno;

    public function __construct($link)
    {
        $this->link = $link;
    }

    /**
     * Selects a db 
     * 
     * @param mixed $sql      The full, bound query string.
     * @abstract
     * @access public
     * @return bool
     */
    abstract function selectDb($db);

    /**
     * query 
     * 
     * @param mixed $sql      The full, bound query string.
     * @abstract
     * @access public
     * @return mixed            The driver-dependent special result variable if the query was
     *                          a SELECT and successful, true if a successful non-SELECT query,
     *                          and false on failure.
     */
    abstract function query($sql);

    /**
     * Gets the number of rows
     * 
     * @param mixed $result 
     * @abstract
     * @access public
     * @return int
     */
    abstract function numRows($result);

    /**
     * Fetches a result row associatively
     * 
     * @param mixed $result 
     * @abstract
     * @access public
     * @return void
     */
    abstract function fetchAssoc($result);

    /**
     * Fetches a result row numerically indexed
     * 
     * @param mixed $result 
     * @abstract
     * @access public
     * @return void
     */
    abstract function fetchRow($result);

    /**
     * Fetches a result row both numerically indexed and associatively
     * 
     * @param mixed $result 
     * @abstract
     * @access public
     * @return void
     */
    abstract function fetchArray($result);

    /**
     * Fetches a result row as an object
     * 
     * @param mixed $result 
     * @abstract
     * @access public
     * @return void
     */
    abstract function fetchObject($result, $className, array $params);

    /**
     * Returns the result of the given row and field. A warning is issued
     * if the result on the given row and column does not exist.
     * 
     * @param int $row		(Optional) The row number.
     * @param mixed $field	(Optional) The field number or name.
     * @return mixed
     */
    abstract function result($result, $row = 0, $field = 0);

    /**
     * Escapes a string to go between apostrophes
     * 
     * @param mixed $string 
     * @abstract
     * @access public
     * @return void
     */
    abstract function realEscapeString($string);

    /**
     * Change the internel SQL row pointer's index
     * 
     * @param int $row 
     * @abstract
     * @access public
     * @return void
     */
    abstract function dataSeek($result, $row);

    /**
     * Sets the character set
     * 
     * @param mixed $charset 
     * @abstract
     * @access public
     * @return void
     */
    abstract function setCharset($charset);

    /**
     * Frees the result
     * 
     * @param mixed $sql 
     * @abstract
     * @access public
     * @return void
     */
    abstract function free($sql);

    /**
     * Gets the last SQL error message
     * 
     * @abstract
     * @access public
     * @return string
     */
    abstract function getError();

    /**
     * Gets the last SQL error code
     * 
     * @abstract
     * @access public
     * @return int
     */
    abstract function getErrno();

    /**
     * Gets the last SQL error message if the connection itself was unsuccessful
     * 
     * @abstract
     * @access public
     * @return string
     */
    abstract function getConnectionError();

    /**
     * Gets the last SQL error code if the connection itself was unsuccessful
     * 
     * @abstract
     * @access public
     * @return int
     */
    abstract function getConnectionErrno();
}
