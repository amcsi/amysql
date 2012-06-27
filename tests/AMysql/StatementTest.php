<?php
class StatementTest extends PHPUnit_Framework_TestCase {

    protected $_conn;
    protected $_amysql;

    public function setUp() {
	$conn = mysql_connect(AMYSQL_TEST_HOST, AMYSQL_TEST_USER, 
	    AMYSQL_TEST_PASS);
	$this->_conn = $conn;
	$this->_amysql = new AMysql($conn);
    }

    public function tearDown() {
	mysql_close($this->_conn);
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
}
?>
