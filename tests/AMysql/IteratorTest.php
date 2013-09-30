<?php /* vim: set tabstop=8 expandtab : */
class IteratorTest extends PHPUnit_Framework_TestCase {

    protected $_conn;
    protected $_amysql;

    public $tableName = 'abstracttest';

    public function setUp() {
        if ('mysqli' == SQL_DRIVER) {
            $this->_amysql = new AMysql(
                AMYSQL_TEST_HOST, AMYSQL_TEST_USER, AMYSQL_TEST_PASS);
            $this->_amysql->selectDb(AMYSQL_TEST_DB);
        }
        else if ('mysql' == SQL_DRIVER) {
            $conn = mysql_connect(AMYSQL_TEST_HOST, AMYSQL_TEST_USER,
                AMYSQL_TEST_PASS);
            $this->_amysql = new AMysql($conn);
            $this->_amysql->selectDb(AMYSQL_TEST_DB);
        }
        else if ('pgsql' == SQL_DRIVER) {
            $config = array (
                'system' => 'pgsql',
                'host' => AMYSQL_TEST_HOST,
                'username' => AMYSQL_TEST_USER,
                'password' => AMYSQL_TEST_PASS,
                'db' => AMYSQL_TEST_DB,
            );
            $this->_amysql = new AMysql($config);
        }

        $this->createTable();
    }

    public function createTable() {
        if ($this->_amysql->getDriver() instanceof AMysql_Driver_Postgresql) {
            $sql = <<<EOT
DROP TABLE IF EXISTS $this->tableName;
CREATE TABLE abstracttest
(
   id serial PRIMARY KEY, 
   string character varying(255)
)
WITH (
  OIDS = FALSE
)

TABLESPACE pg_default;
EOT;
        }
        else {
            $sql = <<<EOT
CREATE TABLE IF NOT EXISTS `$this->tableName` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `string` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
EOT;
        }
        try {
            $this->_amysql->query($sql);
        }
        catch (Exception $e) {
            trigger_error($e, E_USER_WARNING);
        }
    }

    public function tearDown() {
        try {
            $this->_amysql->query("DROP TABLE $this->tableName");
        }
        catch (Exception $e) {

        }
	$this->_amysql = null;
    }

    public function testIterate() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$i = 0;
	foreach ($stmt as $key => $value) {
	    if ($i == 0) {
		$this->assertEquals(0, $key);
		$this->assertEquals('3', $value['string']);
	    }
	    if ($i == 1) {
		$this->assertEquals(1, $key);
		$this->assertEquals('blah', $value['string']);
	    }
	    if ($i == 2) {
		$this->fail();
	    }
	    $i++;
	}
	$i = 0;
	foreach ($stmt as $key => $value) {
	    if ($i == 0) {
		$this->assertEquals(0, $key);
		$this->assertEquals('3', $value['string']);
	    }
	    if ($i == 1) {
		$this->assertEquals(1, $key);
		$this->assertEquals('blah', $value['string']);
	    }
	    if ($i == 2) {
		$this->fail();
	    }
	    $i++;
	}
    }

    public function testIterateNonSelect() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->lastStatement;
	$this->setExpectedException('LogicException');
	foreach ($stmt as $key => $value) {
	}
    }
}
?>

