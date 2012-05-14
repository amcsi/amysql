<?php
/**
 * Az AMysql class-hoz tartozó Statement osztály, amelyben egy mysql
 * tranzakciót foglal magában. 
 * Nagyon egyszerű használat:
 * 
 * $amysql = new AMysql($this->_engine->db->conn);
 * try { 
 *     $stmt = $amysql->query('SELECT * FROM cms_content');
 *     while ($row = $stmt->fetchAssoc()) {
 *         ...
 *     }
 * }
 * catch (AMysql_Exception $e) {
 *     echo
 *          "Mysql Hiba!\n" .
 *          "Hibakód: $e->code\n" .
 *          "Üzenet: $e->message\n" .
 *          "SQL query string: $e->query"   
 *      );
 * }  
 *
 * TODO: a query() $this-t adjon vissza.
 *    
 * @author Szerémi Attila
 * @version 0.9
 **/ 
class AMysql_Statement {
    public $amysql;
    public $error;
    public $errno;
    public $result;
    public $results = array ();
    public $query;
    public $affectedRows;
    public $fetchMode = 'assoc';
    public $throwExceptions;
    public $lastException = null;
    public $insertId;

    public $mysqlResource;

    public $beforeSql = '';
    public $prepared = '';
    public $binds = array();

    public function __construct(AMysql $amysql) {
	$this->amysql = $amysql;
	$this->mysqlResource = $amysql->mysqlResource;
	$this->throwExceptions = $this->amysql->throwExceptions;
    }

    public function execute($binds = null) {
	if (is_array($binds)) {
	    $this->binds = $binds;
	}
	$sql = $this->getSql();
	$result = $this->_query($sql);
	return $this;
    }

    /**
     * A különböző metódusok (prepare, select, insert stb.) által épített
     * előkészített sql query string, és a bind-oló metódusok (bindParam,
     * bindValue) által visszaadja az éppeni sql stringet, ami ténylegesen
     * létrejön execute()-nál.
     * @author Szerémi Attila               
     **/         
    public function getSql($prepared = null) {
	if (!$prepared) {
	    $prepared = $this->prepared;
	}
	$sql = $this->prepared;
	$binds =& $this->binds;
	if ($binds) {
	    if (array_key_exists(0, $binds)) {
		$parts = explode('?', $sql);
		$sql = '';
		if (count($parts)-1 == count($binds)) {
		    foreach ($binds as &$bind) {
			$sql .= array_shift($parts);
			$sql .= $this->amysql->escape($bind);
		    };
		    $sql .= array_shift($parts);
		}
		else if (count($parts)-1 < count($binds)) {
		    throw new RuntimeException('More binds than question marks!');
		}
		else {
		    throw new RuntimeException('Fewer binds than question marks!');
		}
	    }
	    else {
		$map = array();
		foreach ($binds as $key => &$bind) {
		    $string = '';
		    for ($i = 0; $i < 5; $i++) {
			$string .= chr(mt_rand(0, 255));
		    }
		    $map[$key] = $string;
		    $sql = str_replace("$key", $string, $sql);
		}
		foreach ($binds as $key => &$bind) {
		    $placeholder = $map[$key];
		    $sql = str_replace($placeholder, $this->amysql->escape($bind), $sql);
		}
	    }
	}
	return $this->beforeSql . $sql;
    }

    public function prepare($sql) {
	$this->beforeSql = '';
	$this->prepared = $sql;
	$this->binds = array();
	return $this;
    }

    public function query($sql, array $binds = array ()) {
	$this->prepare($sql);
	$result = $this->execute($binds);
	return $this;
    }

    protected function _query($sql) {
	$this->query = $sql; 
	$res = $this->mysqlResource;
	if ('mysql link' != get_resource_type($res)) {
	    throw new LogicException('Resource is not a mysql resource.', 0, $sql);
	}
	$result = mysql_query($sql, $this->mysqlResource);
	$this->error = mysql_error($res);
	$this->errno = mysql_errno($res);
	$this->result = $result;
	$this->results[] = $result;
	$this->affectedRows = mysql_affected_rows($res);
	$this->amysql->affectedRows = $this->affectedRows;
	$this->insertId = mysql_insert_id($res);
	$this->amysql->insertId = $this->insertId;
	if (false === $result) {
	    try {
		$this->throwException();
		$this->lastException = null;
	    }
	    catch (AMysql_Exception $e) {
		$this->lastException = $e;
		if ($this->throwExceptions) {
		    throw $e;
		}
		else {
		    trigger_error($e, E_USER_WARNING);
		}
	    }
	}
	return $this;
    }

