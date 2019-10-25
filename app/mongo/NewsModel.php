<?php

namespace app\mongo;


class NewsModel extends BaseModel
{

    protected $name = 'news';

    /**
     * 获取总数
     * @param $where
     * @return mixed
     */
    public function getCount($where = array())
    {
        $this->formatWhere($where);
        return $this->mongo->oldWhere($where)->count();
    }

    /**
     * 获取列表
     * @param $where
     * @param $start
     * @param $limit
     * @param string $order
     * @return mixed
     */
    public function getList($where, $start, $limit, $order = array('_id' => -1))
    {
        $this->formatWhere($where);
        $this->mongo->field('_id, title, image_path, update_time, create_time, status');
        $result = $this->mongo->oldWhere($where)->limit($start, $limit)->order($order)->select();
        $this->formatResult($result);
        return $result;
    }

    /**
     * 获取上页下页的id
     * @param $id
     * @return array
     */
    public function getPrevAndNext($id)
    {
        $obj = new self();
        $prev = $obj->mongo->field('_id')->oldWhere(array('_id' => array('gt', $id)))->order(array('_id' => 1))->find();
        $obj = new self();
        $next = $obj->mongo->field('_id')->oldWhere(array('_id' => array('lt', $id)))->order(array('_id' => -1))->find();
        $prev = $prev ? $prev['_id'] : 0;
        $next = $next ? $next['_id'] : 0;
        return array($prev, $next);
    }

    protected function formatWhere(&$where)
    {
        if(isset($where['end_time']))
        {
            foreach($where['end_time'] as &$item)
            {
                $item[1] = new \MongoDB\BSON\UTCDateTime($item[1] * 1000);
            }
            unset($item);
        }
        if(isset($where['id']))
        {
            $where['_id'] = $where['id'];
            unset($where['id']);
        }
    }

    protected function formatData(&$data)
    {
        if(isset($data['id'])) {
            $data['_id'] = (int) $data['id'];
            unset($data['id']);
        }
        foreach (array('create_user', 'update_user', 'status', 'sort', 'has_read', 'lang') as $item) {
            if(isset($data[$item])) {
                $data[$item] = (int) $data[$item];
            }
        }
        foreach (array('create_time', 'update_time') as $item) {
            if(isset($data[$item])) {
                $data[$item] = new \MongoDB\BSON\UTCDateTime($data[$item] * 1000);
            }
        }
    }

    protected function formatResult(&$result)
    {
        foreach($result as &$item)
        {
            if(isset($item['_id']))
            {
                $item['id'] = $item['_id'];
                unset($item['_id']);
            }
            if(isset($item['create_time']))
            {
                $item['create_time'] = ($item['create_time'])->toDateTime()->getTimestamp();
            }
            if(isset($item['update_time']))
            {
                $item['update_time'] = ($item['update_time'])->toDateTime()->getTimestamp();
            }
        }
        unset($item);
    }

    public function insert(array $data)
    {
        $this->formatData($data);
        return $this->mongo->insert($data);
    }

    public function find($id)
    {
        $data = $this->mongo->find($id);
        $data = array($data);
        $this->formatResult($data);
        return reset($data);
    }

}
