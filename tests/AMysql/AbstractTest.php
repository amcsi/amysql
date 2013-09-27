<?php /* vim: set tabstop=8 expandtab : */
class AbstractTest extends PHPUnit_Framework_TestCase {

    protected $_conn;
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
        try {
            $this->_amysql->query("DROP TABLE `$this->tableName`");
        }
        catch (Exception $e) {

        }
	$this->_amysql = null;
    }

    public function testConnect() {
        $this->tearDown();
        $this->_amysql = new AMysql;
        $this->_amysql->setConnDetails(array (
            'host' => AMYSQL_TEST_HOST,
            'username' =>AMYSQL_TEST_USER,
            'password' => AMYSQL_TEST_PASS,
            'db' => AMYSQL_TEST_DB,
        ));
        $this->_amysql->connect();
        $this->createTable();
    }

    public function testConnectThenToDb() {
        $this->tearDown();
        $this->_amysql = new AMysql;
        $this->_amysql->setConnDetails(array (
            'host' => AMYSQL_TEST_HOST,
            'username' =>AMYSQL_TEST_USER,
            'password' => AMYSQL_TEST_PASS,
        ));
        $this->_amysql->connect();
        $this->_amysql->selectDb(AMYSQL_TEST_DB);
        $this->createTable();
    }

    public function testLazyConnect() {
        $this->tearDown();
        $this->_amysql = new AMysql;
        $this->_amysql->setConnDetails(array (
            'host' => AMYSQL_TEST_HOST,
            'username' =>AMYSQL_TEST_USER,
            'password' => AMYSQL_TEST_PASS,
            'db' => AMYSQL_TEST_DB,
        ));
        $this->createTable();
    }

    public function testConnectRightPort() {
        $this->tearDown();
        $this->_amysql = new AMysql;
        $this->_amysql->setConnDetails(array (
            'host' => AMYSQL_TEST_HOST,
            'username' =>AMYSQL_TEST_USER,
            'password' => AMYSQL_TEST_PASS,
            'port' => 3306,
            'db' => AMYSQL_TEST_DB,
        ));
        $this->createTable();
    }

    public function testForceMysql() {
        $this->tearDown();
        $this->_amysql = new AMysql;
        $this->_amysql->setConnDetails(array (
            'host' => AMYSQL_TEST_HOST,
            'username' =>AMYSQL_TEST_USER,
            'password' => AMYSQL_TEST_PASS,
            'port' => 3306,
            'db' => AMYSQL_TEST_DB,
        ));
        AMysql_Abstract::$useMysqli = false;
        $this->createTable();
        $this->assertEquals('resource', gettype($this->_amysql->link));
        AMysql_Abstract::$useMysqli = true;
    }

    public function testInsertSingleRow() {
	$data = array (
	    array (
		'string' => 3
	    ),
	);
	$this->_amysql->insert($this->tableName, $data);
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName");
	$results = $stmt->fetchAllAssoc();
	$expected = array (
	    array (
		'id' => '1',
		'string' => '3'
	    ),
	);
	$this->assertEquals($expected, $results);
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
	$this->_amysql->update($this->tableName, $data, 'id = ?', '1');
	$stmt = $this->_amysql->query("SELECT * FROM $this->tableName WHERE
	    id = ?", 1);
	$result = $stmt->fetchAssoc();
	$this->assertEquals('foo', $result['string']);
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

	$this->assertEquals(2, $this->_amysql->multipleAffectedRows);

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

	$this->_amysql->updateMultipleByKey($this->tableName, $data, 'id');
	$this->assertEquals(0, $this->_amysql->multipleAffectedRows);
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

	$this->assertEquals(2, $this->_amysql->multipleAffectedRows);
        $data[1]['string'] = 'bar2';
	$success =
	    $this->_amysql->updateMultipleByData($this->tableName, $data, 'id',
	    true);
	$this->assertEquals(1, $this->_amysql->multipleAffectedRows);
    }

    public function testTranspose() {
        $input = array (
            3 => array (
                'col1' => 'bla',
                'col2' => 'yo'
            ),
            9 => array (
                'col1' => 'ney',
                'col2' => 'lol'
            )
        );

        $expected = array (
            'col1' => array (
                3 => 'bla',
                9 => 'ney'
            ),
            'col2' => array (
                3 => 'yo',
                9 => 'lol'
            )
        );

        $result = AMysql_Abstract::transpose($input);
        $this->assertEquals($expected, $result);
    }

    public function testReplace() {
	$data = array (
	    'string' => array (
		3, 'blah'
	    )
	);
        $this->_amysql->insert($this->tableName, $data);
        $data = array (
            array (
                'id' => 2,
                'string' => 'replaced1'
            ),
            array (
                'id' => 4,
                'string' => 'replaced2'
            ),
        );
        $this->_amysql->replace($this->tableName, $data);

        $result = $this->_amysql->query("SELECT * FROM $this->tableName")
            ->fetchAllAssoc();

        $expected = array (
            array (
                'id' => '1',
                'string' => '3'
            ),
            array (
                'id' => '2',
                'string' => 'replaced1'
            ),
            array (
                'id' => '4',
                'string' => 'replaced2'
            )
        );
        $this->assertEquals($expected, $result);
    }

    public function testNoProfiling () {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
	$this->_amysql->insert($this->tableName, $data);
	$this->assertSame(0.0, $this->_amysql->totalTime);
    }

    public function testProfiling () {
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
        $this->assertInternalType('float', $this->_amysql->totalTime);
	$this->assertGreaterThan(0.0, $this->_amysql->totalTime);
    }

    public function testProfiling2 () {
	$data = array (
	    array (
		'string' => 3
	    ),
	    array (
		'string' => 'blah',
	    )
	);
        $this->_amysql->profileQueries = true;
        $this->_amysql->includeBacktrace = true;

	$this->_amysql->insert($this->tableName, $data);
        $totalTimeSoFar = $this->_amysql->totalTime;
        $this->assertInternalType('float', $this->_amysql->totalTime);
        $this->assertGreaterThan(0.0, $this->_amysql->totalTime);

	$this->_amysql->insert($this->tableName, $data);
        $this->assertInternalType('float', $this->_amysql->totalTime);
	$this->assertGreaterThan($totalTimeSoFar, $this->_amysql->totalTime);
    }

    /**
     * 
     **/
    public function repeat100() {
	return array_fill(0, 100, array ());
    }
}
?>

