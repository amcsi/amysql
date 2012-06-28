<?php
class StatementTest extends PHPUnit_Framework_TestCase {

    protected $_amysql;

    public $tableName = 'abstracttest';

    public function setUp() {
	$conn = mysql_connect(AMYSQL_TEST_HOST, AMYSQL_TEST_USER,
	    AMYSQL_TEST_PASS);
	$this->_amysql = new AMysql($conn);
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

    public function testNamedBinds1() {
	$sql = ":foo: :bar:";
	$binds = array (
	    ':foo:' => 'text1',
	    ':bar:' => 'text2'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'text1' 'text2'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testNamedBinds2() {
	$sql = ":foo: :foo:";
	$binds = array (
	    ':foo:' => 'text1'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'text1' 'text1'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testNamedBinds3() {
	$sql = ":fooo :foo";
	$binds = array (
	    ':foo' => 'shorter',
	    ':fooo' => 'longer'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'longer' 'shorter'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testAutoColon1() {
	$sql = ":foo :bar";
	$binds = array (
	    'foo' => 's1',
	    'bar' => 's2'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'s1' 's2'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testAutoColon2() {
	$sql = ":foo:bar";
	$binds = array (
	    'foo' => 's1',
	    'bar' => 's2'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'s1''s2'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testAutoColon3() {
	$sql = ":ékezet:árvíz";
	$binds = array (
	    'ékezet' => 's1',
	    'árvíz' => 's2'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'s1''s2'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testNoAutoColon1() {
	$sql = "@ékezet:árvíz:";
	$binds = array (
	    '@ékezet' => 's1',
	    'árvíz:' => 's2'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'s1''s2'";
	$stmt->binds = $binds;
	$this->assertEquals($expected, $stmt->getSql());
    }

    public function testPairUp() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->pairUp();
	$expected = array (
	    '1' => '3',
	    '2' => 'blah'
	);
	$this->assertEquals($expected, $results);
    }

    public function testPairUpColumnNames() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->pairUp('string', 'id');
	$expected = array (
	    '3' => '1',
	    'blah' => '2'
	);
	$this->assertEquals($expected, $results);
    }

    public function testPairUpMixed() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->pairUp('string', 0);
	$expected = array (
	    '3' => '1',
	    'blah' => '2'
	);
	$this->assertEquals($expected, $results);
    }

    public function testPairUpSame() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->pairUp(1, 1);
	$expected = array (
	    '3' => '3',
	    'blah' => 'blah'
	);
	$this->assertEquals($expected, $results);
    }

    public function testFetchObject() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$result = $stmt->fetchObject();
	$this->assertEquals('1', $result->id);
	$this->assertEquals('3', $result->string);
    }

    public function testFetchAllAssoc() {
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

    public function testFetchAllAssocIdColumn() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAllAssoc('id');
	$expected = array (
	    '1' => array (
		'id' => '1',
		'string' => '3'
	    ),
	    '2' => array (
		'id' => '2',
		'string' => 'blah'
	    )
	);
	$this->assertEquals($expected, $results);
    }

    public function testFetchAllAssocIdColumn2() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAllAssoc(1);
	$expected = array (
	    '3' => array (
		'id' => '1',
		'string' => '3'
	    ),
	    'blah' => array (
		'id' => '2',
		'string' => 'blah'
	    )
	);
	$this->assertEquals($expected, $results);
    }
}
?>