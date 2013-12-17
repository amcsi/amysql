<?php
class ExceptionTest extends AMysql_TestCase
{
    public function testGetProperties()
    {
        $amysql = $this->_amysql;
        $data = array(
            'id' => 1,
            'string' => 'bla'
        );
        $amysql->insert($this->tableName, $data);
        try {
            $amysql->insert($this->tableName, $data);
            $this->fail('An exception should be thrown');
        } catch (AMysql_Exception $e) {
            $this->assertEquals(
                AMysql_Exception::CODE_DUPLICATE_ENTRY,
                $e->getCode()
            );
            $props = $e->getProperties();
            $this->assertEquals(array('1', 'PRIMARY'), $props);
        }
    }
}
?>
