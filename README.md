Requirements
=

PHP 5.1.0+ is required, and either the MySQLi extension or the mysql_* functions must be available.

Installation
=

Available on packagist.

See [INSTALL](INSTALL.md) file.


Usage
=

Typically you want to make one instance of AMysql per db connection. AMysql lazy connects by default.

Instantiating AMysql
-

When instantiating AMysql, you can pass in either a mysql link resource or connection details as an array.

    $this->_amysql = new AMysql;
    $this->_amysql->setConnDetails(array (
        'host' => 'localhost',
        'username' => 'user',
        'password' => 'pass',
        'db'        => 'db',
    ));

or

    $this->_amysql = new AMysql(array (
        'host' => 'localhost',
        'username' => 'user',
        'password' => 'pass',
        'db'        => 'db',
    ));

or

    $conn = mysql_connect($host, $user, $pass);
    $amysql = new AMysql($conn);
    $amysql->selectDb($db);
    
The full connection details array supports:

* `host` Host to connect to
* `username` Mysql user username
* `password` Mysql user password
* `db` The database to select
* `port`
* `clientFlags` mysql_* only. For the `mysql_connect()` `$client_flags` argument

When making a new connection, AMysql tries to use `MySQLi` if it is available, otherwise it falls back to the `mysql_*` functions.

Inserting one row of data
-

    $data = array (
        'name' => 'adam',
        'description' => 'blah'
    );
    $amysql->insert('tablename', $data);

Inserting mysql expressions
-

Within prepared statement you can purposefully bind literal strings that will not be escaped or enclosed by quotes. Use it with caution.

To use it, see the example below:

    $date = $amysql->expr('CURRENT_TIMESTAMP');
    // or new AMysql_Expr($amysql, 'CURRENT_TIMESTAMP');
    $data = array (
        'name' => 'adam',
        'description' => 'blah',
        'date' => $date
    );
    $insertId = $amysql->insert('tablename', $data);
    // INSERT INTO `tablename` (`name`, `description`, `date`) VALUES ('adam', 'blah', CURRENT_TIMESTAMP);

AMysql_Expr also supports a few predefined special expressions not only consisting of literal values, such as for making an `IN` list or for doing proper escaping for a `LIKE` expression. For more information, check out the [AMysql/Expr.php file](AMysql/Expr.php).

Inserting multiple rows of data
-

    $data = array (
        array (
            'name' => 'adam',
            'description' => 'blah'
        ),
        array (
            'name' => 'bob',
            'description' => 'blahblah'
        )
    );
    $id = $amysql->insert('tablename', $data);
    $affectedRows = $amysql->lastStatement->affectedRows();

    // or you can achieve the same result by having the column names be the outer indexes.

    $data = array (
        'name' => array (
            'adam', 'bob'
        ),
        'description' => array (
            'blah', 'blahblah'
        )
    );
    $id = $amysql->insert('tablename', $data);
    $affectedRows = $amysql->lastStatement->affectedRows();

Updating a single row
-

    /**
     * Update the name to bob and the description to blahmodified for all rows
     * with an id of 2.
     */
    $data = array (
        'name' => 'bob',
        'description' => 'blahmodified'
    );
    $success = $amysql->update('tablename', $data, 'id = ?', array('2'));
    $affectedRows = $amysql->lastStatement->affectedRows();

Updating multiple rows
-

You can update multiple rows with the same `insert()` method as for single rows if you pass a multidimensional array. It can be an array or rows, or an array of columns with an array of values.

    /**
     * Update the name to bob and the description to blahmodified for all rows
     * with an id of 2, and update the name to carmen and the description to
     * anothermodified for all rows with an id of 3.
     */
    $data = array (
        array (
            'id'            => 2
            'name'          => 'bob',
            'description'   => 'blahmodified'
        ),
        array (
            'id'            => 3
            'name'          => 'carmen',
            'description'   => 'anothermodified'
        )
    );
    $success = $amysql->updateMultipleByData('tablename', $data, 'id');
    $affectedRows = $amysql->multipleAffectedRows;

