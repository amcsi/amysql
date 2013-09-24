<?php
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
}
