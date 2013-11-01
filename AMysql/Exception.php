<?php /* vim: set expandtab : */
/**
 * MySQL exception class
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila 
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 **/ 
class AMysql_Exception extends RuntimeException {

    /**
     * @var string The query string mysql had a problem with (if there is one).
     **/         
    public $query;
    protected $errorTriggered = 0;

    public function __construct($msg, $errno, $query)
    {
        $this->query = $query;
        parent::__construct($msg, $errno);
    }

    public function getDetails()
    {
        return $this->__toString();
    }

    public function getLogMessage()
    {
        return $this->__toString();
    }

    /**
     * Performs a trigger_error on this exception.
     * Does nothing if this method has already been called on this object.
     * 
     * @access public
     * @return void
     */
    public function triggerErrorOnce()
    {
        if (!$this->errorTriggered) {
            trigger_error($this, E_USER_WARNING);
            $this->errorTriggered++;
        }
    }

    public function __toString()
    {
        return "AMysqlException: `$this->message`\n" .
            "Error code `$this->code` in $this->file:$this->line\n" .
            "Query: $this->query\n";
    }
}
?>
