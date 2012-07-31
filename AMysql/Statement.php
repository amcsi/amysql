<?php
/**
 * The statement class belonging to the AMysql_Abstract class, where mysql
 * queries are built and handled.
 * Most methods here are chainable, and many common AMysql_Abstract methods
 * return a new instance of this class.
 * A simple use example:
 * 
 * $amysql = new AMysql($conn);
 * try { 
 *     $stmt = $amysql->query('SELECT * FROM cms_content');
 *     while ($row = $stmt->fetchAssoc()) {
 *         ...
 *     }
 * }
 * catch (AMysql_Exception $e) {
 *     echo $e->getDetails();
 * }  
 *
 * @author Szerémi Attila
 * @version 0.9.2.3
 **/ 
class AMysql_Statement {
    public $amysql;
    public $error;
    public $errno;
    public $result;
    public $results = array ();
    public $query;
    public $affectedRows;
    public $throwExceptions;
    public $lastException = null;
    public $insertId;

    public $link;

    protected $_fetchMode;
    protected $_fetchModeExtraArgs = array ();

    public $beforeSql = '';
    public $prepared = '';
    public $binds = array();

    protected $_replacements;

    public function __construct(AMysql_Abstract $amysql) {
	$amysql->lastStatement = $this;
	$this->amysql = $amysql;
	$this->link = $amysql->link;
	$this->throwExceptions = $this->amysql->throwExceptions;
	$this->setFetchMode($amysql->getFetchMode());
    }

    public function setFetchMode($fetchMode/* [, extras [, extras...]]*/) {
	static $fetchModes = array (
	    AMysql_Abstract::FETCH_ASSOC, AMysql_Abstract::FETCH_OBJECT,
	    AMysql_Abstract::FETCH_ARRAY, AMysql_Abstract::FETCH_ROW
	);
	$args = func_get_args();
	$extraArgs = array_slice($args, 1);
	if (in_array($fetchMode, $fetchModes)) {
	    $this->_fetchMode = $fetchMode;
	    $this->_fetchModeExtraArgs = $extraArgs;
	}
	else {
	    throw new Exception("Unknown fetch mode: `$fetchMode`");
	}
    }

    /**
     * Executes a prepared statement, optionally accepting binds for replacing
     * placeholders.
     * 
     * @param array $binds	(Optional) The binds for the placeholders. This
     *				library supports names and unnames placeholders.
     *				To use unnamed placeholders, use question marks
     *				(?) as placeholders. A bind's key should be the
     *				index of the question mark.
     *				To use named placeholders, the placeholders
     *				must start with a non-alphanumeric, non-128+
     *				character. If the key starts with an
     *				alphanumeric or 128+ character, the placeholder
     *				that is searched to be replaced will be the
     *				key prepended by a colon (:). Here are examples
     *				for what keys will replace what placeholders
     *				for the value:
     *
     *				:key: => :key:
     *				:key => :key
     *				key => :key
     *				key: => :key:
     *				!key => !key
     *				élet => :élet
     *
     *				All values are escaped and are automatically
     *				surrounded by apostrophes if needed. Do NOT
     *				add apostrophes around the string values as
     *				encapsulating for a mysql string.
     *				@see AMysql_Abstract::escape()
     *
     * @return $this
     */
    public function execute($binds = null) {
	if (is_array($binds)) {
	    $this->binds = $binds;
	}
	else if (!is_null($binds)) {
	    throw new InvalidArgumentException("\$binds should be an array.");
	}
	$sql = $this->getSql();
	$result = $this->_query($sql);
	return $this;
    }

