<?php

namespace app\mongo;

use framework\Mongo\MDb;
use framework\Mongo\Query;


/**
 * Class BaseModel
 * @package app\mongo
 * @method array cmd($command, $extra = null, $db = null)  执行command
 * @method string getLastSql() 获取sql
 */
class BaseModel
{
    protected $name;

    /**
     * @var Query
     */
    protected $mongo;

    public function __construct()
    {
        $this->mongo = MDb::name($this->name);
    }

    public function insertAll($data, $ignore = false)
    {
        return $this->mongo->insertAll($data, $ignore);
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->mongo, $method), $arguments);
    }

}