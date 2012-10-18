<?php /* vim: set tabstop=8 expandtab : */
/**
 * Mysql abstraction which only uses mysql_* functions
 *
 * For information on binding placeholders, @see AMysql_Statement::execute()
 *
 * @todo try to make a new select class that works similarly like in Zend
 * @todo Maybe remove automatic dot detection for identifier escaping.
 * @todo AMysql_Select
 *
 * Visit https://github.com/amcsi/amysql
 * @author Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 * @version 0.9.3
 **/

$dir = dirname(realpath(__FILE__));
require_once $dir . '/Exception.php';
require_once $dir . '/Expr.php';
require_once $dir . '/Statement.php';
require_once $dir . '/Iterator.php';

abstract class AMysql_Abstract {

    public $insertId; // last insert id
    public $lastStatement; // last AMysql_Statement
    public $link = null; // mysql link
    public $error; // last error message
    public $errno; // last error number
    public $result; // last mysql result
    public $query; // last used query string
    public $affectedRows; // last affected rows count
    /**
     * Contains the number of total affected rows by the last multiple statement deploying
     * method, such as updateMultipleByData and updateMultipleByKey
     * 
     * @var int
     */
    public $multipleAffectedRows;
    public $throwExceptions = true; // whether to throw exceptions
    public $totalTime = 0.0;

    /**
     * Whether the time all the queries take should be recorded.
     *
     * @var boolean
     */
    public $profileQueries = false;
    /**
     * Whether backtraces should be added to each array for getQueriesData(). If true,
     * backtraces will be found under the 'backtrace' key.
     *
     * @var boolean
     */
    public $includeBacktrace = false;

    protected $_queries = array ();
    protected $_queriesData = array ();

    /**
     * Let AMysql_Statement::bindParam() and AMysql_Statement::bindValue()
     * use indexes starting from 1 instead of 0 in case of unnamed placeholders.
     * The same way PDO does it. The factory default is false.
     **/
    public $pdoIndexedBinding = false;
    /**
     * The fetch mode. Can be changed here or set at runtime with
     * setFetchMode.
     * 
     * @var string
     * @access protected
     */
    protected $_fetchMode = self::FETCH_ASSOC;

    const FETCH_ASSOC	= 'assoc';
    const FETCH_OBJECT	= 'object';
    const FETCH_ARRAY	= 'array';
    const FETCH_ROW	= 'row';

    /**
     * @constructor
     * @param resource|string $resOrHost    Either a valid mysql connection
     *					    resource, or if you're connecting
     *					    to mysql with this class, then
     *					    pass the same parameters you would
     *					    pass to mysql_connect.
     *
     **/
    public function __construct($resOrHost = null, $username = null,
	$password = null, $newLink = null, $clientFlags = 0) {
	if (is_resource($resOrHost)
	    &&
	'mysql link' == get_resource_type($resOrHost)) {
            $this->link = $resOrHost;
        }
	else if(is_null($resOrHost) || is_string($resOrHost)) {
	    $args = func_get_args();
	    $res = call_user_func_array('mysql_connect', $args);
	    if ($res) {
		$this->link = $res;
	    }
	    else {
		throw new AMysql_Exception(mysql_error(), mysql_errno(),
		    '(connection to mysql)');
	    }
	}
        else {
            throw new RuntimeException('Resource given is not a mysql resource.', 0);
        }
    }

    public function getFetchMode() {
	return $this->_fetchMode;
    }

    /**
     * Selects the given database.
     * 
     * @param string $db 
     * @return $this
     */
    public function selectDb($db) {
	$result = mysql_select_db($db, $this->link);
	if (!$result) {
	    if ($this->throwExceptions) {
		throw new AMysql_Exception(mysql_error($this->link),
		    mysql_errno($this->link), 'USE ' . $db);
	    }
	    else {
		trigger_error(mysql_error($this->link), E_USER_WARNING);
	    }
	}
	return $this;
    }

    /**
     * Changes the character set of the connection.
     * 
     * @param string $charset Example: utf8
     * @return $this
     */
    public function setCharset($charset) {
	if (!function_exists('mysql_set_charset')) {
	    function mysql_set_charset($charset, $link = null) {
		return mysql_query("SET CHARACTER SET '$charset'", $link);
	    }
	}
	$result = mysql_set_charset($charset, $this->link);
	if (!$result) {
	    if ($this->throwExceptions) {
		throw new AMysql_Exception(mysql_error($this->link),
		    mysql_errno($this->link), "(setting charset)");
	    }
	    else {
		trigger_error(mysql_error($this->link), E_USER_WARNING);
	    }
	}
	return $this;
    }

