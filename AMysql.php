<?php
/**
 * Mysql abstraction which only uses mysql_* functions
 * @author Szerémi Attila
 * @version 0.8
 *   
 **/
$dir = dirname(realpath(__FILE__));
require_once $dir . '/AMysql/Abstract.php';
require_once $dir . '/AMysql/Exception.php';
require_once $dir . '/AMysql/Expr.php';
require_once $dir . '/AMysql/Statement.php';

class AMysql extends Amysql_Abstract 
{
}
?>
