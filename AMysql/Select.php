<?php
/**
 * AMysql_Select 
 *
 * Anatomy of a select:
 * SELECT <SELECT OPTIONS> <COLUMNS> FROM <FROMS> <JOINS> <WHERES> <GROUP BYS> <HAVINGS>
 * <ORDER BYS> <LIMIT> <OFFSET>
 * 
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Select extends AMysql_Statement {

    protected $selectOptions = array ();
    protected $columnLiteral;
    protected $columns = array ();
    protected $froms = array ();
    protected $joins = array ();
    protected $wheres = array ();
    protected $groupBys = array ();
    protected $havings = array ();
    protected $orderBys = array ();
    protected $limit = null;
    protected $offset = null;

    /**
     * Adds a select option.
     *
     * e.g.
     * SQL_CALC_FOUND_ROWS
     * DISTINCT
     *
     * @param string $selectOption       The select option.
     * @access public
     * @return $this
     */
    public function option($selectOption)
    {
        $this->selectOptions[$selectOption] = $selectOption;
        return $this;
    }

    /**
     * Formats a column name and an optional alias to form `columnName` AS alias.
     * The alias is automatically not taken into account if it's numeric.
     * No need to worry about escaping the select all star character '*'.
     * 
     * @param string $columnName    The column name.
     * @param string $alias         (Optional) the alias to select AS
     * @access public
     * @return string
     */
    public function formatSelectColumn($columnName, $alias = null)
    {
        if ('*' == $columnName[strlen($columnName) - 1]) {
            return $columnName;
        }
        $ret = AMysql_Abstract::escapeIdentifier($columnName);
        if ($alias && !is_numeric($alias)) {
            $ret .= ' AS ' . AMysql_Abstract::escapeIdentifierSimple($alias);
        }
        return $ret;
    }

    /**
     * Adds one or more COLUMN to the list of columns to select. 
     * 
     * @param string|AMysql_Expr|array $columns          The column name. Can be an array or column
     *                                                  names in which case the key can mark the
     *                                                  optional alias of the column.
     * @param array $options                            (Options) an array of config options
     *                                                  columnPrefix - prefix each column with this
     *                                                  table prefix
     * @access public
     * @return $this
     */
    public function column($columns, $options = array ())
    {
        $columns = (array) $columns;
        $columnPrefix = !empty($options['columnPrefix']) ? $options['columnPrefix'] . '.' : '';
        foreach ($columns as $alias => $columnName) {
            if ('*' == $columnName[strlen($columnName)- 1]) {
                $this->columns['*'] = "$columnPrefix$columnName";
            }
            else {
                $key = $alias && !is_numeric($alias) ? $alias : $columnName;
                $this->columns[$key] = $this->formatSelectColumn("$columnPrefix$columnName", $alias);
            }
        }
        return $this;
    }

    /**
     * Add this literal string between select options and columns.
     *
     * @param string $columnLiteral       Literal string
     * @access public
     * @return $this
     */
    public function columnLiteral($columnLiteral)
    {
        if ($this->columnLiteral) {
            $this->columnLiteral .= ", $columnLiteral";
        }
        else {
            $this->columnLiteral = $columnLiteral;
        }
        return $this;
    }

    /**
     * Formats a table name and an optional alias to form `tableName` AS alias.
     * The alias is automatically not taken into account if it's numeric.
     * 
     * @param string $tableName      The table name.
     * @param string $alias         (Optional) the alias to select AS
     * @access public
     * @return string
     */
    public function formatFrom($tableName, $alias = null)
    {
        $ret = AMysql_Abstract::escapeIdentifier($tableName);
        if ($alias && !is_numeric($alias)) {
            $ret .= ' AS ' . AMysql_Abstract::escapeIdentifierSimple($alias);
        }
        return $ret;
    }

    /**
     * 1) Adds one or more table name to the list of tables to select FROM.
     *
     * 2) Alternatively, if you only select from 1 table here, you can supply an array
     * of columns to select, having them automatically prefixed to the needed prefix
     * of the table selected from. Similar to Zend Framework 1.
     * 
     * e.g.
     * ->from(array('p' => 'products'),
     *       array('product_id', 'product_name'));// Build this query:
     * // results in: SELECT p."product_id", p."product_name" FROM "products" AS p
     *
     * You can use literals as table names with AMysql_Expr.
     * 
     * @param string|AMysql_Expr|array $tables          The table name. Can be an array or table
     *                                                  names in which case the key can mark the
     *                                                  optional alias of the table.
     * @param array $columns                            (Optional)
     *                                                  The columns from this table to select.
     *                                                  Do not use if you are selecting from more than 1 tables!
     * @access public
     * @return $this
     */
    public function from($tables, $columns = array ())
    {
        $tables = (array) $tables;
        foreach ($tables as $alias => $tableName) {
            $key = !is_numeric($alias) ? $alias : $tableName;
            $this->froms[$key] = $this->formatFrom($tableName, $alias);
        }
        if ($columns) {
            $key = !is_numeric($alias) ? $alias : $tableName;
            $columnOptions = array ();
            $columnOptions['columnPrefix'] = $key;
            $this->column($columns, $columnOptions);
        }
        return $this;
    }

    /**
     * Adds a JOIN 
     * 
     * @param string $type      Type of join. 'left' would be LEFT JOIN, 'inner'
     *                          would be INNER JOIN. Leaving this falsy will result
     *                          in a normal JOIN.
     * @param string $table     The table name to join. Can be a 1 element array of
     *                          ['alias' => 'tableName']
     * @param string $on        The ON clause unbound.
     * @param array $columns    (Optional) The columns from this table to select. TODO!
     * @param boolean $prepend  (Optional) whether to prepend this JOIN to the other
     *                          joins. Default: false (append).
     * @access public
     * @return $this
     */
    public function join($type, $table, $on, $columns = array (), $prepend = false)
    {
        $table = (array) $table;
        $tableName = reset($table);
        $alias = key($table);
        $joinText = $type ? strtoupper($type) . ' JOIN' : 'JOIN';
        $tableText = $this->formatFrom($tableName, $alias);
        $text = "$joinText $tableText ON ($on)";
        if ($prepend) {
            array_unshift($this->joins, $text);
        }
        else {
            $this->joins[] = $text;
        }
        if ($columns) {
            $key = !is_numeric($alias) ? $alias : $tableName;
            $columnOptions = array ();
            $columnOptions['columnPrefix'] = $key;
            $this->column($columns, $columnOptions);
        }
        return $this;
    }

    /**
     * Adds a WHERE fragment. All fragments are joined by an AND
     * at the end. 
     * 
     * @param string $where     Unbound WHERE fragment
     * @access public
     * @return $this
     */
    public function where($where)
    {
        $this->wheres[] = $where;
        return $this;
    }

    /**
     * Adds an GROUP BY parameter 
     * 
     * @param name $col         Column name
     * @param bool $desc        (Optional) Whether to sort DESC. Default: false
     * @param int $prepend      (Optional) Whether to prepend this parameter.
     *                              Default: false
     * @access public
     * @return $this;
     */
    public function groupBy($col, $desc = false, $prepend = false)
    {
        $what = AMysql_Abstract::escapeIdentifier($col);
        if ($desc) {
            $what .= ' DESC';
        }
        if ($prepend) {
            array_unshift($this->groupBys, $what);
        }
        else {
            $this->groupBys[] = $what;
        }
        return $this;
    }

    /**
     * Adds a HAVING fragment. All fragments are joined by an AND
     * at the end. 
     * 
     * @param string $having        Unbound HAVING fragment
     * @access public
     * @return $this
     */
    public function having($having)
    {
        $this->havings[] = $having;
        return $this;
    }

    /**
     * Adds an ORDER BY parameter 
     * 
     * @param name $col         Column name
     * @param bool $desc        (Optional) Whether to sort DESC. Default: false
     * @param int $prepend      (Optional) Whether to prepend this parameter.
     *                              Default: false
     * @access public
     * @return $this;
     */
    public function orderBy($col, $desc = false, $prepend = false)
    {
        $what = AMysql_Abstract::escapeIdentifier($col);
        if ($desc) {
            $what .= ' DESC';
        }
        if ($prepend) {
            array_unshift($this->orderBys, $what);
        }
        else {
            $this->orderBys[] = $what;
        }
        return $this;
    }

    /**
     * Adds a LIMIT
     * 
     * @param int $limit    The LIMIT
     * @access public
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = (is_numeric($limit) && 0 < $limit) ? (int) $limit : null;
        return $this;
    }

    /**
     * Adds an OFFSET
     * 
     * @param int $offset   The OFFSET
     * @access public
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = (is_numeric($offset) && 0 <= $offset) ? (int) $offset : null;
        return $this;
    }

    /**
     * Gets the full, bound SQL string ready for use with MySQL.
     * 
     * @param string $prepared      (Optional) Only for parent compatibility.
     * @access public
     * @return string
     */
    public function getSql($prepared = null)
    {
        $this->prepared = $this->getUnboundSql();
        parent::getSql();
    }

    /**
     * Gets the SQL string without the binds applied yet.
     * 
     * @access public
     * @return string
     */
    public function getUnboundSql()
    {
        $parts = array ('SELECT');
        if ($this->selectOptions) {
            $parts[] = join (', ', $this->selectOptions);
        }

        $columns = $this->columns;
        if ($this->columnLiteral) {
            $columns[] = $this->columnLiteral;
        }
        $parts[] = join(', ', $columns);

        $parts = array (join(' ', $parts)); // okay so everything so far should be 1 part.

        if ($this->froms) {
            $parts[] = 'FROM ' . join(', ', $this->froms);
        }

        foreach ($this->joins as $join) {
            $parts[] = $join;
        }

        if ($this->wheres) {
            $parts[] = 'WHERE ' . join(' AND ', $this->wheres);
        }

        if ($this->groupBys) {
            $ob = array ();
            foreach ($this->groupBys as $groupBy) {
                $ob[] = $groupBy;
            }
            $ob = join(', ', $ob);
            $parts[] = 'GROUP BY ' . $ob;
        }

        if ($this->havings) {
            $parts[] = 'HAVING ' . join(' AND ', $this->havings);
        }

        if ($this->orderBys) {
            $ob = array ();
            foreach ($this->orderBys as $orderBy) {
                $ob[] = $orderBy;
            }
            $ob = join(', ', $ob);
            $parts[] = 'ORDER BY ' . $ob;
        }

        if ($this->limit) {
            $parts[] = "LIMIT $this->limit";
        }

        if ($this->offset) {
            $parts[] = "OFFSET $this->offset";
        }
        $sql = join("\n", $parts);
        return $sql;
    }
}
