<?php
class AMysql_Iterator implements SeekableIterator, Countable
{
    
    protected $_stmt;
    protected $_count;
    protected $_lastFetch;
    protected $_currentIndex = 0;
    protected $_resultIndex = 0;

    public function __construct(AMysql_Statement $stmt) {
	$count = $stmt->numRows();
	$this->_stmt = $stmt;
	$this->_count = $count;
    }

    public function current() {
	if ($this->_resultIndex == $this->_currentIndex + 1) {
	    return $this->_lastFetch;
	}
	$ret = $this->_stmt->fetch();
	$this->_resultIndex++;
	$this->_lastFetch = $ret;
	return $ret;
    }

    public function key() {
	return $this->_currentIndex;
    }

    public function next() {
	$this->_currentIndex++;
    }

    public function rewind() {
	if ($this->_count) {
	    $this->seek(0);
	}
    }

    public function valid() {
	if (0 <= $this->_currentIndex && $this->_currentIndex < $this->_count) {
	    return true;
	}
	return false;
    }

    public function seek($index) {
	if (0 <= $index && $index < $this->_count) {
	    mysql_data_seek($this->_stmt->result, $index);
	    $this->_resultIndex = $index;
	    $this->_currentIndex = $index;
	}
	else {
	    throw new OutOfBoundsException("Cannot seek to position `$index`.");
	}
    }

    public function count() {
	return $this->_count;
    }
}
