<?php
/**
 * AMysql_Select 
 *
 * Anatomy of a select:
 * SELECT <SELECT OPTIONS> <WHATS> FROM <FROMS> <JOINS> <WHERES> <GROUP BYS> <HAVINGS>
 * <ORDER BYS> <LIMIT> <OFFSET>
 * 
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Select extends AMysql_Statement {

    protected $whatLiteral;
    protected $what = array ();

    protected $froms = array ();
    protected $joins = array ();
    protected $wheres = array ();
    protected $groupBys = array ();
    protected $havings = array ();
    protected $orderBys = array ();
    protected $limit = null;
    protected $offset = null;

    protected $selectOptions = array ();

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
    }

    /**
     * Adds an array of columns to select.
     * 
     * @param array $what       The array of table names. Aliases can optionally be
     *                          assigned with the array key.
     * @access public
     * @return $this
     */
    public function whatArray(array $what)
    {
        foreach ($what as $key => $val) {
            $this->whatSingle($val, $key);
        }
        return $this;
    }

    /**
     * Adds a WHAT to the list of columns to select. 
     * 
     * @param string $tableName     The (namespaced) table name. No need to escape *.
     * @param string $alias         (Optional) the alias for the column.
     * @access public
     * @return $this
     */
    public function whatSingle($tableName, $alias = false)
    {
        if ('*' == $tableName[strlen($tableName)- 1]) {
            $this->what['*'] = $tableName;
        }
        else if ($alias && !is_numeric($alias)) {
            // ['alias' => 'a.colName'] => ['alias' => `a.colName` AS `alias`]
            $this->what[$alias] = AMysql::escapeIdentifier($tableName, $alias);
        }
        else {
            // [0 => 'a.colName'] => ['a.colName' => `a.colName`]
            $this->what[$tableName] = AMysql::escapeIdentifier($tableName);
        }
        return $this;
    }

    /**
     * Add this literal string between select options and whats.
     *
     * @param string $whatLiteral       Literal string
     * @access public
     * @return $this
     */
    public function whatLiteral($whatLiteral)
    {
        if ($this->whatLiteral) {
            $this->whatLiteral .= ", $whatLiteral";
        }
        else {
            $this->whatLiteral = $whatLiteral;
        }
        return $this;
    }

    /**
     * Adds a table name to the list of tables to select FROM.
     * You can use literals as table names with AMysql_Expr.
     * 
     * @param string|AMysql_Expr $tableName     The table name
     * @param string $as                        (Optional) The alias
     * @access public
     * @return $this
     */
    public function from($tableName, $as = null)
    {
        $ref = $as ? $as : $tableName;
        $tableName = $tableName instanceof AMysql_Expr ?
            $tableName->__toString() :
            AMysql::escapeIdentifier($tableName) 
        ;
        if ($as) {
            $tableName .= " AS $as";
        }
        $this->froms[$ref] = $tableName;
        return $this;
    }

    /**
     * Adds a JOIN 
     * 
     * @param string $type      Type of join. 'left' would be LEFT JOIN, 'inner'
     *                          would be INNER JOIN. Leaving this falsy will result
     *                          in a normal JOIN.
     * @param string $table     The table name to join.
     * @param string $on        The ON clause unbound.
     * @param string $as        What table name to alias this joined table as.
     * @param boolean $prepend  (Optional) whether to prepend this JOIN to the other
     *                          joins. Default: false (append).
     * @access public
     * @return $this
     */
    public function join($type, $table, $on, $as = false, $prepend = false)
    {
        $joinText = $type ? strtoupper($type) . ' JOIN' : 'JOIN';

        $table = AMysql_Abstract::escapeIdentifier($table, $as);

        $text = "$joinText $table ON $on";
        if ($prepend) {
            array_unshift($this->joins, $text);
        }
        else {
            $this->joins[] = $text;
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
     * Adds a GROUP BY parameter 
     * 
     * @param name $col         Column name
     * @param bool $desc        (Optional) Whether to sort DESC. Default: false
     * @param int $weight       (Optional) weight of the parameter.
     *                              Lighter ones come sooner. Default: 0
     * @param int $prepend      (Optional) Whether to prepend this parameter.
     *                              Default: false
     * @access public
     * @return $this;
     */
    public function groupBy($col, $desc = false, $weight = 0, $prepend = false)
    {
        $what = AMysql_Abstract::escapeIdentifier($col);
        if ($desc) {
            $what .= ' DESC';
        }
        if (!isset($this->groupBys[$weight])) {
            $this->groupBys[$weight] = array ();
        }
        if ($prepend) {
            array_unshift($this->groupBys[$weight], $what);
        }
        else {
            $this->groupBys[$weight][] = $what;
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
     * @param int $weight       (Optional) weight of the parameter.
     *                              Lighter ones come sooner. Default: 0
     * @param int $prepend      (Optional) Whether to prepend this parameter.
     *                              Default: false
     * @access public
     * @return $this;
     */
    public function orderBy($col, $desc = false, $weight = 0, $prepend = false)
    {
        $what = AMysql_Abstract::escapeIdentifier($col);
        if ($desc) {
            $what .= ' DESC';
        }
        if (!isset($this->orderBys[$weight])) {
            $this->orderBys[$weight] = array ();
        }
        if ($prepend) {
            array_unshift($this->orderBys[$weight], $what);
        }
        else {
            $this->orderBys[$weight][] = $what;
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
            $parts[] = join (', ', $this->selectOptions) . ' ';
        }

        $what = $this->what;
        if ($this->whatLiteral) {
            $what[] = $this->whatLiteral;
        }
        $parts[] = join(', ', $what);
        if ($this->from) {
            $parts[] = 'FROM ' . join(', ', $this->froms);
        }

        foreach ($this->joins as $join) {
            $parts[] = $join;
        }

        if ($this->wheres) {
            $parts[] = 'WHERE ' . join(', ', $this->wheres);
        }

        if ($this->groupBys) {
            ksort($this->groupBys);
            $ob = array ();
            foreach ($this->groupBys as $weight => $groupBys) {
                // these are only the array of groupBys under this weight. Let's iterate deeper.
                foreach ($groupBys as $groupBy) {
                    $ob[] = $groupBy;
                }
            }
            $ob = join(', ', $ob);
            $parts[] = 'GROUP BY ' . $ob;
        }

        if ($this->havings) {
            $parts[] = 'HAVING ' . join(', ', $this->havings);
        }

        if ($this->orderBys) {
            krsort($this->orderBys);
            $ob = array ();
            foreach ($this->orderBys as $weight => $orderBys) {
                // these are only the array of orderBys under this weight. Let's iterate deeper.
                foreach ($orderBys as $orderBy) {
                    $ob[] = $orderBy;
                }
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
