<?php /* vim: set tabstop=8 expandtab : */
class StatementTest extends PHPUnit_Framework_TestCase {

    protected $_amysql;

    public $tableName = 'abstracttest';

    public function setUp() {
        if ('mysqli' == SQL_DRIVER) {
            $this->_amysql = new AMysql(
                AMYSQL_TEST_HOST, AMYSQL_TEST_USER, AMYSQL_TEST_PASS);
        }
        else if ('mysql' == SQL_DRIVER) {
            $conn = mysql_connect(AMYSQL_TEST_HOST, AMYSQL_TEST_USER,
                AMYSQL_TEST_PASS);
            $this->_amysql = new AMysql($conn);
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
        $this->_amysql->selectDb(AMYSQL_TEST_DB);

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

    public function tearDown() {
	$this->_amysql->query("DROP TABLE `$this->tableName`");
	$this->_amysql = null;
    }

    public function testDoubleExecute() {
        $sql = "SELECT * FROM $this->tableName";
        $stmt = $this->_amysql->prepare($sql);
        $stmt->execute();
	$this->setExpectedException('LogicException');
        $stmt->execute();
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

    public function testNamedBinds4() {
	$sql = ":foo :bar";
	$binds = array (
	    'foo' => ':bar',
	    'bar' => 'cheese'
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "':bar' 'cheese'";
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

    public function testAutoColon4() {
	$sql = ":foo\n:bar :a:b :c :d";
	$binds = array (
	    'foo' => 's1',
	    'bar' => 's2',
	    'a' => 's3',
	    'b' => 's4',
	    'c' => 's5',
	    'd' => ''
	);
	$stmt = $this->_amysql->prepare($sql);
	$expected = "'s1'\n's2' 's3''s4' 's5' ''";
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

    public function testFetchObject2() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$result = $stmt->fetchObject();
	$this->assertInstanceOf('stdClass', $result);
	$this->assertEquals(3, $result->string);
	$result = $stmt->fetchObject('ArrayObject', 
	    array (array (),
		ArrayObject::ARRAY_AS_PROPS | ArrayObject::STD_PROP_LIST
	    )
	);
	$this->assertInstanceOf('ArrayObject', $result);
	$this->assertEquals('blah', $result->string);
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

    public function testFetchAllDefault() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAll();
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

    public function testBindParam() {
	$sql = " :a ";
	$bind = 1;
	$stmt = $this->_amysql->prepare($sql);
	$stmt->bindParam('a', $bind);
	$bind = 2;
	$resultSql = $stmt->getSql();
	$this->assertEquals(' 2 ', $resultSql);
    }

    public function testBindValue() {
	$sql = " :a ";
	$bind = 1;
	$stmt = $this->_amysql->prepare($sql);
	$stmt->bindValue('a', $bind);
	$bind = 2;
	$resultSql = $stmt->getSql();
	$this->assertEquals(' 1 ', $resultSql);
    }

    public function testSetFetchModeExtraArgs() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$stmt->setFetchMode(AMysql_Abstract::FETCH_OBJECT, 'ArrayObject',
	    array (array (),
		ArrayObject::ARRAY_AS_PROPS | ArrayObject::STD_PROP_LIST
	    )
	);
	$result = $stmt->fetch();
	$this->assertInstanceOf('ArrayObject', $result);
	$this->assertEquals('3', $result->string);
    }

    /**
     * 
     **/
    public function repeat20() {
	return array_fill(0, 20, array ());
    }

    public function testCount() {
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
	$this->assertEquals(2, count($stmt));
    }

    public function testCountNonSelect() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->lastStatement;
	$this->setExpectedException('LogicException');
	count($stmt);
    }

    public function testFetchAllColumns() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->lastStatement;
        $sql = "SELECT * FROM $this->tableName";
        $stmt = $this->_amysql->query($sql);
        $result = $stmt->fetchAllColumns();

        $expected = array (
            'id' => array ('1', '2'),
            'string' => array ('3', 'blah')
        );
        $this->assertEquals($expected, $result);
    }

    public function testFetchAllColumnsEmpty() {
        $sql = "SELECT * FROM $this->tableName";
        $stmt = $this->_amysql->query($sql);
        $result = $stmt->fetchAllColumns();
        $this->assertEquals(array(), $result);
    }

    public function testFetchAllColumnsNamed() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->lastStatement;
        $sql = "SELECT * FROM $this->tableName";
        $stmt = $this->_amysql->query($sql);
        $result = $stmt->fetchAllColumns(1);

        $expected = array (
            'id' => array ('3' => '1', 'blah' => '2'),
            'string' => array ('3' => '3', 'blah' => 'blah')
        );
        $this->assertEquals($expected, $result);
    }

    public function testNoProfiling() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
        $this->assertNull($this->_amysql->lastStatement->queryTime);
    }

    public function testProfiling() {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
        $this->_amysql->profileQueries = true;
        $this->_amysql->insert($this->tableName, $data);
        $this->assertInternalType('float', 
            $this->_amysql->lastStatement->queryTime
        );
        $this->assertGreaterThan(0.0, $this->_amysql->lastStatement->queryTime);
        $this->assertSame($this->_amysql->totalTime,
            $this->_amysql->lastStatement->queryTime);
    }
}
?>
