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
 * @version 0.9
 **/ 
class AMysql_Expr {

    public $prepared;

    public $amysql;

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
     * Escapes wildcards for a LIKE statement.
     * The second parameter has to be the string to escape, and
     * the third is optional, and is the sprintf format of the string,
     * where literal wildcards should appear. The default is %%%s%%,
     * where the input of "something" will result in:
     *	"'%something%' ESCAPE '='"
     **/         
    const ESCAPE_LIKE = 2;

    /**
     * @constructor
     * This constructor accepts different parameters in different cases.
     * Before everything, if the first parameter is an AMysql instance, it
     * is saved, and is shifted from the arguments array, and the following applies
     * in either case:
     * The first parameter is mandatory: the one that gives the type of
     * expression. The types of expressions can be found as constants on this
     * class, and their documentation can be found above each constant type
     * declaration.
     * 
     * In case of a literal string, you can just pass the literal string as
     * the only parameter.
     **/         
    public function __construct(/* args */) {
	$args = func_get_args();
	if ($args[0] instanceof AMysql) {
	    $this->amysql = array_shift($args);
	}
	if ($args) {
	    call_user_func_array(array($this, 'set'), $args);
	}
    }

    public function set() {
	$args = func_get_args();
	// literál
	if (is_string($args[0])) {
	    $prepared = $args[0];
	}
	else {
	    switch ($args[0]) {
	    case self::EXPR_LITERAL:
		$prepared = $args[1];
		break;
	    case self::EXPR_COLUMN_IN:
		$prepared = '';
		if ($args[2]) {
		    $prepared = AMysql::escapeIdentifier($args[1]) . ' IN 
			(' . join(', ', $args[2]) . ') ';
		}
		else {
		    // If the array is empty, don't break the WHERE syntax
		    $prepared = 0;
		}
		break;
	    case self::ESCAPE_LIKE:
		$format = '%%%s%%';
		if (!empty($args[2])) {
		    $format = $args[2];
		}
		$likeEscaped = AMysql::escapeLike($args[1]);
		$formatted = sprintf($format, $likeEscaped);
		$escaped = mysql_real_escape_string($formatted);
		$prepared = "'$escaped'";
		$prepared .= " ESCAPE '='";
		break;
	    default:
		throw new Exception("No such expression type: `$args[0]`.");
		break;
	    }
	}
	$this->prepared = $prepared;
    }

    public function toString() {
	if (!isset($this->prepared)) {
	    throw new Exception ("No prepared string for mysql expression.");
	}
	return $this->prepared;
    }

    public function __toString() {
	return $this->toString();
    }
} 
?>
