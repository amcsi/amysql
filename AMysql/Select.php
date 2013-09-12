<?php
/**
 * AMysql_Select 
 *
 * Anatomy of a select:
 * SELECT <SELECT OPTIONS> <WHAT> FROM <FROM> <JOINS> <WHERES> <ORDERBYS> <LIMIT> <OFFSET>
 * 
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Select extends AMysql_Statement {

    protected $froms = array ();
    protected $joins = array ();
    protected $wheres = array ();
    protected $limit = null;
    protected $offset = null;
    protected $orderBys = array ();

    protected $whatLiteral;
    protected $what = array ();

    protected $selectOptions = array ();

    /**
     * e.g.
     * SQL_CALC_FOUND_ROWS
     * DISTINCT
     */
    public function option($selectOption)
    {
        $this->selectOptions[$selectOption] = $selectOption;
    }

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

    public function whatArray(array $what)
    {
        foreach ($what as $key => $val) {
            $this->whatSingle($val, $key);
        }
        return $this;
    }

    public function whatSingle($tableName, $alias = false)
    {
        if ($alias) {
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
     * SELECT <what> FROM ...
     **/
    public function whatLiteral($whatLiteral)
    {
        $this->whatLiteral = $whatLiteral;
    }

    public function join($type, $table, $on, $as = false, $prepend = false) {
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

    public function getFullColumnName($col)
    {
        $ret = AMysql_Abstract::escapeIdentifier($col);
        return $ret;
    }

    public function orderBy($col, $dir = 'asc', $priority = 0)
    {
        return $this->_addOrderby($col, $dir, $priority, false);
    }

    public function prependOrderBy($col, $dir = 'asc', $priority = 0)
    {
        return $this->_addOrderby($col, $dir, $priority, true);
    }

    protected function _addOrderBy($col, $dir = 'asc', $priority = 0, $prepend = false)
    {
        $what = $this->getFullColumnName($col);
        if ('desc' === strtolower($dir)) {
            $what .= ' DESC';
        }
        return $this->_orderBy($what, $priority, $prepend);
    }

    protected function _orderBy($what, $priority = 0, $prepend = false)
    {
        if (!isset($this->orderBys[$priority])) {
            $this->orderBys[$priority] = array ();
        }
        if ($prepend) {
            array_unshift($this->orderBys[$priority], $what);
        }
        else {
            $this->orderBys[$priority][] = $what;
        }
        return $this;
    }

    public function where($where)
    {
        $this->wheres[] = $where;
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (is_numeric($limit) && 0 < $limit) ? (int) $limit : null;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = (is_numeric($offset) && 0 <= $offset) ? (int) $offset : null;
        return $this;
    }

    public function getSql($prepared = null) {
        $this->prepared = $this->getUnboundSql();
        parent::getSql();
    }

    public function getUnboundSql() {
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

        if ($this->orderBys) {
            krsort($this->orderBys);
            $ob = array ();
            foreach ($this->orderBys as $priority => $orderBys) {
                // these are only the array of orderBys under this priority. Let's iterate deeper.
                foreach ($orderBys as $orderBy) {
                    $ob[] = $orderBy;
                }
            }
            $ob = join(', ', $ob);
            $parts[] = 'ORDER BY ' . $ob;
        }

        if ($this->wheres) {
            $parts[] = 'WHERE ' . join(', ', $wheres);
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