    /**
     * The sql string built up by the different preparing methods (prepare,
     * select, insert etc.) is returned, having the placeholders being
     * replaced by their binded values. You can debug what the final SQL
     * string would be by calling this method.
     *
     * @param string $prepared	(Optional) Use this prepared string
     *					instead of the one set.
     *
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
		$keysQuoted = array ();
		$replacements = array ();
		foreach ($binds as $key => &$bind) {
		    if (127 < ord($key[0]) || preg_match('/^\w$/', $key[0])) {
			$key = ':' . $key;
		    }
		    $keyQuoted = preg_quote($key, '/');
		    $keysQuoted[] = $keyQuoted;
		    $replacements[$key] = $this->amysql->escape($bind);
		}
		$keysOr = join('|', $keysQuoted);
		$pattern =
		    "/($keysOr)(?![\w\x80-\xff])/m";
		$this->_replacements = $replacements;

		$sql = preg_replace_callback($pattern,
		    array ($this, '_replaceCallback'),
		    $sql
		);
	    }
	}
	return $this->beforeSql . $sql;
    }

    protected function _replaceCallback($match) {
	$key = $match[0];
	$replacement = array_key_exists($key, $this->_replacements) ?
	    $this->_replacements[$key] :
	    $key;
	return $replacement;
    }

    /**
     * Prepares an SQL string for binding and execution. Use of this
     * method is not recommended externally. Use the AMysql class's
     * prepare method instead which returns a new AMysql_Statement instance.
     * 
     * @param string $sql The SQL string to prepare.
     * @return $this
     */
    public function prepare($sql) {
	$this->beforeSql = '';
	$this->prepared = $sql;
	$this->binds = array();
	return $this;
    }

    public function query($sql, array $binds = array ()) {
	if (!is_null($binds) && !is_array($binds)) {
	    throw new InvalidArgumentException("\$binds should be an array.");
	}
	$this->prepare($sql);
	$result = $this->execute($binds);
	return $this;
    }

