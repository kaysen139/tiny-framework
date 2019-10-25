<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace framework\Mongo;

use MongoDB\BSON\Javascript;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Query as MongoQuery;
use Exception;

class Builder
{
    // connection对象实例
    protected $connection;
    // 最后插入ID
    protected $insertId = array();
    // 查询表达式
    protected $exp = ['<>' => 'ne', 'neq' => 'ne', '=' => 'eq', '>' => 'gt', '>=' => 'gte', 'egt' => 'gte', '<' => 'lt', '<=' => 'lte', 'elt' => 'lte', 'in' => 'in', 'not in' => 'nin', 'nin' => 'nin', 'mod' => 'mod', 'exists' => 'exists', 'null' => 'null', 'notnull' => 'not null', 'not null' => 'not null', 'regex' => 'regex', 'type' => 'type', 'all' => 'all', '> time' => '> time', '< time' => '< time', 'between' => 'between', 'not between' => 'not between', 'between time' => 'between time', 'not between time' => 'not between time', 'notbetween time' => 'not between time', 'like' => 'like', 'near' => 'near', 'size' => 'size'];

    /**
     * 架构函数
     * @access public
     * @param Connection    $connection 数据库连接对象实例
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * 获取当前的连接对象实例
     * @access public
     * @return void
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * key分析
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey($key)
    {
        if (0 === strpos($key, '__TABLE__.')) {
            list($collection, $key) = explode('.', $key, 2);
        }

        if ('id' == $key && $this->connection->getConfig('pk_convert_id')) {
            $key = '_id';
        }

        return trim($key);
    }

    /**
     * value分析
     * @access protected
     * @param Query     $query 查询对象
     * @param mixed     $value
     * @param string    $field
     * @return string
     */
    protected function parseValue(Query $query, $value, $field = '')
    {
//        if ('_id' == $field && 'ObjectID' == $this->connection->getConfig('pk_type') && is_string($value)) {
//            try {
//                return new ObjectID($value);
//            } catch (InvalidArgumentException $e) {
//                return new ObjectID();
//            }
//        }

        return $value;
    }

    /**
     * insert数据分析
     * @access protected
     * @param Query $query 查询对象
     * @param array $data 数据
     * @return array
     */
    protected function parseData(Query $query, $data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $val) {
            $item = $this->parseKey($key);

            if (is_object($val)) {
                $result[$item] = $val;
            } elseif (isset($val[0]) && 'exp' === $val[0]) {
                $result[$item] = $val[1];
            } elseif (is_null($val)) {
                $result[$item] = 'NULL';
            } else {
                $result[$item] = $this->parseValue($query, $val, $key);
            }
        }

