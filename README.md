Usage <!-- vim: set tabstop=8 expandtab filetype=php : <?php -->
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
        $amysql->insert('tablename', $data);
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

Updating multiple rows
-

        /**
         * Update the name to bob and the description to blahmodified for all rows
         * with an id of 2.
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

Deleting rows
-

        $where = 'id = ?';
        $success = $amysql->delete('tablename', $where, array ('2'));

Queries throw AMysql_Exceptions by default
-

        try {
            $amysql->query("SELECTbad-syntaxError-!#");
        }
        catch (AMysql_Exception $e) {
            trigger_error($e, E_USER_WARNING); // the exception converted to string
            // contains the mysql error code, the error message, and the query string used.
        }

Selecting
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

Preparing select first, executing later
-
        $binds = array (
            'id' => 1,
            'state' => 'active'
        );
        $stmt = $amysql->prepare("SELECT * FROM tablename WHERE id = :id AND state = :state");
        $stmt->execute($binds);
        $results = $stmt->fetchAllAssoc();

A documentation on binding parameters can be found in the comments for AMysql_Statement::execute(). Be sure to check it out.

Many other useful methods are available as well. Check out the source files and read the documentation for the methods.
