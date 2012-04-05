<?php
/**
 * Mysql absztrakció, amely csak a sima mysql függvényeket hivogatja
 * @author Szerémi Attila
 * @version 6
 *   
 **/
abstract class AMysql_Abstract {

    public $insertId;
    public $lastStatement;
    public $mysqlResource = null;
    public $error;
    public $errno;
    public $result;
    public $query;
    public $affectedRows;
    public $throwExceptions = true;
    
    /**
     * @constructor
     * @param resource $res A mysql kapcsolat resource-ja.
     * 
     **/                   
    public function __construct($res) {
        if ('mysql link' == get_resource_type($res)) {
            $this->mysqlResource = $res;
        }
		else {
			throw new RuntimeException('Resource given is not a mysql resource.', 0);
		}
    }
    
    /**
     * Backtickeket tesz az oszlop vagy táblanév köré
     * 
     **/          
    protected static function _escapeColumn($column) {
		$exploded = explode('.', $column);
		$count = count($exploded);
		$column = '`' . $exploded[$count-1] . '`';
		if (1 < $count) {
			$column = "`$exploded[0]`.$column";
		}
		$ret = $column;
        return $ret;
    }
    
    public static function escapeColumn($columnName, $as = null) {
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
		    $ret = self::_escapeColumn($columnName) . $asString;
        }
		return $ret;
    }
    
    public static function escapeTable($tableName, $as = null) {
		$ret = self::_escapeColumn($tableName);
		if ($as and !is_int($as)) {
			$ret .= ' ' . $as; 
		}
		return $ret;
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
     * AMysql_Exception-t dob a legutolsó Mysql hiba szöveggel és számmal.
     * 
     **/              
    public function throwException() {
        throw new AMysql_Exception($this->error, $this->errno);
    }
    
    /**
     * Végrehajt egy kérést.
     * @param string $sql A kérés stringje.
     * @return resource $result Az eredmény resource-ja.     
     **/              
    public function query($sql, $binds = null) {
        $stmt = new AMysql_Statement($this);
        $result = $stmt->query($sql, $binds);
        $this->lastStatement = $stmt;
        return $result;
    }
    
    public function prepare($sql) {
        $stmt = new AMysql_Statement($this);
        $stmt->prepare($sql);
        return $stmt;
    }
    
    /**
     * Egy eredményen végez mysql_fetch_assoc()-ot. Ha nem adunk meg eredmény
     * resource-t, akkor az utolsó, ezen objektumon létrejött eredmény
     * resource-t használja.          
     * @return mixed Egy eredménysor, ha van, különban false.
     **/         
    public function fetchAssoc($result = null) {
        if (is_resource($result)) {
            $stmt = $this->lastStatement;
        }
        else {
            $result = null;
            if ($result instanceof AMysql_Statement) {
                $stmt = $result;
            }
            else {
                $stmt = $this->lastStatement;
            }
        }
        return $stmt->fetchAssoc($result);
    }
        
    public function fetchAllAssoc($result = null) {
        if (is_resource($result)) {
            $stmt = $this->lastStatement;
        }
        else {
            $result = null;
            if ($result instanceof AMysql_Statement) {
                $stmt = $result;
            }
            else {
                $stmt = $this->lastStatement;
            }
        }
        return $stmt->fetchAllAssoc($result);
    }
    
    public function fetchObject($result = null) {
        if (is_resource($result)) {
            $stmt = $this->lastStatement;
        }
        else {
            $result = null;
            if ($result instanceof AMysql_Statement) {
                $stmt = $result;
            }
            else {
                $stmt = $this->lastStatement;
            }
        }
        return $stmt->fetchObject($result);
    }

	public function select() {
		$stmt = $this->newStatement();
		$args = func_get_args();
		return call_user_func_array(array($stmt, 'select'), $args);
	}
    
	public function affectedRows() {
		$deprecatedMessage = 'Do not use AMysql::affectedRows(). Use the statement\'s
				affectedRows() method!';
		if (class_exists('FB')) {
			FB::warn($deprecatedMessage);
		}
		trigger_error($deprecatedMessage, E_USER_DEPRECATED);
        return $this->lastStatement->affectedRows();
    }

	public function newStatement() {
		return new AMysql_Statement($this);
	}

	/**
	 * @return boolean Sikerült-e az update
	 **/
    public function update($tableName, array $data, $where, $binds = null) {
        $stmt = new AMysql_Statement($this);
        $stmt->update($tableName, $data, $where);
        $result = $stmt->execute($binds);
        return $result;
    }

	/**
	 * @return mixed a mysql_insert_id(), ha van auto increment, különben true.
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
	 * @return mixed a törölt sorok száma, ha sikerült, különben false
	 **/
    public function delete($tableName, $where, $binds = null) {
        $stmt = new AMysql_Statement($this);
        $stmt->delete($tableName, $where);
        $stmt->execute($binds);
        return $stmt->affectedRows();
    }
    
    
    /**
     * Escape-el, és aposztrófok közé teszi az átadott értéket. Illetve típustól
     * függően formáz.
     * 
     **/                   
    public function escape($value) {
        $res = $this->mysqlResource;
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
    }
}
?>
