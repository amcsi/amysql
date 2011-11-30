<?php
/**
 * Mysql-es kifejezésekkel foglalkozó osztály az AMysql-hez.
 * @author Szerémi Attila
 * @created 2011.06.10. 13:26:56  
 * @version 5
 **/ 
class AMysql_Expr {

    public $prepared; 
    
    /**
     * Literális string
     **/         
    const EXPR_LITERAL = 0;
    /**
     * IN() függvény. Olyankor a 2. argumentum a tábla neve, a 3. argumentum
     * az értékek tömbje     
     **/         
    const EXPR_COLUMN_IN = 1;

    /**
     * Literális kifejezés esetén csak 1 paramétert kell átadni, annak meg
     * stringnek kell lennie     
     **/         
    public function __construct() {
        $args = func_get_args();
        // literál
        if (is_string($args[0])) {
            $prepared = $args[0];
        }
        else {
            switch ($args[0]) {
                case self::EXPR_LITERAL:
                    $prepared = $args[1];
                case self::EXPR_COLUMN_IN: {
                    $prepared = '';
                    if ($args[2]) {
                        $prepared = AMysql::escapeTable($args[1]) . ' IN 
                        (' . join(', ', $args[2]) . ') ';
                    }
                }
            }
        }
        $this->prepared = $prepared;
    }
    
    public function toString() {
        return $this->prepared;
    }
    
    public function __toString() {
        return $this->toString();
    }
} 
?>
