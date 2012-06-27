<?php
/**
 * To run these tests, have a mysql user of amysql with no password
 **/
ob_start();
define('BASE_PATH', realpath(dirname(__FILE__) . '/../'));
define('APPLICATION_PATH', BASE_PATH);
define('APPLICATION_ENV', 'testing');

set_include_path(
	'.'
    . PATH_SEPARATOR . BASE_PATH
    . PATH_SEPARATOR . get_include_path()
);

$mysqlHost  = 'localhost';
$mysqlUser  = 'amysql';
$mysqlPass  = '';
$mysqlDb    = 'amysql';

define('AMYSQL_TEST_HOST', $mysqlHost);
define('AMYSQL_TEST_USER', $mysqlUser);
define('AMYSQL_TEST_PASS', $mysqlPass);
define('AMYSQL_TEST_DB', $mysqlDb);

require_once APPLICATION_PATH . '/AMysql.php';
?>