    public function startTransaction() {
	return $this->_query('START TRANSACTION');
    }

    public function commit() {
	return $this->_query('COMMIT');
    }

    public function rollback() {
	return $this->_query('ROLLBACK');
    }

    /**
     * Fölszabadítja a resultok memóriafoglalásukat.
     **/         
    public function freeResults() {
	foreach ($this->results as $result) {
	    if (is_resource($result)) {
		mysql_free_result($result);
	    }
	}
	return $this;
    }

    public function fetchAll() {
	if ('assoc' == $this->fetchMode) {
	    return $this->fetchAllAssoc();
	}
	else {
	    throw new Exception("Unknown fetch mode: `$this->fetchMode`");
	}
    }

    public function fetch() {
	if ('assoc' == $this->fetchMode) {
	    return $this->fetchAssoc();
	}
	else if ('object' == $this->fetchMode) {
	    return $this->fetchObject();
	}
	else if ('row' == $this->fetchMode) {
	    return $this->fetchRow();
	}
	else {
	    throw new Exception("Unknown fetch mode: `$this->fetchMode`");
	}
    }

    public function fetchAssoc() {
	$result = $this->result;
	return mysql_fetch_assoc($result);
    }

    public function fetchAllAssoc() {
	$result = $this->result;
	$ret = array();
	$numRows = $this->numRows();
	if (0 === $numRows) {
	    return array ();
	}
	else if (false === $numRows) {
	    return false;
	}
	mysql_data_seek($result, 0);
	while (false !== ($row = $this->fetchAssoc())) {
	    $ret[] = $row;
	}
	return $ret;
    }

    public function fetchRow() {
	$result = $this->result;
	return mysql_fetch_row($result);
    }

    public function fetchNum() {
	return $this->fetchRow();
    }

    public function fetchArray() {
	$result = $this->result;
	return mysql_fetch_array($result, MYSQL_BOTH);
    }

    /**
     * Returns the result of the given row and field. A warning is issued
     * if the result on the given row and column does not exist.
     * 
     * @param int $row		(Optional) The row number.
     * @param int $field	(Optional) The field.
     * @return mixed
     */
    public function result($row = 0, $field = 0) {
	$result = $this->result;
	return mysql_result($result, $row, $field);
    }

    /**
     * Returns an array of scalar values, where the keys are the values
     * of the key column specified, and the values are the values of the
     * value column specified.
     * 
     * @param mixed $keyColumn	    (Optional) column number or string for
     *				    the keys.
     *				    Default: 0.
     * @param mixed $valueColumn    (Optional) column number or string for
     *				    the values.
     *				    Default: 1.
     * @access public
     * @return array
     */
    public function pairUp($keyColumn = 0, $valueColumn = 1) {
	$ret = array ();
	while ($row = $stmt->fetchArray()) {
	    $key = $row[$keyColumn];
	    $ret[$key] = $row[$valueColumn];
	}
	return $ret;
    }

    /**
     * Returns all values of a specified column as an array.
     * 
     * @param mixed $column	    (Optional) column number or string for
     *				    the values.
     *				    Default: 0.
     * @access public
     * @return array
     */
    public function fetchAllColumn($column = 0) {
	$ret = array ();
	while ($row = $stmt->fetchArray()) {
	    $ret[] = $row[$column];
	}
	return $ret;
    }

    /**
     * Returns the result of the given row and field, or null if the
     * row doesn't exist
     * 
     * 
     * @param int $row		(Optional) The row number.
     * @param int $field	(Optional) The field.
     * @return mixed
     */
    public function resultNull($row = 0, $field = 0) {
	$result = $this->result;
	return $row < $this->numRows() ? mysql_result($result, $row, $field) :
	    null;
    }

    /**
     * Returns the result of the given row and field as an integer.
     * 0, if that result doesn't exist.
     * 
     * @param int $row		(Optional) The row number.
     * @param int $field	(Optional) The field.
     * @return integer
     */
    public function resultInt($row = 0, $field = 0) {
	return (int) $this->resultNull($row, $field);
    }

    public function fetchObject() {
	$result = $this->result;
	return mysql_fetch_object($result);
    }

    public function affectedRows() {
	return $this->affectedRows;
    }

    public function numRows() {
	return mysql_num_rows($this->result);
    }