    /**
     * Performs SET NAMES <charset> to change the character set. It may be
     * enough to use $this->setCharset().
     * 
     * @param string $names Example: utf8
     * @return $this
     */
    public function setNames($names) {
	$result = mysql_query("SET NAMES $names", $this->link);
	if (!$result) {
	    if ($this->throwExceptions) {
		throw new AMysql_Exception(mysql_error($this->link),
		    mysql_errno($this->link), "(setting names)");
	    }
	    else {
		trigger_error(mysql_error($this->link), E_USER_WARNING);
	    }
	}
	return $this;
    }

    /**
     * Does a simple identifier escape. It should be fail proof for that literal identifier.
     *
     * @param $identifier The identifier
     *
     * @return The escaped identifier.
     **/
    public static function escapeIdentifierSimple($identifier) {
        return '`' . addcslashes($identifier, '`\\') . '`';
    }

    /**
     * Escapes an identifier. If there's a dot in it, it is split
     * into two identifiers, each escaped, and joined with a dot.
     *
     * @param $identifier The identifier
     *
     * @return The escaped identifier.
     **/
    protected static function _escapeIdentifier($identifier) {
        $exploded = explode('.', $identifier);
        $count = count($exploded);
        $identifier = '`' . $exploded[$count-1] . '`';
        if (1 < $count) {
            $identifier = "`$exploded[0]`.$identifier";
        }
        $ret = $identifier;
        return $ret;
    }

    /**
     * Escapes an identifier, such as a column or table name.
     * Includes functionality for making an AS syntax.
     *
     * @param string $identifierName The identifier name. If it has a dot in it,
     * it'll automatically split the identifier name into the `tableName`.`columnName`
     * syntax.
     * @param string $as (Optional) adds an AS syntax. The value is the alias the
     * identifier should have for the query.
     *
     * @todo Possibly change the functionality to remove the automatic dot detection,
     * 	and ask for an array instead?
     *
     * e.g.
     *  echo $amysql->escapeIdentifier('table.order', 'ot');
     *  // `table`.`order` AS ot
     **/
    public static function escapeIdentifier($identifierName, $as = null) {
        $asString = '';
        $escapeIdentifierName = true;
        if ($as and !is_int($as)) {
            $asString = ' AS ' . $as;
        }
        else if (is_string($identifierName) and (false !== strpos($identifierName, ' AS '))) {
            $exploded = explode(' AS ', $identifierName);
            $identifierName = $exploded[0];
            $asString = ' AS ' . $exploded[1];
        }
        if ($identifierName instanceof AMysql_Expr) {
            $ret = $identifierName->__toString() . $asString;
        }
        else {
            $ret = self::_escapeIdentifier($identifierName) . $asString;
        }
        return $ret;
    }

    /**
     * Performs an InnoDB rollback.
     *
     * @todo Checks (such as for whether we have already started a
     * transaction)
     **/
    public function startTransaction() {
        return $this->query('START TRANSACTION');
    }

    /**
     * Performs an InnoDB commit.
     *
     * @todo Checks
     **/
    public function commit() {
        return $this->query('COMMIT');
    }

    /**
     * Performs an InnoDB rollback.
     *
     * @todo Checks
     **/
    public function rollback() {
        return $this->query('ROLLBACK');
    }

    /**
     * Executes a query by an sql string and binds.
     *
     * @todo Variable params possibility for binds?
     *
     * @param string $sql The SQL string.
     * @param mixed $binds The binds or a single bind.
     *
     * @return AMysql_Statement
     **/
    public function query($sql, $binds = array ()) {
        $stmt = new AMysql_Statement($this);
        $result = $stmt->query($sql, (array) $binds);
        return $stmt;
    }

    /**
     * Executes a query, and returns the first found row's first column's value.
     * Throws a warning if no rows were found.
     *
     * @todo Variable params possibility for binds?
     *
     * @param string $sql The SQL string.
     * @param mixed $binds The binds or a single bind.
     *
     * @return string
     **/
    public function getOne($sql, $binds = array ()) {
        $stmt = new AMysql_Statement($this);
        $stmt->query($sql, (array) $binds);
        return $stmt->result(0, 0);
    }

