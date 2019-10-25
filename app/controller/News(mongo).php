<?php

namespace app\controller;

use app\mongo\NewsModel;
use framework\BaseController;
use framework\Mongo\MDb;
use framework\Page;

class News extends BaseController
{

    const KEY = 'd254066903f175dafb5101f2cd3201d6';

    const DOMAIN = 'http://cs.com';

    public function index()
    {
        $where = array('status' => 1);
        $NewsModel = new NewsModel();
        $count = $NewsModel->getCount($where);
        $page = new Page($count, 5);
        $list = $NewsModel->getList($where, $page->firstRow, $page->listRows);

        foreach ($list as &$item) {
            $item['update_time'] = date('m/d', $item['create_time']);
        }
        unset($item);

        $data = array(
            'curr_page'      => $page->nowPage,
            'total_page'     => $page->totalPages,
            'total_num'      => $page->totalRows,
            'list'           => $list,
        );

        $this->ajaxSuccess('', $data);
    }

    public function save()
    {
        $data = $_POST;
        if(empty($data['id']) || empty($data['title']))
        {
            $this->ajaxError('参数错误');
        }
        $sign = md5('id='.$data['id'].'&title='.$data['title'].'&key='.self::KEY);
        if($sign != $data['sign'])
        {
            $this->ajaxError('签名错误');
        }
        unset($data['sign']);

        $NewsModel = new NewsModel();
        // mongodb 不能自动更新，只有先删除再添加
        $NewsModel->delete((int) $data['id']);
        if($NewsModel->insert($data))
        {
            $this->ajaxSuccess('success');
        }
        else
        {
            $this->ajaxError('插入失败');
        }
    }

    public function detail()
    {
        $id = (int) $_GET['id'];
        if(empty($id))
        {
            $this->ajaxError('error');
        }
        $NewsModel = new NewsModel();
        $res = $NewsModel->find($id);
        if($res)
        {
            $NewsModel->setInc('has_read', 1);
            $res['update_time'] = date('Y/m/d H:i', $res['create_time']);

            list($res['prev'], $res['next']) = $NewsModel->getPrevAndNext($id);

            $domain = self::DOMAIN;
            $res['content'] = preg_replace_callback('#\<img[^>]+src\s*=\s*(?:"|\')(.+?)(?:"|\')(?:.+?)\>#', function ($matches) use ($domain) {
                list($elem, $src) = $matches;
                if(!preg_match('/http|https/', $src))
                {
                    $elem = str_replace($src, $domain . $src, $elem);
                }
                return $elem;
            }, $res['content']);
            $this->ajaxSuccess('', $res);
        }
        else
        {
            $this->ajaxError('该文章不存在');
        }
    }

}
