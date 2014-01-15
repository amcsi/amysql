<?php
class AMysql_TestCase extends PHPUnit_Framework_TestCase
{

    public $tableName = 'abstracttest';
    protected $_amysql;

    public function setUp() {
        if ('mysqli' == SQL_DRIVER) {
            $this->_amysql = new AMysql(
                AMYSQL_TEST_HOST, AMYSQL_TEST_USER, AMYSQL_TEST_PASS);
            $this->_amysql->selectDb(AMYSQL_TEST_DB);
        }
        else if ('mysql' == SQL_DRIVER) {
            if (
                version_compare(PHP_VERSION, '5.5.0') >= 0 &&
                function_exists('mysql_connect')
            ) {
                error_reporting(error_reporting() & ~E_DEPRECATED);
            }
            $conn = mysql_connect(AMYSQL_TEST_HOST, AMYSQL_TEST_USER,
                AMYSQL_TEST_PASS);
            $this->_amysql = new AMysql($conn);
            $this->_amysql->selectDb(AMYSQL_TEST_DB);
        }

        $this->createTable();
    }

    public function createTable() {
        $sql = <<<EOT
CREATE TABLE IF NOT EXISTS `$this->tableName` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `string` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
EOT;
        $this->_amysql->query($sql);
    }

    public function tearDown()
    {
        $this->_amysql->query("DROP TABLE `$this->tableName`");
        $this->_amysql = null;
    }
}
