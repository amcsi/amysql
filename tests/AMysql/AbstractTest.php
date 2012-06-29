<?php
class AbstractTest extends PHPUnit_Framework_TestCase {

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

    public function createTable() {
    }

    /**
     * Inserting to the database with indices as the outer array and
     * columns as the inner.
     **/
    public function testInsert() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => '3'
	    ),
	    array (
		'id' => '2',
		'string' => 'blah'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    /**
     * Inserting to the database with columns as the outer array and
     * indices as the inner.
     * 
     * @access public
     * @return void
     */
    public function testInsert2() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => '3'
	    ),
	    array (
		'id' => '2',
		'string' => 'blah'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    public function testGetOne() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$result = $this->_amysql->getOne("SELECT string FROM $this->tableName");
	$this->assertEquals('3', $result);
    }

    public function testGetOneWarning() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$this->setExpectedException('PHPUnit_Framework_Error_Warning');
	$result = $this->_amysql->getOne("SELECT string FROM $this->tableName
	    WHERE id = '3'");
    }

    public function testGetOneInt() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$result = $this->_amysql->getOneInt("SELECT id FROM $this->tableName");
	$this->assertSame(1, $result);
	$this->assertNotSame('1', $result);
    }

    public function testGetOneInt2() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$result = $this->_amysql->getOneInt("SELECT id FROM $this->tableName
	    WHERE id = '3'");
	$this->assertSame(0, $result);
    }

    public function testGetOneInt3() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$result = $this->_amysql->getOneInt(
	    "SELECT string FROM $this->tableName WHERE string = 'blah'"
	);
	$this->assertSame(0, $result);
    }

    public function testGetOneNull() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$result = $this->_amysql->getOneNull(
	    "SELECT string FROM $this->tableName WHERE id = '3'"
	);
	$this->assertNull($result);
    }

    public function testUpdate() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    'string' => 'foo'
	);
	$this->_amysql->update($this->tableName, $data, 'id = ?',
	    array ('1'));
	$results = $this->_amysql->query("SELECT * FROM $this->tableName")
	    ->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => 'foo'
	    ),
	    array (
		'id' => '2',
		'string' => 'blah'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    public function testUpdateNotArrayBinds() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    'string' => 'foo'
	);
	$this->setExpectedException('InvalidArgumentException');
	$this->_amysql->update($this->tableName, $data, 'id = ?',
	    '1');
    }

    public function testUpdateMultipleByKey() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    1 => array (
		'string' => 'foo'
	    ),
	    2 => array (
		'string' => 'bar'
	    )
	);
	$this->_amysql->updateMultipleByKey($this->tableName, $data, 'id');
	$results = $this->_amysql->query("SELECT * FROM $this->tableName")
	    ->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => 'foo'
	    ),
	    array (
		'id' => '2',
		'string' => 'bar'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    public function testUpdateMultipleByKeySameColumn() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    1 => array (
		'id' => 3,
		'string' => 'foo'
	    ),
	    2 => array (
		'id' => 4,
		'string' => 'bar'
	    )
	);
	$this->_amysql->updateMultipleByKey($this->tableName, $data, 'id');
	$results = $this->_amysql->query("SELECT * FROM $this->tableName")
	    ->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => 'foo'
	    ),
	    array (
		'id' => '2',
		'string' => 'bar'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    public function testUpdateMultipleByKeySameColumn2() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    1 => array (
		'id' => 3,
		'string' => 'foo'
	    ),
	    2 => array (
		'id' => 4,
		'string' => 'bar'
	    )
	);
	$success =
	    $this->_amysql->updateMultipleByKey($this->tableName, $data, 'id',
	    true);
	$results = $this->_amysql->query("SELECT * FROM $this->tableName")
	    ->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '3',
		'string' => 'foo'
	    ),
	    array (
		'id' => '4',
		'string' => 'bar'
	    )
	);
	$this->assertTrue($success);
	$this->assertEquals($expected, $results);
    }

    public function testUpdateMultipleByData() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$data = array (
	    array (
		'id' => 2,
		'string' => 'foo'
	    ),
	    array (
		'id' => 1,
		'string' => 'bar'
	    )
	);
	$success =
	    $this->_amysql->updateMultipleByData($this->tableName, $data, 'id',
	    true);
	$results = $this->_amysql->query("SELECT * FROM $this->tableName")
	    ->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => 'bar'
	    ),
	    array (
		'id' => '2',
		'string' => 'foo'
	    )
	);
	$this->assertTrue($success);
	$this->assertEquals($expected, $results);
    }
}
?>