    /**
     * Like $this->getOne(), except returns a null when no result is found,
     * without throwing an error.
     *
     * @param string $sql The SQL string.
     * @param mixed $binds The binds or a single bind.
     *
     * @see $this->getOne()
     *
     * @return string
     **/
    public function getOneNull($sql, $binds = array ()) {
        $stmt = new AMysql_Statement($this);
        $stmt->query($sql, (array) $binds);
        return $stmt->resultNull(0, 0);
    }

    /**
     * Like $this->getOne(), but casts the result to an int. No exception is
     * thrown when there is no result.
     *
     * @param string $sql The SQL string.
     * @param mixed $binds The binds or a single bind.
     *
     * @see $this->getOne()
     *
     * @return string
     **/
    public function getOneInt($sql, $binds = array ()) {
        $stmt = new AMysql_Statement($this);
        $stmt->query($sql, (array) $binds);
        return $stmt->resultInt(0, 0);
    }

    /**
     * Prepares a mysql statement. It is to be executed.
     *
     * @param string $sql The SQL string.
     *
     * @return AMysql_Statement
     **/
    public function prepare($sql) {
        $stmt = new AMysql_Statement($this);
        $stmt->prepare($sql);
        return $stmt;
    }

    /**
     * Starts building a new SELECT sql.
     * USAGE OF THIS METHOD IS HIGHLY DISCOURAGED. IT IS BOUND TO CHANGE A LOT.
     * Use prepared statements instead.
     *
     * @see AMysql_Statement::select()
     *
     * @return AMysql_Statement
     **/
    public function select() {
        $stmt = $this->newStatement();
        $args = func_get_args();
        return call_user_func_array(array($stmt, 'select'), $args);
    }

    /**
     * Creates a new AMysql_Statement instance and returns it.
     *
     * @return AMysql_Statement
     **/
    public function newStatement() {
        return new AMysql_Statement($this);
    }

    /**
     * Performs an instant UPDATE returning its success.
     *
     * @param string $tableName 	The table name.
     * @param array $data 		The array of data changes. A
     *					    one-dimensional array
     * 					with keys as column names and values
     *					    as their values.
     * @param string $where		An SQL substring of the WHERE clause.
     * @param mixed $binds		(Optional) The binds or a single bind for the WHERE clause.
     *
     * @return boolean Whether the update was successful.
     **/
    public function update($tableName, array $data, $where, $binds = array()) {
        $stmt = new AMysql_Statement($this);
        $stmt->update($tableName, $data, $where);
        $result = $stmt->execute($binds);
        return $stmt->result;
    }

    /**
     * Updates multiple rows.
     * The number of total affected rows can be found in
     * $this->multipleAffectedRows.
     *
     * @param string $tableName 	The table name.
     * @param array $data 		The array of data changes. A
     *					    one-dimensional array
     * 					with keys as column names and values
     *					    as their values.
     *					One of the keys should be the one
     *					    with the value to search for for
     *					    replacement.
     * @param string $column		(Options) the name of the column and
     *					    key to search for. The default is
     *					    'id'.
     * @return boolean
     **/
    public function updateMultipleByData(
	$tableName, array $data, $column = 'id'
    ) {
	$successesNeeded = count($data);
	$where = self::escapeIdentifier($column) . " = ?";
	$affectedRows = 0;
	foreach ($data as $row) {
	    $by = $row[$column];
	    unset($row[$column]);
	    $stmt = new AMysql_Statement($this);
	    $stmt->update($tableName, $row, $where)->execute(array ($by));
	    $affectedRows += $stmt->affectedRows;
	    if ($stmt->result) {
		$successesNeeded--;
	    }
	}
	$this->multipleAffectedRows = $affectedRows;
	return 0 === $successesNeeded;
    }

