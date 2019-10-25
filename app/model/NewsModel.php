<?php

namespace app\model;

use \framework\Mysql\Db;
use \framework\Mysql\PDODb;


class NewsModel
{
    /**
     * @var PDODb
     */
    private $pdo;

    private $name = 'news';

    public function __construct()
    {
        $this->pdo = Db::connect();
    }

    /**
     * 获取总数
     * @param $where
     * @return mixed
     */
    public function getCount($where = array())
    {
        $this->formatWhere($where);
        return $this->pdo->count('news');
    }

    /**
     * 获取列表
     * @param $where
     * @param $start
     * @param $limit
     * @param string $order
     * @return mixed
     */
    public function getList($where, $start, $limit, $order = 'id')
    {
        $this->formatWhere($where);
        $field = 'id, title, image_path, update_time, create_time, status';
        $result = $this->pdo->orderBy($order)->get($this->name, array($start, $limit), $field);
        return $result;
    }

    public function delete($where)
    {
        $this->formatWhere($where);
        return $this->pdo->delete('news');
    }

    /**
     * 获取上页下页的id
     * @param $id
     * @return array
     */
    public function getPrevAndNext($id)
    {
        $prev = $this->pdo->where('status', 1)
            ->where('id', $id, '>')
            ->orderBy('id', 'ASC')
            ->getOne($this->name, 'id');
        $next = $this->pdo->where('status', 1)
            ->where('id', $id, '<')
            ->orderBy('id')
            ->getOne($this->name, 'id');
        $prev = $prev ? $prev['id'] : 0;
        $next = $next ? $next['id'] : 0;
        return array($prev, $next);
    }

    public function setInc($field, $int, $id)
    {
        $data[$field] = $this->pdo->inc(1);
        return $this->pdo->where('id', $id)->update($this->name, $data);
    }

    public function insert(array $data)
    {
        return $this->pdo->insert($this->name, $data);
    }

    public function replace($data)
    {
        return $this->pdo->replace($this->name, $data);
    }

    public function find($id, $field = '*')
    {
        $data = $this->pdo->where('id', $id)->getOne($this->name, $field);
        return $data;
    }

    protected function formatWhere($where)
    {
        foreach ($where as $item) {
            call_user_func_array(array($this->pdo, 'where'), $item);
        }
    }

}
