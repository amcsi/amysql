<?php /* vim: set expandtab : */
/**
 * MySQL exception class
 *
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila 
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 **/ 
class AMysql_Exception extends RuntimeException
{

    /**
     * @var string The query string mysql had a problem with (if there is one).
     **/         
    public $query;
    protected $errorTriggered = 0;
    protected $properties;

    /**
     * Duplicate entry '(0)' for key '(1)'
     */
    const CODE_DUPLICATE_ENTRY = 1062; 

    /**
     * Mysql server has gone away
     */
    const CODE_SERVER_GONE_AWAY = 2006;

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

    public function getProperties()
    {
        if (!isset($this->properties)) {
            switch ($this->getCode()) {
                case self::CODE_DUPLICATE_ENTRY:
                    $pattern = "@Duplicate entry '(.*)' for key '(.*)'@";
                    $message = $this->getMessage();
                    preg_match($pattern, $message, $props);
                    array_shift($props);
                    break;
                default:
                    $props = array();
                    break;
            }
            $this->properties = $props;
        }
        return $this->properties;
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
