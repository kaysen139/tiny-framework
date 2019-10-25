<?php
return array(
    'MONGO_DB_CONFIG'   => array(
        'type'              => '\\framework\\Mongo\\Connection', // 数据库类型
        'hostname'          => '127.0.0.1', // 服务器地址
        'database'          => 'cs', // 数据库名
        'username'          => '', // 用户名
        'password'          => '', // 密码
        'hostport'          => '27017', // 端口
        'dsn'             => '',
        'params'          => array(),
        'charset'         => 'utf8',
        'prefix'          => 'cs_',
        'debug'           => true, // 想要 getLastSql()这里必须要打开
        'deploy'          => 0,
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'read_master'     => false,
        'fields_strict'   => true,
        'resultset_type'  => 'array',
        'auto_timestamp'  => false,
        'datetime_format' => 'Y-m-d H:i:s',
        'sql_explain'     => false,
        'builder'         => '',
        'query'           => '\\framework\\Mongo\\Query',
        'break_reconnect' => false,
        'break_match_str' => array(),
    ),

    'MYSQL_CONFIG'  => array(
        'type'              => 'mysql',
        'host'              => '127.0.0.1',
        'username'          => 'root',
        'port'              => '3306',
        'password'          => '123456',
        'dbname'            => 'cs',
        'charset'           => 'utf8',
        'prefix'            => 'cs_',
    ),
);
