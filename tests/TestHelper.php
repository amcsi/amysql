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

/**
 * Make sure you have a mysql user and database preprepared for the tests
 **/
$conf = array();
if (file_exists($filename = dirname(__FILE__) . '/config/conf.dist.php')) {
    include $filename;
}
if (file_exists($filename = dirname(__FILE__) . '/config/conf.php')) {
    include $filename;
}

define('AMYSQL_TEST_HOST', $conf['amysqlTestHost']);
define('AMYSQL_TEST_USER', $conf['amysqlTestUser']);
define('AMYSQL_TEST_PASS', $conf['amysqlTestPass']);
define('AMYSQL_TEST_DB', $conf['amysqlTestDb']);
define('SQL_DRIVER', $conf['amysqlTestDriver']);

require_once dirname(__FILE__) . '/AMysql_TestCase.php';

require_once APPLICATION_PATH . '/AMysql.php';
