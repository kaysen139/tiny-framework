<?php

function config($name)
{
    static $config = array();
    if (empty($config)) {
        $config = include APP_PATH . 'app/config.php';
    }

    return $config[$name];
}

function parse_name($name, $type = 0)
{
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}
