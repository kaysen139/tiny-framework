<?php

namespace framework;

class view
{
    private $_data;
    private $_action;
    private $_controller;
    private $_viewPath;

    public function __construct($controller, $action)
    {
        $this->_controller = $controller;
        $this->_action = $action;
    }

    public function assign($key, $value)
    {
        $this->_data[$key] = $value;
    }

    //渲染视图
    public function render()
    {
        $this->_viewPath = APP_PATH . 'app/view/' . $this->_controller . '/' . $this->_action . ".php";
        if (!file_exists($this->_viewPath)) {
            exit('模板文件不存在');
        }
        if($this->_data)
        {
            extract($this->_data);
        }
        include "$this->_viewPath";
    }
}
