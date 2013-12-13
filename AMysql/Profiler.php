<?php
/**
 * This class is so that you could access the query profile data within
 * your view containing the information on the latest queries without
 * having to pass the entire AMysql object onto the view.
 *
 * The problem with simply calling $amysql->getQueriesData() is that you
 * may be using a framework that doesn't allow you to set a hook for after
 * resolving the controllers, but before rendering a view. With this class,
 * you can just fetch it by $amysql->getProfiler(), assign it to view, and
 * access the latest profiler data with $profiler['queriesData'] and
 * $profiler['totalTime'].
 *
 * Enable the profiler by setting the AMysql object's profileQueries
 * proterty to true.
 * 
 * Visit https://github.com/amcsi/amysql
 * @author      Szerémi Attila
 * @license     MIT License; http://www.opensource.org/licenses/mit-license.php
 */
class AMysql_Profiler implements ArrayAccess
{
    protected $amysql;

    public function __construct(AMysql_Abstract $amysql)
    {
        $this->amysql = $amysql;
    }

    public function setEnabled($enabled)
    {
        $this->amysql->profileQueries = !!$enabled;
    }

    /**
     * Gets the profile data as an HTML table with by a template shipped with
     * this library.
     * Can also be called with the help of ArrayAccess via $this['asHtml']
     * 
     * @access public
     * @return string
     */
    public function getAsHtml()
    {
        if (!$encoding) {
            $encoding = $this->defaultEncoding;
        }
        $tplBaseDir = dirname(__FILE__) . '/../tpl';
        $filename = "$tplBaseDir/profileTemplate.php";
        $profiler = $this;
        ob_start();
        include $filename;
        $html = ob_get_clean();
        return $html;
    }

    /**
     * Gets the profile data as an array.
     * Can also be called with the help of ArrayAccess via $this['asArray']
     * 
     * @access public
     * @return array
     */
    public function getAsArray()
    {
        return array(
            'totalTime' => $this['totalTime'],
            'queriesData' => $this['queriesData']
        );
    }

    public function offsetExists($key)
    {
        return in_array(
            $key,
            array(
                'asHtml', 'totalTime', 'queriesData'
            )
        );
    }

    public function offsetGet($key)
    {
        switch ($key) {
            case 'totalTime':
                return $this->amysql->totalTime;
                break;
            case 'queriesData':
                return $this->amysql->getQueriesData();
                break;
            case 'asHtml':
                return $this->getAsHtml();
                break;
            case 'asArray':
                return $this->getAsArray();
                break;
            default:
                trigger_error("Invalid key: `$key`.");
                break;
        }
    }

    public function offsetSet($key, $value)
    {
        trigger_error("Access denied. Cannot set key `$key`.", E_USER_WARNING);
    }

    public function offsetUnset($key)
    {
        trigger_error(
            "Access denied. Cannot unset key `$key`.",
            E_USER_WARNING
        );
        return false;
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }
}
