<?php /* vim: set tabstop=8 expandtab : */
/**
 * MySQL exception class
 *
 * Visit https://github.com/amcsi/amysql
 * @author Szerémi Attila 
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 * @version 0.9.2.6
 **/ 
class AMysql_Exception extends RuntimeException {

    /**
     * @var string The query string mysql had a problem with (if there is one).
     **/         
    public $query;

    /**
     * @var boolean Shall an error automatically be triggered on instantiation.
     * With the help of this, if errors are being logged, then amysql
     * exceptions will automatically be logged as well.
     * This can also be set to -1. In that case, it manually logs the error
     * with error_log(), and writes out the error if display_errors is on.
     * This is for in the case that set_error_handler() is set, but isn't
     * being used in a practical way enough.
     **/     	
    public $autoTriggerErrors = 1;

    /**
     * @var integer The level of the error to be triggered.
     **/     	
    public $errorLevel = E_USER_WARNING;
    /**
     * @var boolean Always log the messages. It will not log twice if
     * trigger_error() is set here and the error_log directive is set.
     **/
    public $autoLog = false;

    /**
     * MySQL error message.
     **/
    public $origMsg;

    public function __construct($msg, $errno, $query) {
        $this->query = $query;
        parent::__construct($msg, $errno);
        $this->origMsg = $msg;
        $toLog = $this->autoLog;
        $logMessage = $this->getLogMessage();
        if ($this->autoTriggerErrors) {
            $errorLevel = $this->errorLevel;
            if (-1 === $this->autoTriggerErrors) {
                if (ini_get('display_errors')) {
                    echo $logMessage;
                }
                /**
                 * Ha a szerveren nincs beállítva, hogy a megadott szintű
                 * hibákat jelentse, akkor ne is logolja.                 
                 **/                                 
                if (!(error_reporting() & $errorLevel)) {
                    $toLog = false;
                }
            }
            else {
                trigger_error(
                    $this,
                    $errorLevel
                );
                /**
                 * Ha az error_reporting bitmaskben benne van az $errorLevel,
                 * akkor, ha autoLog be van kapcsolva, akkor sincs szükség logoláshoz,
                 * mert az error triggerelés automatikusan logol is.                 		 
                 **/                 		
                if (ini_get('log_errors') and error_reporting() & $errorLevel) {
                    $toLog = false;
                }
            }
        }
        if ($toLog) {
            error_log($logMessage);
        }
    }

    public function getDetails() {
        return "Code: $this->code\n" .
            "Message: $this->message\n" .
            "Last query: $this->query";
    }

    public function getLogMessage() {
        return "Mysql error!\n" . $this->getDetails();
    }

    public function __toString() {
        return "AMysqlException: `$this->message`\n" .
            "Error code `$this->code` in $this->file:$this->line\n" .
            "Query: $this->query\n";
    }
}
?>
