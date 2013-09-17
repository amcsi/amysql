Version 1.0.0 is finally out! Now with AMysql_Select!

Installation
=

See [INSTALL](INSTALL.md) file.


Usage
=

Instantiating AMysql
-

    $amysql = new AMysql($host, $user, $pass);
    $amysql->selectDb($db);

    // or

    $conn = mysql_connect($host, $user, $pass);
    mysql_select_db($db, $conn);
    $amysql = new AMysql($conn);

Inserting one row of data
-

    $data = array (
        'name' => 'adam',
        'description' => 'blah'
    );
    $amysql->insert('tablename', $data);

Inserting mysql expressions
-

    $data = array (
        'name' => 'adam',
        'description' => 'blah',
        'date' => $amysql->expr('CURRENT_TIMESTAMP')
    );
    $insertId = $amysql->insert('tablename', $data);
    // INSERT INTO `tablename` (`name`, `description`, `date`) VALUES ('adam', 'blah', CURRENT_TIMESTAMP);

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
