<?php
class AMysql_Select extends AMysql_Statement {

    public function from($tableNames, $columns = true) {
        /**
         * Columns parts
         **/
        if (true === $columns || '*' === $columns) {
            $columnsString = '*';
        }
        else {
            if (is_string($columns)) {
                $columns = array ($columns);
            }
            $parts = array ();
            foreach ($columns as $key => $column) {
                if ($column instanceof AMysql_Expr) {
                    $part = $column->toString();
                    if (!is_numeric($key)) {
                        $part .= " AS " . $key;
                    }
                }
                else {
                    $part = AMysql::escapeIdentifier($column, $key);
                }
                $parts[] = $part;
            }
            $columnsString = join(', ', $parts);
        }
        /**
         * Tables parts
         **/
        $parts = array ();
        if (is_string($tableNames)) {
            $tableNames = array($tableNames);
        }
        foreach ($tableNames as $key => $tableName) {
            $parts[] = AMysql::escapeIdentifier($tableName, $key);
        }
        $tablesString = join(', ', $parts);

        $sql = "SELECT $tablesString FROM $columnsString ";
        $this->prepared = $sql;
    }
}
