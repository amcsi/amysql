<?php
/**
 * Test the 2006 "server has gone away" error here.
 **/

include dirname(__FILE__) . '/../TestHelper.php';


$amysql = new AMysql;
$connDetails = array(
    'host' => AMYSQL_TEST_HOST,
    'username' => AMYSQL_TEST_USER,
    'password' => AMYSQL_TEST_PASS,
    'db' => AMYSQL_TEST_DB,
    'driver' => SQL_DRIVER,
    'autoPingSeconds' => 9,
);
$amysql->setConnDetails($connDetails);
$amysql->connect();

sleep(10); // restart the mysql service here quickly

try {
    $amysql->setNames('utf8');
    echo "success\n";
} catch (Exception $e) {
    echo "failure\n";
    trigger_error($e);
}