        return $result;
    }

    /**
     * Set数据分析
     * @access protected
     * @param Query $query 查询对象
     * @param array $data 数据
     * @return array
     */
    protected function parseSet(Query $query, $data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $val) {
            $item = $this->parseKey($key);

            if (is_array($val) && isset($val[0]) && is_string($val[0]) && 0 === strpos($val[0], '$')) {
                $result[$val[0]][$item] = $this->parseValue($query, $val[1], $key);
            } else {
                $result['$set'][$item] = $this->parseValue($query, $val, $key);
            }
        }

        return $result;
    }

    /**
     * 生成查询过滤条件
     * @access public
     * @param Query $query 查询对象
     * @param mixed $where
     * @return array
     */
    public function parseWhere(Query $query, $where)
    {
        if (empty($where)) {
            $where = [];
        }

        $filter = [];
        foreach ($where as $logic => $val) {
            foreach ($val as $field => $value) {
                if (is_array($value)) {
                    if (key($value) !== 0) {
                        throw new Exception('where express error:' . var_export($value, true));
                    }
                    $field = array_shift($value);
                } elseif (!($value instanceof \Closure)) {
                    throw new Exception('where express error:' . var_export($value, true));
                }

                if ($value instanceof \Closure) {
                    // 使用闭包查询
                    $query = new Query($this->connection);
                    call_user_func_array($value, [ & $query]);
                    $filter[$logic][] = $this->parseWhere($query, $query->getOptions('where'));
                } else {
                    if (strpos($field, '|')) {
                        // 不同字段使用相同查询条件（OR）
                        $array = explode('|', $field);
                        foreach ($array as $k) {
                            $filter['$or'][] = $this->parseWhereItem($query, $k, $value);
                        }
                    } elseif (strpos($field, '&')) {
                        // 不同字段使用相同查询条件（AND）
                        $array = explode('&', $field);
                        foreach ($array as $k) {
                            $filter['$and'][] = $this->parseWhereItem($query, $k, $value);
                        }
                    } else {
                        // 对字段使用表达式查询
                        $field            = is_string($field) ? $field : '';
                        $filter[$logic][] = $this->parseWhereItem($query, $field, $value);
                    }
                }
            }
        }

        $options = $query->getOptions();
        if (!empty($options['soft_delete'])) {
            // 附加软删除条件
            list($field, $condition) = $options['soft_delete'];
            $filter['$and'][]        = $this->parseWhereItem($query, $field, $condition);
        }

        return $filter;
    }

    // where子单元分析
    protected function parseWhereItem(Query $query, $field, $val)
    {
        $key = $field ? $this->parseKey($field) : '';
        // 查询规则和条件
        if (!is_array($val)) {
            $val = ['=', $val];
        }
        list($exp, $value) = $val;

        // 对一个字段使用多个查询条件
        if (is_array($exp)) {
            $data = [];
            foreach ($val as $value) {
                $exp   = $value[0];
                $value = $value[1];
                if (!in_array($exp, $this->exp)) {
                    $exp = strtolower($exp);
                    if (isset($this->exp[$exp])) {
                        $exp = $this->exp[$exp];
                    }
                }
                $k        = '$' . $exp;
                $data[$k] = $value;
            }
            $result[$key] = $data;
            return $result;
        } elseif (!in_array($exp, $this->exp)) {
            $exp = strtolower($exp);
            if (isset($this->exp[$exp])) {
                $exp = $this->exp[$exp];
            } else {
                throw new Exception('where express error:' . $exp);
            }
        }

        $result = [];
        if ('=' == $exp) {
            // 普通查询
            $result[$key] = $this->parseValue($query, $value, $key);
        } elseif (in_array($exp, ['neq', 'ne', 'gt', 'egt', 'gte', 'lt', 'lte', 'elt', 'mod'])) {
            // 比较运算
            $k            = '$' . $exp;
            $result[$key] = [$k => $this->parseValue($query, $value, $key)];
        } elseif ('null' == $exp) {
            // NULL 查询
            $result[$key] = null;
        } elseif ('not null' == $exp) {
            $result[$key] = ['$ne' => null];
        } elseif ('all' == $exp) {
            // 满足所有指定条件
            $result[$key] = ['$all', $this->parseValue($query, $value, $key)];
        } elseif ('between' == $exp) {
            // 区间查询
            $value        = is_array($value) ? $value : explode(',', $value);
            $result[$key] = ['$gte' => $this->parseValue($query, $value[0], $key), '$lte' => $this->parseValue($query, $value[1], $key)];
        } elseif ('not between' == $exp) {
            // 范围查询
            $value        = is_array($value) ? $value : explode(',', $value);
            $result[$key] = ['$lt' => $this->parseValue($query, $value[0], $key), '$gt' => $this->parseValue($query, $value[1], $key)];
        } elseif ('exists' == $exp) {
            // 字段是否存在
            $result[$key] = ['$exists' => (bool) $value];
        } elseif ('type' == $exp) {
            // 类型查询
            $result[$key] = ['$type' => intval($value)];
        } elseif ('exp' == $exp) {
            // 表达式查询
            $result['$where'] = $value instanceof Javascript ? $value : new Javascript($value);
        } elseif ('like' == $exp) {
            // 模糊查询 采用正则方式
            $result[$key] = $value instanceof Regex ? $value : new Regex($value, 'i');
        } elseif (in_array($exp, ['nin', 'in'])) {
            // IN 查询
            $value = is_array($value) ? $value : explode(',', $value);
            foreach ($value as $k => $val) {
                $value[$k] = $this->parseValue($query, $val, $key);
            }
            $result[$key] = ['$' . $exp => $value];
        } elseif ('regex' == $exp) {
            $result[$key] = $value instanceof Regex ? $value : new Regex($value, 'i');
        } elseif ('< time' == $exp) {
            $result[$key] = ['$lt' => $this->parseDateTime($query, $value, $field)];
        } elseif ('> time' == $exp) {
            $result[$key] = ['$gt' => $this->parseDateTime($query, $value, $field)];
        } elseif ('between time' == $exp) {
            // 区间查询
            $value        = is_array($value) ? $value : explode(',', $value);
            $result[$key] = ['$gte' => $this->parseDateTime($query, $value[0], $field), '$lte' => $this->parseDateTime($query, $value[1], $field)];
        } elseif ('not between time' == $exp) {
            // 范围查询
            $value        = is_array($value) ? $value : explode(',', $value);
            $result[$key] = ['$lt' => $this->parseDateTime($query, $value[0], $field), '$gt' => $this->parseDateTime($query, $value[1], $field)];
        } elseif ('near' == $exp) {
            // 经纬度查询
            $result[$key] = ['$near' => $this->parseValue($query, $value, $key)];
        } elseif ('size' == $exp) {
            // 元素长度查询
            $result[$key] = ['$size' => intval($value)];
        } else {
            // 普通查询
            $result[$key] = $this->parseValue($query, $value, $key);
        }

        return $result;
    }

    /**
     * 日期时间条件解析
     * @access protected
     * @param Query     $query 查询对象
     * @param string    $value
     * @param string    $key
     * @return string
     */
    protected function parseDateTime(Query $query, $value, $key)
    {
        // 获取时间字段类型
        $type = $this->connection->getTableInfo('', 'type');

        if (isset($type[$key])) {
            $value = strtotime($value) ?: $value;

            if (preg_match('/(datetime|timestamp)/is', $type[$key])) {
                // 日期及时间戳类型
                $value = date('Y-m-d H:i:s', $value);
            } elseif (preg_match('/(date)/is', $type[$key])) {
                // 日期及时间戳类型
                $value = date('Y-m-d', $value);
            }
        }

        return $value;
    }

    /**
     * 获取最后写入的ID 如果是insertAll方法的话 返回所有写入的ID
     * @access public
     * @return mixed
     */
    public function getLastInsID()
    {
        return $this->insertId;
    }

    /**
     * 生成insert BulkWrite对象
     * @access public
     * @param Query     $query 查询对象
     * @param array     $data 数据
     * @return BulkWrite
     */
    public function insert(Query $query, $replace = false)
    {
        // 分析并处理数据
        $options = $query->getOptions();

        $data = $this->parseData($query, $options['data']);

        $bulk = new BulkWrite;

        if ($insertId = $bulk->insert($data)) {
            $this->insertId = $insertId;
        }

        $this->log('insert', $data, $options);

        return $bulk;
    }

    /**
     * 生成insertall BulkWrite对象
     * @access public
     * @param Query $query 查询对象
     * @param array $dataSet 数据集
     * @param bool $ignore
     * @return BulkWrite
     */
    public function insertAll(Query $query, $dataSet, $ignore = false)
    {
        $bulk    = new BulkWrite(['ordered' => !$ignore]);
        $options = $query->getOptions();

        $this->insertId = [];
        foreach ($dataSet as $data) {
            // 分析并处理数据
            $data = $this->parseData($query, $data);
            if ($insertId = $bulk->insert($data)) {
                $this->insertId[] = $insertId;
            }
        }

        $this->log('insert', $dataSet, $options);

        return $bulk;
    }

    /**
     * 生成update BulkWrite对象
     * @access public
     * @param Query     $query 查询对象
     * @return BulkWrite
     */
    public function update(Query $query)
    {
        $options = $query->getOptions();

        $data  = $this->parseSet($query, $options['data']);
        $where = $this->parseWhere($query, $options['where']);

        if (1 == $options['limit']) {
            $updateOptions = ['multi' => false];
        } else {
            $updateOptions = ['multi' => true];
        }

        if (isset($options['upsert']) && $options['upsert'] == true) {
            $updateOptions['upsert'] = true;
        }

        $bulk = new BulkWrite;

        $bulk->update($where, $data, $updateOptions);

        $this->log('update', $data, $where);

        return $bulk;
    }

    /**
     * 生成delete BulkWrite对象
     * @access public
     * @param Query     $query 查询对象
     * @return BulkWrite
     */
    public function delete(Query $query)
    {
        $options = $query->getOptions();
        $where   = $this->parseWhere($query, $options['where']);

        $bulk = new BulkWrite;

        if (1 == $options['limit']) {
            $deleteOptions = ['limit' => 1];
        } else {
            $deleteOptions = ['limit' => 0];
        }

        $bulk->delete($where, $deleteOptions);

        $this->log('remove', $where, $deleteOptions);

        return $bulk;
    }

    /**
     * 生成Mongo查询对象
     * @access public
     * @param Query     $query 查询对象
     * @return MongoQuery
     */
    public function select(Query $query)
    {
        $options = $query->getOptions();

        $where = $this->parseWhere($query, $options['where']);
        $query = new MongoQuery($where, $options);

        $this->log('find', $where, $options);

        return $query;
    }

    /**
     * 生成Count命令
     * @access public
     * @param Query     $query 查询对象
     * @return Command
     */
    public function count(Query $query)
    {
        $options = $query->getOptions();

        $cmd['count'] = $options['table'];
        $cmd['query'] = $this->parseWhere($query, $options['where']);

        foreach (['hint', 'limit', 'maxTimeMS', 'skip'] as $option) {
            if (isset($options[$option])) {
                $cmd[$option] = $options[$option];
            }
        }

        $command = new Command($cmd);
        $this->log('cmd', 'count', $cmd);

        return $command;
    }

    /**
     * 聚合查询命令
     * @access public
     * @param Query     $query  查询对象
     * @param array     $extra  指令和字段
     * @return Command
     */
    public function aggregate(Query $query, $extra)
    {
        $options           = $query->getOptions();
        list($fun, $field) = $extra;

        if ('id' == $field && $this->connection->getConfig('pk_convert_id')) {
            $field = '_id';
        }

        $group = isset($options['group']) ? '$' . $options['group'] : null;

        $pipeline = [
            ['$match' => (object) $this->parseWhere($query, $options['where'])],
            ['$group' => ['_id' => $group, 'aggregate' => ['$' . $fun => '$' . $field]]],
        ];

        $cmd = [
            'aggregate'    => $options['table'],
            'allowDiskUse' => true,
            'pipeline'     => $pipeline,
            'cursor'       => new \stdClass,
        ];

        foreach (['explain', 'collation', 'bypassDocumentValidation', 'readConcern'] as $option) {
            if (isset($options[$option])) {
                $cmd[$option] = $options[$option];
            }
        }

        $command = new Command($cmd);

        $this->log('aggregate', $cmd);

        return $command;
    }

    /**
     * 多聚合查询命令, 可以对多个字段进行 group by 操作
     *
     * @param Query     $query  查询对象
     * @param array     $extra 指令和字段
     * @return Command
     */
    public function multiAggregate(Query $query, $extra)
    {
        $options = $query->getOptions();

        list($aggregate, $groupBy) = $extra;

        $groups = ['_id' => []];

        if (empty($groupBy)) {
            $groups['_id'] = null;
        } else {
            foreach ($groupBy as $field) {
                $groups['_id'][$field] = '$' . $field;
            }
        }

        foreach ($aggregate as $fun => $fields)
        {
            foreach ($fields as $alias => $field)
            {
                $groups[$alias] = ['$' . $fun => '$' . $field];
            }
        }

//        foreach ($aggregate as $fun => $field) {
//            $groups[$field . '_' . $fun] = ['$' . $fun => '$' . $field];
//        }

        $pipeline = [
            ['$match' => (object) $this->parseWhere($query, $options['where'])],
            ['$group' => $groups],
        ];

        $cmd = [
            'aggregate'    => $options['table'],
            'allowDiskUse' => true,
            'pipeline'     => $pipeline,
            'cursor'       => new \stdClass,
        ];

        foreach (['explain', 'collation', 'bypassDocumentValidation', 'readConcern'] as $option) {
            if (isset($options[$option])) {
                $cmd[$option] = $options[$option];
            }
        }

        $command = new Command($cmd);
        $this->log('group', $cmd);

        return $command;
    }

    /**
     * 自定义聚合查询
     * @param Query $query
     * @param $pipeline
     * @return Command
     * @throws Exception
     */
    public function flexAggregate(Query $query, $pipeline)
    {
        $options = $query->getOptions();

        $where = $this->parseWhere($query, $options['where']);
        if (!empty($where)) {
            array_unshift($pipeline, ['$match' => $where]);
        }

        $cmd = [
            'aggregate'    => $options['table'],
            'allowDiskUse' => true,
            'pipeline'     => $pipeline,
            'cursor'       => new \stdClass,
        ];

        foreach (['explain', 'collation', 'bypassDocumentValidation', 'readConcern'] as $option) {
            if (isset($options[$option])) {
                $cmd[$option] = $options[$option];
            }
        }

        $command = new Command($cmd);
        $this->log('group', $cmd);

        return $command;
    }


    /**
     * 生成distinct命令
     * @access public
     * @param Query     $query 查询对象
     * @param string    $field 字段名
     * @return Command
     */
    public function distinct(Query $query, $field)
    {
        $options = $query->getOptions();

        $cmd = [
            'distinct' => $options['table'],
            'key'      => $field,
        ];

        if (!empty($options['where'])) {
            $cmd['query'] = $this->parseWhere($query, $options['where']);
        }

        if (isset($options['maxTimeMS'])) {
            $cmd['maxTimeMS'] = $options['maxTimeMS'];
        }

        $command = new Command($cmd);

        $this->log('cmd', 'distinct', $cmd);

        return $command;
    }

    /**
     * 查询所有的collection
     * @access public
     * @return Command
     */
    public function listcollections()
    {
        $cmd     = ['listCollections' => 1];
        $command = new Command($cmd);

        $this->log('cmd', 'listCollections', $cmd);

        return $command;
    }

    /**
     * 查询数据表的状态信息
     * @access public
     * @param Query     $query 查询对象
     * @return Command
     */
    public function collStats(Query $query)
    {
        $options = $query->getOptions();

        $cmd     = ['collStats' => $options['table']];
        $command = new Command($cmd);

        $this->log('cmd', 'collStats', $cmd);

        return $command;
    }

    protected function log($type, $data, $options = [])
    {
        if ($this->connection->getConfig('debug')) {
            $this->connection->log($type, $data, $options);
        }
    }
}