Deleting rows
-

    $where = 'id = ?';
    $success = $amysql->delete('tablename', $where, array ('2'));
    $affectedRows = $amysql->lastStatement->affectedRows();

Queries throw AMysql_Exceptions by default
-

    try {
        $amysql->query("SELECTbad-syntaxError-!#");
    }
    catch (AMysql_Exception $e) {
        trigger_error($e, E_USER_WARNING); // the exception converted to string
        // contains the mysql error code, the error message, and the query string used.
    }

Selecting (without AMysql_Select)
-

    // with named parameters (do not use apostrophes)
    $binds = array (
        'id' => 1,
        'state' => 'active'
    );
    $stmt = $amysql->query("SELECT * FROM tablename WHERE id = :id AND state = :state", $binds);
    $results = $stmt->fetchAllAssoc();

    // with unnamed parameters (never use apostrophes when binding values)
    $binds = array (1, 'active');
    $stmt = $amysql->query("SELECT * FROM tablename WHERE id = ? AND state = ?", $binds);
    $results = $stmt->fetchAllAssoc();
    $numRows = $stmt->numRows();
    
Note that if there is only 1 question mark in the prepared string, you may also pass the `$binds` as a scalar value and it will be treated as if it were the within an array. If you are expecting a possible `null` value to be bound, do not use the scalar method though (always use an array in that case).

P.S. this is also true for every method that expects a `$binds` array.

Preparing select first, executing later (without AMysql_Select)
-
    $binds = array (
        'id' => 1,
        'state' => 'active'
    );
    $stmt = $amysql->prepare("SELECT * FROM tablename WHERE id = :id AND state = :state");
    $stmt->execute($binds);
    $results = $stmt->fetchAllAssoc();

And now with a new AMysql_Select class to help assemble a SELECT SQL string:
-

    $select = $mysql->select();
    $select 
        ->option('DISTINCT')
        ->option('SQL_CALC_FOUND_ROWS')
        ->from(array ('table1', 't2alias' => 'table2'))
        ->from(array ('t3alias' => 'table3'), array ('t3_col1' => 'col1', 't3_col2' => 'col2'))
        ->column('t2alias.*')
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
        ->where('3 = :aBind')
        ->where("'yes' = :anotherBind")
        ->limit(100)
        ->offset(200)
    ;
    $select->execute(array ('aBind' => 3, 'anotherBind' => 'yes'));
    /*
    SELECT DISTINCT `t3alias`.`col1` AS `t3_col1`, `t3alias`.`col2` AS `t3_col2`,
        `t2alias`.*, `table1`.`col1` AS `t1_col1`, `t4alias`.`t4lol`,
        `t4alias`.`t4lol2` AS `t4lol2aliased`,
        table7, table8, CURRENT_TIMESTAMP AS ctimestamp'
        FROM `table1`, `table2` AS `t2alias`, `table3` AS `t3alias`
        LEFT JOIN `table5` ON (t2alias.colx = table5.coly)
        JOIN `table4` AS `t4alias` ON (t4alias.t1_id = table1.id)
        CROSS JOIN `table6` ON (t3alias.colx = table6.coly)
        WHERE 3 = 3 AND 'yes' = 'yes'
        GROUP BY `t2alias`.`col2` DESC, `t2alias`.`col1`, `t2alias`.`col3` DESC
        HAVING 1 = 1 AND 2 = 2
        ORDER BY `t3alias`.`col2` DESC, `t3alias`.`col1`, `t3alias`.`col3` DESC
        LIMIT 100
        OFFSET 200
     */
    $foundRows = $amysql->foundRows(); // resolves "SELECT FOUND_ROWS()" for "SQL_CALC_FOUND_ROWS"

Read the commented [AMysql_Select](AMysql/Select.php) file for more details.

A documentation on binding parameters can be found in the comments for [AMysql_Statement](AMysql/Statement.php)::execute(). Be sure to check it out.

Many other useful methods are available as well. Check out the source files and read the documentation for the methods.
