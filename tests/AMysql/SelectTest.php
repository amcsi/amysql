<?php /* vim: set tabstop=4 expandtab : */
class SelectTest extends PHPUnit_Framework_TestCase {

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

    public function testComplexQuery()
    {
        $select = $this->_amysql->select('t2alias.*');
        $select 
            ->option('DISTINCT')
            ->from(array ('table1', 't2alias' => 'table2'))
            ->from(array ('t3alias' => 'table3'), array ('t3_col1' => 'col1', 't3_col2' => 'col2'))
            ->column (array ('t1_col1' => 'table1.col1'))
            ->columnLiteral('table7, table8, CURRENT_TIMESTAMP AS ctimestamp')
            ->join(
                '',
                array ('t4alias' => 'table4'),
                't4alias.t1_id = table1.id',
                array ('t4lol', 't4lol2aliased' => 't4lol2')
            )
            ->join('left', array ('table5'), 't2alias.colx = table5.coly', array (), true)
            ->join('cross', array ('table6'), 't3alias.colx = table6.coly', array ())
            ->groupBy('t2alias.col1')
            ->groupBy('t2alias.col2', true, true)
            ->groupBy('t2alias.col3', true)
            ->having('1 = 1')
            ->having('2 = 2')
            ->orderBy('t3alias.col1')
            ->orderBy('t3alias.col2', true, true)
            ->orderBy('t3alias.col3', true)
            ->where('3 = :where3')
            ->where('4 = 4')
            ->limit(100)
            ->offset(200)
        ;
        $unboundSql = $select->getUnboundSql();
        $expected = 'SELECT DISTINCT ' .
            '`t2alias`.*, `t3alias`.`col1` AS `t3_col1`, `t3alias`.`col2` AS `t3_col2`, '
            . '`table1`.`col1` AS `t1_col1`, `t4alias`.`t4lol`, ' .
            '`t4alias`.`t4lol2` AS `t4lol2aliased`, ' .
            'table7, table8, CURRENT_TIMESTAMP AS ctimestamp' . "\n" .
            'FROM `table1`, `table2` AS `t2alias`, `table3` AS `t3alias`' . "\n" .
            'LEFT JOIN `table5` ON (t2alias.colx = table5.coly)' . "\n" .
            'JOIN `table4` AS `t4alias` ON (t4alias.t1_id = table1.id)' . "\n" .
            'CROSS JOIN `table6` ON (t3alias.colx = table6.coly)' . "\n" .
            'WHERE 3 = :where3 AND 4 = 4' . "\n" .
            'GROUP BY `t2alias`.`col2` DESC, `t2alias`.`col1`, `t2alias`.`col3` DESC' . "\n" .
            'HAVING 1 = 1 AND 2 = 2' . "\n" .
            'ORDER BY `t3alias`.`col2` DESC, `t3alias`.`col1`, `t3alias`.`col3` DESC' . "\n" .
            'LIMIT 100' . "\n" .
            'OFFSET 200'
        ;
        $this->assertEquals($expected, $unboundSql);
    }

    public function testBlankQuery()
    {
        $select = $this->_amysql->select();
        $unboundSql = $select->getUnboundSql();
        $expected = 'SELECT ';
        $this->assertEquals($expected, $unboundSql);
    }

    public function testSelectExecute()
    {
        $data = array (
            array (
                'string' => 3
            ),
            array (
                'string' => 'blah',
            )
        );
        $this->_amysql->insert($this->tableName, $data);

        $select = $this->_amysql->select();
        $select
            ->column('*')
            ->from($this->tableName)
            ->where('string = :string')
            ->bindValue('string', 'blah');
        $select->execute();
        $rows = $select->fetchAllAssoc();
        $expected = array (
            array (
                'id' => '2',
                'string' => 'blah',
            )
        );
        $this->assertEquals($expected, $rows);
    }
}
?>