    /**
     * Updates multiple rows. The values for the column to search for is the
     * key of each row.
     * The number of total affected rows can be found in
     * $this->multipleAffectedRows.
     *
     * @param string $tableName 	The table name.
     * @param array $data 		The array of data changes. A
     *					    one-dimensional array
     * 					with keys as column names and values
     *					    as their values.
     *					Each data row must be under the key
     *					    that is the same as the value of
     *					    the column being searched for.
     * @param string $column		(Options) the name of the column and
     *					    key to search for. The default is
     *					    'id'.
     * @param string $updateSameColumn	(Options) If the column being searched
     *					    for is within the a data row,
     *					    if this is false, that key should
     *					    be removed before updating the data.
     *					    This is the default.
     *
     * @return boolean
     **/
    public function updateMultipleByKey(
	$tableName, array $data, $column = 'id', $updateSameColumn = false
    ) {
	$successesNeeded = count($data);
	$where = self::escapeIdentifier($column) . " = ?";
	$affectedRows = 0;
	foreach ($data as $by => $row) {
	    if (!$updateSameColumn) {
		unset($row[$column]);
	    }
	    $stmt = new AMysql_Statement($this);
	    $stmt->update($tableName, $row, $where)->execute(array ($by));
	    $affectedRows += $stmt->affectedRows;
	    if ($stmt->result) {
		$successesNeeded--;
	    }
	}
	$this->multipleAffectedRows = $affectedRows;
	return 0 === $successesNeeded;
    }

    /**
     * Performs an instant INSERT.
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
     * @return mixed The mysql_insert_id(), if the query succeeded and there exists a primary
     * key. Otherwise the boolean of whether the insert was successful.
     **/
    public function insert($tableName, array $data) {
        $stmt = new AMysql_Statement($this);
        $stmt->insert($tableName, $data);
        $success = $stmt->execute();
        if ($success) {
            return $stmt->insertId ? $stmt->insertId : true;
        }
        else {
            return false;
        }
    }

    /**
     * Performs an instant REPLACE.
     *
     * @see $this->insert()
     *
     * @return boolean Success.
     **/
    public function replace($tableName, array $data) {
        $stmt = new AMysql_Statement($this);
        $stmt->replace($tableName, $data);
        $success = $stmt->execute();
        return $success;
    }

    /**
     * Performs an INSERT or an UPDATE; if the $value
     * parameter is not falsy, an UPDATE is performed with the given column name
     * and value, otherwise an insert. It is recommended that this is used for
     * tables with a primary key, and use the primary key as the column to
     * look at. Also, this would keep the return value consistent.
     * 
     * @param mixed $tableName		The table name to INSERT or UPDATE to
     * @param mixed $data		The data to change
     * @param mixed $columnName		The column to search by. It should be
     *					a primary key.
     * @param mixed $value		(Optional) The value to look for in
     *					case you want
     *					to UPDATE. Keep this at null, 0,
     *					or anything else falsy for INSERT.
     *
     * @return integer			If the $value is not falsy, it returns
     *					$value after UPDATING. Otherwise the
     *					mysql_insert_id() of the newly
     *					INSERTED row.
     */
    public function save($tableName, $data, $columnName, $value = null) {
	if ($value) {
	    $where = AMysql_Abstract::escapeIdentifier($columnName) . ' = ?';
	    $this->update($tableName, $data, $where, array ($value));
	    return $value;
	}
	else {
	    $id = $this->insert($tableName, $data);
	    return $id;
	}
    }

    /**
     * Performs an instant DELETE.
     *
     * @param string $tableName 	The table name.
     * @param string $where		An SQL substring of the WHERE clause.
     * @param mixed $binds		(Optional) The binds or a single bind for the WHERE clause.
     *
     * @return resource|false The mysql resource if the delete was successful, otherwise false.
     **/
    public function delete($tableName, $where, $binds = array ()) {
        $stmt = new AMysql_Statement($this);
        $stmt->delete($tableName, $where);
        $result = $stmt->execute($binds);
        return $result;
    }

    /**
     * If the last mysql query was a SELECT with the SQL_CALC_FOUND_ROWS
     * options, this returns the number of found rows from that last
     * query with LIMIT and OFFSET ignored.
     * 
     * @access public
     * @return void
     */
    public function foundRows() {
        $stmt = new AMysql_Statement($this);
	$sql = 'SELECT FOUND_ROWS()';
	$stmt->query($sql);
	return $stmt->resultInt();
    }

