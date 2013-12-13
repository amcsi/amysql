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
class AMysql_Profiler extends ArrayObject
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

    public function toArray()
    {
        return array(
            'totalTime' => $this['totalTime'],
            'queriesData' => $this['queriesData']
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
            default:
                trigger_error("Invalid key: `$key`.");
                break;
        }
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }
}
