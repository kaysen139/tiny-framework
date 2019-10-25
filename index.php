<?php
#ini_set('display_errors', 1);
error_reporting(0);
define('APP_PATH', __DIR__ . '/');                        //定义app根路径
spl_autoload_register('loadClass');       //自动加载方法

include APP_PATH . 'app/config.php';
include APP_PATH . 'app/functions.php';
route();

function route()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;    //获取请求参数
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : null; //获取脚本路径
    $script = trim($script, '/');
    $con = 'index';                            //获取默认访问控制器
    $func = 'index';                                 //获取默认访问方法
    $uri = trim($uri, '/');
    if (!empty($uri)) {
        $url = trim(str_replace($script, '', $uri), '/');
        $position = strpos($url, '?');                                      //清除？之后的内容
        $url = $position === false ? $url : substr($url, 0, $position);
        //获取要访问的控制器和方法
        $arr_url = explode('/', $url);
        $url_len = count($arr_url);
        if ($url_len >= 2) {
            $con = $arr_url[0];
            $func = $arr_url[1];
        } else {
            $con = $arr_url[0];
        }
    }
    $controller = "app\controller\\$con";
    $c = new $controller($con, $func);                  //实例化控制器类
    call_user_func(array($c, $func));                   //调用控制器文件中的方法
}

function loadClass($className)
{
    $class_name = str_replace('\\', '/', $className);   //反斜杠替换成正斜杠
    $file_name = $class_name . '.php';                 //加载控制器文件
    if (!file_exists($file_name)) {
        exit("Sorry, the page you are looking for could not be found.");
    }
    include_once "$file_name";
}
