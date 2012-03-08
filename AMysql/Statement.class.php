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
 * @author Szerémi Attila
 * @version 7
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
        $result = $this->query($sql);
        return $result;
    }
    
    /**
     * A különböző metódusok (prepare, select, insert stb.) által épített
     * előkészített sql query string, és a bind-oló metódusok (bindParam,
     * bindValue) által visszaadja az éppeni sql stringet, ami ténylegesen
     * létrejön execute()-nál.
     * @author Szerémi Attila               
     **/         
    public function getSql() {
        $prepared = $this->prepared;
        $sql = $this->prepared;
        $binds =& $this->binds;
        if ($binds) {
            if (array_key_exists(0, $binds)) {
                $parts = explode('?', $sql);
                $sql = '';
                if (count($parts)-1 == count($binds)) {
                    foreach ($binds as &$bind) {
                        $sql .= array_shift($parts);
                        $sql .= $this->escape($bind);
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
                    $sql = str_replace($placeholder, $bind, $sql);
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
    
    public function query($sql) {
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
        return $result;
    }
    
    public function startTransaction() {
        return $this->query('START TRANSACTION');
    }
    
    public function commit() {
        return $this->query('COMMIT');
    }
    
    public function rollback() {
        return $this->query('ROLLBACK');
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
        else {
            throw new Exception("Unknown fetch mode: `$this->fetchMode`");
        }
    }
    
    public function fetchAssoc($result = null) {
        if (!$result) {
            $result = $this->result;
        }
        return mysql_fetch_assoc($result);
    }
        
    public function fetchAllAssoc($result = null) {
        if (!$result) {
            $result = $this->result;
        }
        $ret = array();
        while (false !== ($row = $this->fetchAssoc($result))) {
            $ret[] = $row;
        }
        return $ret;
    }
    
    public function fetchObject($result = null) {
        if (!$result) {
            $result = $this->result;
        }
        return mysql_fetch_object($result);
    }
    
    public function affectedRows() {
        return $this->affectedRows;
    }
	
	public function numRows() {
		return mysql_num_rows($this->result);
	}
    
    /**
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
			$table = $this->escapeColumn($tableName, $alias);
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
			$column = $this->escapeColumn($columnName, $alias);
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
		$sql = ' LEFT JOIN ' . $this->escapeTable($tableName, $as);
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

	/**
	 * Egy oszlopnevet escape-el.
	 * @param string $columnName Az oszlop neve.
	 * @param string $as (opcionális) az oszlop átnevezése.          	
	 **/
	public function escapeColumn($columnName, $as = null) {
	    return AMysql::escapeColumn($columnName, $as);
		$asString = '';
		$escapeColumnName = true;
		if ($as and !is_int($as)) {
			$asString = ' AS ' . $as; 
		}
		else if (is_string($columnName) and (false !== strpos($columnName, ' AS '))) {
			$exploded = explode(' AS ', $columnName);
			$columnName = $exploded[0];
			$asString = ' AS ' . $exploded[1];
		}
		if ($columnName instanceof AMysql_Expr) {
            $ret = $columnName->__toString() . $asString;
        }
        else {
		    $ret = '`' . $columnName . '`' . $asString;
        }
		return $ret;
    }
    
    public function escapeTable($tableName, $as = null) {
		$ret = $this->amysql->escapeColumn($tableName);
		if ($as and !is_int($as)) {
			$ret .= ' ' . $as; 
		}
		return $ret;
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
            $sets[] = "`$columnName` = " . $this->escape($value);
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
                $cols[] = $this->escapeColumn($columnName);
                if (!is_array($values)) {
                    $values = array($values);
                }
                foreach ($values as $key => $value) {
                    if (!isset($vals[$key])) {
                        $vals[$key] = array ();
                    }
                    $vals[$key][] = $this->escape($value);
                }
            }
        }
        else {
            $akeys = array_keys($data[0]);
            $cols = array_combine($akeys, $akeys);
            foreach ($data as $row) {
                $vals[$i] = array ();
                $row2 = array_fill_keys($akeys, null);
                foreach ($row as $columnName => $value) {
                    $row2[$columnName] = $this->escape($value);
                    
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
     * Escape-el, és aposztrófok közé teszi az átadott értéket. Illetve típustól
     * függően formáz.
     * 
     **/                   
    public function escape($value) {
        $res = $this->mysqlResource;
        if ('mysql link' != get_resource_type($res)) {
			throw new RuntimeException('Resource is not a mysql resource.', 0, $sql);
        }
        // string esetén escape-eljük a stringet, és aposztrófok közé tesszük
        if (is_string($value)) {
            return "'" . mysql_real_escape_string($value, $res) . "'";
        }
        // integer esetén csak visszaadjuk a számot
        if (is_int($value)) {
            return $value;
        }
        // null esetén idézőjelek nélkül a NULL kulcsszót adjuk vissza stringként
        if (null === $value) {
            return 'NULL';
        }
        // boolean esetén a TRUE vagy FALSE kulcsszót adjuk vissza stringként
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        // AMysql_Expr esetén az objektum toString() metódusát hívva kapjuk
        // meg a literális stringet
        if ($value instanceof AMysql_Expr) {
            return $value->__toString();
        }
    }
    
    /**
     * Visszaadja az új insertált id-t
     * @return int     
     **/	     
    public function insertId() {
    	$ret = mysql_insert_id($this->mysqlResource);
    	if (false !== $ret) {
			return $ret;
		}
		else {
			$msg = 'No MySQL connection was established.';
			if ($this->throwExceptions) {
				throw new RuntimeException($msg);
			}
			trigger_error($msg, E_USER_WARNING);
			return false;
		}
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
