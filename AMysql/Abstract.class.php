<?php
/**
 * Mysql absztrakci�, amely csak a sima mysql f�ggv�nyeket hivogatja
 * @author Szer�mi Attila
 * @version 5
 *   
 **/
abstract class AMysql_Abstract {

    public $lastInsertId;
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
     * Backtickeket tesz az oszlop vagy t�blan�v k�r�
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
     * AMysql_Exception-t dob a legutols� Mysql hiba sz�veggel �s sz�mmal.
     * 
     **/              
    public function throwException() {
        throw new AMysql_Exception($this->error, $this->errno);
    }
    
    /**
     * V�grehajt egy k�r�st.
     * @param string $sql A k�r�s stringje.
     * @return resource $result Az eredm�ny resource-ja.     
     **/              
    public function query($sql) {
        $stmt = new AMysql_Statement($this);
        $stmt->query($sql);
        $this->lastStatement = $stmt;
        return $stmt;
    }
    
    public function prepare($sql) {
        $stmt = new AMysql_Statement($this);
        $stmt->prepare($sql);
        return $stmt;
    }
    
    /**
     * Egy eredm�nyen v�gez mysql_fetch_assoc()-ot. Ha nem adunk meg eredm�ny
     * resource-t, akkor az utols�, ezen objektumon l�trej�tt eredm�ny
     * resource-t haszn�lja.          
     * @return mixed Egy eredm�nysor, ha van, k�l�nban false.
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
    
    public function update($tableName, array $data, $where, $binds = null) {
        $stmt = new AMysql_Statement($this);
        $stmt->update($tableName, $data, $where);
        $stmt->execute($binds);
        return $stmt->affectedRows();
    }
    
    public function insert($tableName, array $data) {
        $stmt = new AMysql_Statement($this);
        $stmt->insert($tableName, $data);
        $stmt->execute();
        return $stmt->affectedRows();
    }
    
    public function delete($tableName, $where, $binds = null) {
        $stmt = new AMysql_Statement($this);
        $stmt->delete($tableName, $where);
        $stmt->execute($binds);
        return $stmt->affectedRows();
    }
    
    
    /**
     * Escape-el, �s aposztr�fok k�z� teszi az �tadott �rt�ket. Illetve t�pust�l
     * f�gg�en form�z.
     * 
     **/                   
    public function escape($value) {
        $res = $this->mysqlResource;
        // string eset�n escape-elj�k a stringet, �s aposztr�fok k�z� tessz�k
        if (is_string($value)) {
            return "'" . mysql_real_escape_string($value, $res) . "'";
        }
        // integer eset�n csak visszaadjuk a sz�mot
        if (is_int($value)) {
            return $value;
        }
        // null eset�n id�z�jelek n�lk�l a NULL kulcssz�t adjuk vissza stringk�nt
        if (null === $value) {
            return 'NULL';
        }
        // boolean eset�n a TRUE vagy FALSE kulcssz�t adjuk vissza stringk�nt
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
    }
}
?>