    /**
     * Do not use this method! It requires a lot of revision, and is subject
     * to change a lot.
     * 
     * Egy SELECT utasítást kezdeményez úgy, hogy az épülendő sql utasítás
     * SELECT-tel kezdődjön, és megjelölje azokat az oszlopneveket, amiket
     * átadunk. 
     * - Lehet egy tömbként is jelölni, ahol az érték a kijelölendő
     * táblanév, és opcionálisan, ha a kulcs az nem szám, akkor az az AS
     * szerinti átnevezés.
     * - Lehet dinamikus paramétermennyiségként átadni ugyanazt, mint az
     * efölöttinél, csak nem lehet AS-esen átnevezni a megjelölt táblaneveket.
     * @return this                                   
     **/         
    public function select() {
	$sql = 'SELECT ';
	$arg0 = func_get_arg(0);
	if (is_array($arg0)) {
	    $columnsString = $this->_getColumnsStringByArray($arg0);
	}
	else {
	    $args = func_get_args();
	    // ha csak egy paraméter lett átadva, akkor literálisan vesszük
	    // azt a kiválasztást
	    if (1 == count($args)) {
		$columnsString = $args[0];
	    }
	    else {
		$columnsString = $this->_getColumnsStringByArray($args);
	    }
	}
	$sql .= $columnsString;
	$this->prepared = $sql;
	return $this;
    }

    protected function _getTablesStringByArray($tableArray) {
	$tables = array ();
	foreach ($tableArray as $alias => $tableName) {
	    $table = $this->escapeIdentifier($tableName, $alias);
	    $tables[] = $table;
	}
	return implode(', ', $tables);
    }

    public function throwException() {
	throw new AMysql_Exception($this->error, $this->errno, $this->query);
    }

    protected function _getColumnsStringByArray($columnArray) {
	$columns = array ();
	foreach ($columnArray as $alias => $columnName) {
	    $column = $this->escapeIdentifier($columnName, $alias);
	    $columns[] = $column;
	}
	return implode(', ', $columns);
    }

    /**
     * A FROM részbe tölt táblaneveket.
     **/         
    public function from() {
	$arg0 = func_get_arg(0);
	if (is_array($arg0)) {
	    $tablesString = $this->_getTablesStringByArray($arg0);
	}
	else {
	    $args = func_get_args();
	    // ha csak egy paraméter lett átadva, akkor literálisan vesszük
	    // azt az 1 stringet
	    if (1 == count($args)) {
		$tablesString = $args[0];
	    }
	    else {
		$tablesString = $this->_getTablesStringByArray($args);
	    }
	}
	$sql = ' FROM ' . $tablesString . ' ';
	$this->prepared .= $sql;
	return $this;
    }

    /**
     * A WHERE részbe tölt adatot
     **/         
    public function where($where) {
	$sql = ' WHERE ';
	if (is_string($where) or ($where instanceof AMysql_Expr)) {
	    $sql .= $where;
	}
	$this->prepared .= $sql;
	return $this;
    }

    public function leftJoin($tableName, $as = null, $on = null) {
	$sql = ' LEFT JOIN ' . $this->escapeIdentifier($tableName, $as);
	$this->prepared .= $sql;
	if ($on) {
	    $this->on($on);
	}

	return $this;
    }

    public function on($on) {
	$sql = ' ON ' . $on;
	$this->prepared .= $sql;
	return $this;
    }

    public function escapeIdentifierSimple($columnName) {
	return AMysql::escapeIdentifierSimple($columnName);
    }

    /**
     * Egy oszlopnevet escape-el.
     * @param string $columnName Az oszlop neve.
     * @param string $as (opcionális) az oszlop átnevezése.          	
     **/
    public function escapeIdentifier($columnName, $as = null) {
	return AMysql::escapeIdentifier($columnName, $as);
    }

    public function appendPrepare($sql) {
	$this->prepared .= $sql;
	return $this;
    }

    /**
     * Bindol egy értéket a preparált sql stringhez.
     * @param mixed $key Ha string, akkor az adott stringet cseréli majd ki
     * az értékre, különben, ha integer, akkor          
     **/         
    public function bindValue($key, $val) {
	$this->binds[$key] = $val;
	return $this;
    }

    public function bindParam($key, &$val) {
	$this->binds[$key] = &$val;
	return $this;
    }

