<?php
/**
 * Mysql abstraction which only uses mysql_* functions
 * @author Szerémi Attila
 * @version 0.8
 *   
 **/
$dir = dirname(realpath(__FILE__));
require_once $dir . '/AMysql/Abstract.class.php';
require_once $dir . '/AMysql/Exception.class.php';
require_once $dir . '/AMysql/Expr.class.php';
require_once $dir . '/AMysql/Statement.class.php';

class AMysql extends Amysql_Abstract 
{
}
?>
