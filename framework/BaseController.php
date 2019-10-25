<?php
namespace framework;

class BaseController
{

    private $_view;

    public function __construct($controller, $action)
    {
        $this->_view = new view($controller, $action);
    }

    //未定义方法，提示找不到页面
    public function __call($methodName, $argument)
    {
        echo "Sorry, the page you are looking for could not be found.";
    }

    //渲染视图
    public function render()
    {
        $this->_view->render();
    }

    //给视图界面设置变量
    public function assign($key, $val)
    {
        $this->_view->assign($key, $val);
    }

    /**
     * ajax返回成功
     * @param $msg
     * @param $data
     */
    protected function ajaxSuccess($msg, $data = array())
    {
        $result = array(
            'status'  => 1,
            'message' => $msg,
            'data'    => $data,
        );
        $this->ajaxReturn($result);
    }


    /**
     * ajax返回失败
     * @param $msg
     * @param $ext
     */
    protected function ajaxError($msg, $ext = '')
    {
        $result = array(
            'status'  => 0,
            'message' => $msg,
        );
        if($ext)
        {
            $result['ext'] = $ext;
        }
        $this->ajaxReturn($result);
    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
    protected function ajaxReturn($data, $type = 'JSON')
    {
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data));
            case 'XML'  :
                // 返回xml格式数据
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($data));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $handler = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');
                exit($handler . '(' . json_encode($data) . ');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
        }
    }


}