    /**
     * UPDATE-et végez egy táblanév, egy adat tömb és egy WHERE szöveg alapján.
     * @param string $tableName A tábla neve escape-elés nélkül.
     * @param array $data Az adat tömb. A tömb egy asszociatív tömb, ahol
     *  minden kulcs egy oszlopnevet reprezntál, és az érték pedig az az
     *  érték, amire változna az érték annál az oszlopnál.
     *  FIGYELEM: Itt a tableName CSAK egy string lehet!     
     * @param string $where A WHERE string-je.
     * @return integer mysql_affected_rows()
     * @throws AMysql_Exception                
     **/               
    public function update($tableName, array $data, $where) {
	if (!$data) {
	    return false;
	}
	$sets = array ();
	foreach ($data as $columnName => $value) {
	    $columnName = $this->escapeIdentifierSimple($columnName);
	    $sets[] = "$columnName = " . $this->amysql->escape($value);
	}
	$setsString = join(', ', $sets);

	/**
	 * Ezt beforeSql-el kell megoldani, különben az értékekben lévő
	 * kérdőjelek bezavarnak.         
	 **/		         
	$beforeSql = "UPDATE `$tableName` SET $setsString WHERE ";
	$this->prepare($where);
	$this->beforeSql = $beforeSql;

	return $this;
    }

    /**
     * INSERT-et végez egy táblanév és egy adat tömb alapján. Egyzserre
     * több sort is lehet beszúrni.
     * @param string $tableName A tábla neve escape-elés nélkül.
     * @param array $data Az adat tömb. Az alábbi formák között lehet:
     *  - Indexelt tömb, ahol minden elem egy asszociatív tömb, ahol minden
     *      kulcs egy oszlopnév, az érték pedig a mező értéke.
     *  - Asszociatív tömb, ahol minden kulcs egy táblanév, és minden érték
     *      vagy a mező értéke, vagy egy tömb a különböző sorokhoz tartozó
     *      mező értékkel.
     * @return integer mysql_affected_rows()
     * @throws AMysql_Exception                              
     **/     
    public function insert($tableName, array $data) {
	$cols = array ();
	$vals = array();
	$i = 0;
	if (!$data) {
	    return false;
	}
	if (empty($data[0])) {
	    foreach ($data as $columnName => $values) {
		$cols[] = $this->escapeIdentifierSimple($columnName);
		if (!is_array($values)) {
		    $values = array($values);
		}
		foreach ($values as $key => $value) {
		    if (!isset($vals[$key])) {
			$vals[$key] = array ();
		    }
		    $vals[$key][] = $this->amysql->escape($value);
		}
	    }
	}
	else {
	    $akeys = array_keys($data[0]);
	    $cols = array ();
	    foreach ($akeys as $col) {
		$cols[] = $this->escapeIdentifierSimple($col);
	    }

	    foreach ($data as $row) {
		$vals[$i] = array ();
		$row2 = array_fill_keys($akeys, null);
		foreach ($row as $columnName => $value) {
		    $row2[$columnName] = $this->amysql->escape($value);

		}
		$vals[$i] = $row2;
		$i++;
	    }
	}
	$columnsString = join(', ', $cols);
	$rowValueStrings = array();
	foreach ($vals as $rowValues) {
	    $rowValueStrings[] = join(', ', $rowValues);
	}
	$valuesString = join('), (', $rowValueStrings);
	$sql = "INSERT INTO `$tableName` ($columnsString) VALUES ($valuesString)";
	$this->prepare($sql);
	return $this;
    }

    /**
     * Törlést kezdeményez egy táblán. Opcionálisan a WHERE clause is megadható.
     * @param string $tableName A tábla neve.
     * @param string $where (Opcionális) A WHERE clause.          
     **/         
    public function delete($tableName, $where = null) {
	$sql = "DELETE FROM `$tableName`";
	$this->prepare($sql);
	if ($where) {
	    $this->where($where);
	}
	return $this;
    }

    public function orderBy($orderBy, $order = 'ASC') {
	$this->prepared .= ' ORDER BY ' . $orderBy . ' ' . $order;
	return $this;
    }

    /**
     * LIMIT-et fűz hozzá az előkészített sql stringhez.
     * @param int $limit A max sorok száma
     * @param int $offset (Opcionális) OFFSET     
     **/         
    public function limit($limit = null, $offset = null) {
	if ($limit) {
	    $this->prepared .= " LIMIT $limit";
	}
	if ($offset) {
	    $this->prepared .= "OFFSET $offset";
	}
	return $this;
    }

    /**
     * Visszaadja az új insertált id-t
     * @return int     
     **/	     
    public function insertId() {
	$ret = mysql_insert_id($this->mysqlResource);
	return $ret;
    }

    /**
     * Ha nem marad példány ebből az objektumból, szabadítsa fel a result-ot,
     * ha van.     
     **/         
    public function __destruct() {
	$this->freeResults();
    }
}
?>