    protected function _query($sql) {
	$this->query = $sql; 
	$res = $this->link;
	if ('mysql link' != get_resource_type($res)) {
	    throw new LogicException(
		'Resource is not a mysql resource.', 0, $sql
	    );
	}
	$result = mysql_query($sql, $this->link);
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

    /**
     * Executes START TRANSACTION (Not supported in all db formats).
     *
     * @return $this
     */
    public function startTransaction() {
	return $this->_query('START TRANSACTION');
    }

    /**
     * Executes COMMIT (Not supported in all db formats).
     *
     * @return $this
     */
    public function commit() {
	return $this->_query('COMMIT');
    }

    /**
     * Executes ROLLBACK (Not supported in all db formats).
     *
     * @return $this
     */
    public function rollback() {
	return $this->_query('ROLLBACK');
    }

    /**
     * Frees the mysql result resource.
     *
     * @return $this
     **/         
    public function freeResults() {
	foreach ($this->results as $result) {
	    if (is_resource($result)) {
		mysql_free_result($result);
	    }
	}
	return $this;
    }

    /**
     * Returns all the results with each row in the format of that specified
     * by the fetch mode.
     * 
     * @see $this->setFetchMode()
     *
     * @return array
     **/         
    public function fetchAll() {
	$ret = array ();
	if (AMysql_Abstract::FETCH_ASSOC == $this->_fetchMode) {
	    $methodName = 'fetchAssoc';
	}
	else if (AMysql_Abstract::FETCH_OBJECT == $this->_fetchMode) {
	    $methodName = 'fetchObject';
	}
	else if (AMysql_Abstract::FETCH_ARRAY == $this->_fetchMode) {
	    $methodName = 'fetchArray';
	}
	else if (AMysql_Abstract::FETCH_ROW == $this->_fetchMode) {
	    $methodName = 'fetchRow';
	}
	else {
	    throw new Exception("Unknown fetch mode: `$this->_fetchMode`");
	}
	$ret = array();
	$numRows = $this->numRows();
	if (0 === $numRows) {
	    return array ();
	}
	else if (false === $numRows) {
	    return false;
	}
	$extraArgs = $this->fetchModeExtraArgs;
	$method = array ($this, $methodName);
	mysql_data_seek($result, 0);
	while (false !== ($row = call_user_func_array($method, $extraArgs))) {
	    $ret[] = $row;
	}
	return $ret;
    }

    /**
     * Returns one row in the format specified by the fetch mode.
     *
     * @see $this->setFetchMode()
     * 
     * @return array
     */
    public function fetch() {
	if ('assoc' == $this->_fetchMode) {
	    return $this->fetchAssoc();
	}
	else if ('object' == $this->_fetchMode) {
	    return $this->fetchObject();
	}
	else if ('row' == $this->_fetchMode) {
	    return $this->fetchRow();
	}
	else if (AMysql_Abstract::FETCH_ARRAY == $this->_fetchMode) {
	    return $this->fetchArray();
	}
	else {
	    throw new Exception("Unknown fetch mode: `$this->_fetchMode`");
	}
    }

    /**
     * Fetches one row with column names as the keys.
     * 
     * @return array|false
     */
    public function fetchAssoc() {
	$result = $this->result;
	return mysql_fetch_assoc($result);
    }

    /**
     * Fetches all rows and returns them as an array of associative arrays. The
     * outer array is numerically indexed by default, but can be indexed by
     * a field value.
     * 
     * @param integer|string|boolean $keyColumn	(Optional) If a string, the cell
     *					of the given field will be the key for
     *					its row, so the result will not be an array
     *					numerically indexed from 0 in order. This
     *					value can also be an integer, specifying
     *					the index of the field with the key.
     * @access public
     * @return <Associative result array>[]
     */
    public function fetchAllAssoc($keyColumn = false) {
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
	if (false === $keyColumn) {
	    while (false !== ($row = $this->fetchAssoc())) {
		$ret[] = $row;
	    }
	}
	else {
	    $row = $this->fetchAssoc();
	    /**
	     * Since we are using associative keys here, if we gave the key as an
	     * int, we have to find out the associative version of the key.
	     **/
	    if (is_int($keyColumn)) {
		$cnt = count($keyColumn);
		reset($row);
		for ($i = 0; $i < $cnt; $i++) {
		    next($row);
		}
		$keyColumn = key($row);
		reset($row);
	    }
	    $ret[$row[$keyColumn]] = $row;
	    while ($row = $this->fetchAssoc()) {
		$ret[$row[$keyColumn]] = $row;
	    }
	}
	return $ret;
    }

    /**
     * Fetches the next row with column names as numeric indices.
     * 
     * @return array
     */
    public function fetchRow() {
	$result = $this->result;
	return mysql_fetch_row($result);
    }

    /**
     * Alias of $this->fetchRow()
     * 
     * @return array
     */
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
     * Returns the result of the given row and field, or the given value
     * if the row doesn't exist
     * 
     * 
     * @param mixed $default	The value to return if the field is not found.
     * @param int $row		(Optional) The row number.
     * @param int $field	(Optional) The field.
     * @return mixed
     */
    public function resultDefault($default, $row = 0, $field = 0) {
	$result = $this->result;
	return $row < $this->numRows() ? mysql_result($result, $row, $field) :
	    $default;
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
	return $this->resultDefault(null, $row, $field);
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
	while ($row = $this->fetchArray()) {
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
	while ($row = $this->fetchArray()) {
	    $ret[] = $row[$column];
	}
	return $ret;
    }

    /**
     * Fetches the next row as an object.
     * 
     * @return object
     */
    public function fetchObject(
	$className = 'stdClass', array $params = null
    ) {
	$result = $this->result;
	if ($params) {
	    return mysql_fetch_object($result, $className, $params);
	}
	else {
	    return mysql_fetch_object($result, $className);
	}
    }

    /**
     * Returns the number of affected rows.
     * 
     * @return integer
     */
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
     * Begins a SELECT statement. To this method you can pass an array of
     * column names, or column names as variable arguments. The column names
     * will be listed after SELECT, so the prepared statement will look like:
     *	SELECT `column1`, `column2`, `column3`
     * In the case that those three columns were passed to this method
     * (without the backticks).
     * If you pass an array of columns instead of columns as dynamic parameters,
     * and the array's keys are not numeric, the given column names will be
     * aliased with "AS" to the key.
     *
     * @return this                                   
     **/         
    public function select(/* $params... */) {
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

    /**
     * Throws an AMysqlException for the last mysql error.
     * 
     * @throws AMysql_Exception
     */
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
     * Append FROM and a list of table names to the prepared sql string.
     * This method accepts table names as an array or as dynamic arguments,
     * and in case of the former, the table names will be aliased with "AS"
     * to their key if it is not numeric.
     *
     * This method is experimental, not supported yet, and use of it is
     * discouraged, because it may change a lot in the future.
     *
     * @return $this
     **/         
    public function from(/* $params... */) {
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
     * Appends WHERE and a given string to the prepared sql string.
     *
     * This method is experimental, not supported yet, and use of it is
     * discouraged, because it may change a lot in the future.
     *
     * @param string $where		The string that goes after WHERE
     *
     * @return $this;
     **/         
    public function where($where) {
	$sql = ' WHERE ';
	if (is_string($where) or ($where instanceof AMysql_Expr)) {
	    $sql .= $where;
	}
	$this->prepared .= $sql;
	return $this;
    }

    /**
     * Appends LEFT JOIN and a given table name, an optional AS and an
     * optional ON to the prepared sql string.
     *
     * This method is experimental, not supported yet, and use of it is
     * discouraged, because it may change a lot in the future.
     *
     * @param string $tableName		The table name to left join.
     * @param string $as		(Optional) What to alias the table
     * @param string $on		(Optional) The ON condition.
     *
     * @return $this;
     **/         
    public function leftJoin($tableName, $as = null, $on = null) {
	$sql = ' LEFT JOIN ' . $this->escapeIdentifier($tableName, $as);
	$this->prepared .= $sql;
	if ($on) {
	    $this->on($on);
	}

	return $this;
    }

    /**
     * Appends ON and a given string to the prepared sql string.
     *
     * This method is experimental, not supported yet, and use of it is
     * discouraged, because it may change a lot in the future.
     *
     * @param string $on		The ON condition.
     *
     * @return $this;
     **/
    public function on($on) {
	$sql = ' ON ' . $on;
	$this->prepared .= $sql;
	return $this;
    }

    public function escapeIdentifierSimple($columnName) {
	return AMysql_Abstract::escapeIdentifierSimple($columnName);
    }

    /**
     * @see AMysql_Abstract::escapeIdentifier
     **/
    public function escapeIdentifier($columnName, $as = null) {
	return AMysql_Abstract::escapeIdentifier($columnName, $as);
    }

    /**
     * Appends a string to the prepared string.
     *
     * @param string $sql The string to append.
     * @return $this
     **/
    public function appendPrepare($sql) {
	$this->prepared .= $sql;
	return $this;
    }

    /**
     * Binds a value to the sql string.
     *
     * @param mixed $key	    If an integer, then the given index
     *				    question mark will be replaced.
     *				    If a string, then then, if it starts
     *				    with an alphanumberic or 128+ ascii
     *				    character, then a colon plus the string
     *				    given will be replaced, otherwise the
     *				    given string literally will be replaced.
     *				    Example: if the string is
     *				    foo
     *				    then :foo will be replaced.
     *				    if the string is
     *				    !foo
     *				    then !foo will be replaced
     *				    if the string is
     *				    :foo:
     *				    then :foo: will be replaced.
     *				    Note: don't worry about keys that have a
     *				    common beginning. If foo and fool are set,
     *				    :fool will not be replaced with the value
     *				    given for foo.
     *
     * @param mixed $val	    Bind this value for replacing the mark
     *				    defined by $key. The value is escaped
     *				    depeding on its type, apostrophes included,
     *				    so do not add apostrophes in your
     *				    prepared sqls.
     *
     * @return $this
     **/         
    public function bindValue($key, $val) {
	if (is_numeric($key) && $this->amysql->pdoIndexedBinding) {
	    $key--;
	}
	$this->binds[$key] = $val;
	return $this;
    }

    /**
     * The same as $this->bindValue(), except that $val is binded by
     * reference, meaning its value is extracted on execute.
     *
     * @see $this->bindValue()
     *
     * @return $this
     */
    public function bindParam($key, &$val) {
	if (is_numeric($key) && $this->amysql->pdoIndexedBinding) {
	    $key--;
	}
	$this->binds[$key] =& $val;
	return $this;
    }

    /**
     * Prepares a mysql UPDATE unexecuted. By execution, have the placeholders
     * of the WHERE statement binded.
     * It is rather recommended to use AMysql_Abstract::update() instead, which
     * lets you also bind the values in one call and it returns the success
     * of the query.
     *
     * @param string $tableName 	The table name.
     * @param array $data 		The array of data changes. A
     *					    one-dimensional array
     * 					with keys as column names and values
     *					    as their values.
     * @param string $where		An SQL substring of the WHERE clause.
     *
     * @return $this
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
	$tableSafe = AMysql_Abstract::escapeIdentifier($tableName);
	$beforeSql = "UPDATE $tableSafe SET $setsString WHERE ";
	$this->prepare($where);
	$this->beforeSql = $beforeSql;

	return $this;
    }

    /**
     * Prepares a mysql INSERT unexecuted. After this, you should just
     * call $this->execute().
     * It is rather recommended to use AMysql_Abstract::insert() instead, which
     * returns the last inserted id already.
     *
     * @param string $tableName 	The table name.
     * @param array $data		A one or two-dimensional array.
     * 					1D:
     * 					an associative array of keys as column names and values
     * 					as their values. This inserts one row.
     * 					2D numeric:
     * 					A numeric array where each value is an associative array
     * 					with column-value pairs. Each outer, numeric value represents
     * 					a row of data.
     * 					2D associative:
     * 					An associative array where the keys are the columns, the
     * 					values are numerical arrays, where each value represents the
     * 					value for the new row of that key.
     *
     * @return $this
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
	$tableSafe = AMysql_Abstract::escapeIdentifier($tableName);
	$sql = "INSERT INTO $tableSafe ($columnsString) VALUES ($valuesString)";
	$this->prepare($sql);
	return $this;
    }

    /**
     * Prepares a mysql DELETE unexecuted. By execution, have the placeholders
     * of the WHERE statement binded.
     * It is rather recommended to use AMysql_Abstract::delete() instead, which
     * lets you also bind the values in one call and it returns the success
     * of the query.
     *
     * @param string $tableName 	The table name.
     * @param string $where		An SQL substring of the WHERE clause.
     *
     * @see AMysql_Abstract::delete()
     *
     * @return $this
     **/         
    public function delete($tableName, $where) {
	$tableSafe = AMysql_Abstract::escapeIdentifier($tableName);
	$sql = "DELETE FROM $tableSafe";
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
     * Returns the last insert id
     *
     * @return integer|false
     **/	     
    public function insertId() {
	$ret = mysql_insert_id($this->link);
	return $ret;
    }

    /**
     * Ha nem marad példány ebből az objektumból, szabadítsa fel a result-ot,
     * ha van.     
     **/         
    public function __destruct() {
	$this->freeResults();
    }

    public function __set($name, $value) {
	switch($name) {
	case 'fetchMode':
	    $this->setFetchMode($value);
	    break;
	default:
	    throw new OutOfBoundsException("Invalid member: `$name` " .
		"(target value was `$value`)");
	}
    }
}
?>