    /**
     * Returns an AMysql_Expr for using in prepared statements as values.
     * See the AMysql_Expr class for details.
     * To bind a literal value without apostrophes, here is an example of
     * how you can execute a prepared statement with the help of placeholders:
     *	$amysql->prepare('SELECT ? AS time')->execute(array (
     *	    $amysql->expr('CURRENT_TIMESTAMP')
     *	))
     *
     * @see AMysql_Expr
     *
     * @return AMysql_Expr
     **/
    public function expr(/* args */) {
	$args = func_get_args();
	$expr = new AMysql_Expr($this);
	call_user_func_array(array ($expr, 'set'), $args);
	return $expr;
    }

    /**
     * Escapes LIKE. The after the LIKE <string> syntax, you must place
     * an ESCAPE statement with '=' or whatever you pass here as
     * $escapeStr
     *
     * @param string $s The string to LIKE escape
     * @param string $escapeChar (Opcionális) The escape character
     **/
    public static function escapeLike($s, $escapeStr = '=') {
	return str_replace(
	    array($escapeStr, '_', '%'), 
	    array($escapeStr.$escapeStr, $escapeStr.'_', $escapeStr.'%'), 
	    $s
	);
    }

    /**
     * Escapes a value. The method depends on the passed value's type, but unless the passed type
     * is an AMysql_Expr, the safety is almost guaranteed. Do not put apostrophes around bind marks!
     * Those are handled by this escaping method.
     *
     * @todo Automatic AMysql_Expr(AMysql_Expr::COLUMN_IN) in case of an array?
     *
     * @param mixed The value to escape
     *
     **/
    public function escape($value) {
        $res = $this->link;
        if ('mysql link' != get_resource_type($res)) {
            throw new RuntimeException('Resource is not a mysql resource.', 0, $sql);
        }
        // If it's an int, place it there literally
        if (is_int($value)) {
            return $value;
        }
        // If it's a NULL, use the literal string, NULL
        if (null === $value) {
            return 'NULL';
        }
        // Literal TRUE or FALSE in case of a boolean
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        // In case of an AMysql_Expr, use its __toString() methods return value.
        if ($value instanceof AMysql_Expr) {
            return $value->__toString();
        }
	// In the case of a string or anything else, let's escape it and
	// put it between apostrophes.
	return "'" . mysql_real_escape_string($value, $res) . "'";
    }

    /**
     * Transposes a 2 dimensional array.
     * Every inner array must contain the same keys as the other inner arrays,
     * otherwise unexpected results may occur.
     *
     * Example:
     *   $input = array (
     *       3 => array (
     *           'col1' => 'bla',
     *           'col2' => 'yo'
     *       ),
     *       9 => array (
     *           'col1' => 'ney',
     *           'col2' => 'lol'
     *       )
     *   );
     *   $output = $amysql->transpose($input);
     *
     *   $output: array (
     *       'col1' => array (
     *           3 => 'bla',
     *           9 => 'ney'
     *       ),
     *       'col2' => array (
     *           3 => 'yo',
     *           9 => 'lol'
     *       )
     *   );
     *
     * @param array $array The 2 dimensional array to transpose
     * @return array
     */
    public static function transpose(array $array) {
        $ret = array ();
        if (!$array) {
            return $ret;
        }
        foreach ($array as $key1 => $arraySub) {
            if (!$ret) {
                foreach ($arraySub as $key2 => $value) {
                    $ret[$key2] = array ($key1 => $value);
                }
            }
            else {
                foreach ($arraySub as $key2 => $value) {
                    $ret[$key2][$key1] = $value;
                }
            }
        }
        return $ret;
    }

    public function addQuery($query, $queryTime) {
        $this->_queries[] = $query;
        $data = array (
            'query' => $query,
            'time' => $queryTime
        );
        if ($this->includeBacktrace) {
            $opts = 0;
            if (defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
                $opts |= DEBUG_BACKTRACE_IGNORE_ARGS;
            }
            $data['backtrace'] = debug_backtrace($opts);
        }
        $this->_queriesData[] = $data;
        if (is_numeric($queryTime)) {
            $this->totalTime += $queryTime;
        }
    }

    public function getQueries() {
        return $this->_queries;
    }

    /**
     * Returns an arrays of profiled query data. Each value is an array that consists
     * of:
     *  - query - The SQL query performed
     *  - time - The amount of seconds the query took (float)
     *
     * If profileQueries wss off at any query, its time value will be null.
     * 
     * @return array[]
     */
    public function getQueriesData() {
        return $this->_queriesData;
    }
}
?>
