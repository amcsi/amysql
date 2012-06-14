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

require_once APPLICATION_PATH . '/AMysql.php';
?>
