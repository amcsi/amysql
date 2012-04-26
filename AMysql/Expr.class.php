<?php
/**
 * Class for making custom expressions for AMysql value binding.
 * This is what you should use to be able to add specific kinds
 * of purposely unquoted, non-numeric values into prepared
 * statements, such as being able to call mysql functions to
 * set values.
 *
 * @author Szerémi Attila
 * @created 2011.06.10. 13:26:56  
 * @version 0.8
 **/ 
class AMysql_Expr {

    public $prepared; 
    
    /**
	 * Literal string
	 *
	 * e.g. $currentTimestampBind = new AMysql_Expr(
	 * 		AMysql_Expr::LITERAL, 'CURRENT_TIMESPAMP'
	 * 	);
	 *	// or
	 *	$currentTimestampBind = new AMysql_Expr('CURRENT_TIMESTAMP');
     **/         
    const LITERAL = 0;
    const EXPR_LITERAL = 0;

    /**
	 * IN() function. In this case, the 2nd parameter is the table name, the
	 * third is the array of values
	 *
	 * e.g.
	 * 	$idIn = new AMysql_Expr(AMysql_Expr::COLUMN_IN, 'id', array (
	 *		'3', '4', '6'
	 * 	));
     **/         
    const COLUMN_IN = 1;
    const EXPR_COLUMN_IN = 1;

	/**
	 * @constructor
	 * This constructor accepts different parameters in different cases, but
	 * the first parameter is mandatory: the one that gives the type of
	 * expression. The types of expressions can be found as constants on this
	 * class, and their documentation can be found above each constant type
	 * declaration.
	 * 
	 * In case of a literal string, you can just pass the literal string as
	 * the only parameter.
     **/         
	public function __construct(/* args */) {
        $args = func_get_args();
        // literál
        if (is_string($args[0])) {
            $prepared = $args[0];
        }
        else {
            switch ($args[0]) {
                case self::EXPR_LITERAL:
                    $prepared = $args[1];
                case self::EXPR_COLUMN_IN: {
                    $prepared = '';
                    if ($args[2]) {
                        $prepared = AMysql::escapeTable($args[1]) . ' IN 
                        (' . join(', ', $args[2]) . ') ';
                    }
					else {
						// If the array is empty, don't break the WHERE syntax
						$prepared = 0;
					}
                }
            }
        }
        $this->prepared = $prepared;
    }
    
    public function toString() {
        return $this->prepared;
    }
    
    public function __toString() {
        return $this->toString();
    }
} 
?>
