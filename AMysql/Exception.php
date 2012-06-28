<?php
/**
 * Mysql kivétel osztály
 * @author Szerémi Attila 
 * @version 0.9.1.1
 **/ 
class AMysql_Exception extends RuntimeException {

    /**
     * @var string Query string, ami a mysql hibát okozta (ha van)
     **/         
    public $query;

    /**
     * @var boolean Automatikusan triggereljen-e errort példányosításnál.
     * Ezáltal, ha error-ok logolva vannak, akkor mindig logolva is lesznek
     * a hibák.
     * Ha -1, akkor úgy viselkedik, mint egy alap trigger_error, csak
     * manuálisan. Ez akkor jó, ha a PHP scripteken belül
     * set_error_handler() használva van, és átalakítja a hibakezelést.
     **/     	
    public $autoTriggerErrors = 1;
    /**
     * @var int Az automatikus hiba szintje
     **/     	
    public $errorLevel = E_USER_WARNING;
    /**
     * @var Automatikusan logolja-e a hibákat. Ha az error triggerelés logol
     * is egyben, nem logol mégegyszer feleslegesen.	 
     **/
    public $autoLog = false;

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
