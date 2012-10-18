<?php /* vim: set tabstop=8 expandtab : */
class IteratorTest extends PHPUnit_Framework_TestCase {

    protected $_conn;
    protected $_amysql;

    public $tableName = 'abstracttest';

    public function setUp() {
	$this->_amysql = new AMysql(
	    AMYSQL_TEST_HOST, AMYSQL_TEST_USER, AMYSQL_TEST_PASS);
	$this->_amysql->selectDb(AMYSQL_TEST_DB);

	$sql = <<<EOT
CREATE TABLE IF NOT EXISTS `$this->tableName` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `string` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
EOT;
	$this->_amysql->query($sql);
    }

    public function tearDown() {
	$this->_amysql->query("DROP TABLE `$this->tableName`");
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

