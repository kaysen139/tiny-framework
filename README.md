# tiny-framework
极小的php框架 兼容 php5.3

框架参考 https://zhuanlan.zhihu.com/p/45645763

mogodb操作类从tp5扒下来的，花了点功夫
Mysql操作类使用：http://github.com/joshcam/PHP-MySQLi-Database-Class

nginx配置不需要什么特殊的，pathinfo可用可不用

```nginx
server
    {
        listen 80;
        server_name cs.com
        index  index.php default.html default.htm default.php;
        root  /data/project/php/work/ceshu;

        location / {
            if (!-e $request_filename) {
                rewrite ^(.*)$ /index.php last;
                break;
            }
        }
        
        location ~ [^/]\.php(/|$)
        {
            fastcgi_pass  unix:/tmp/php-cgi7.0.sock;
            fastcgi_index index.php;
            include fastcgi.conf;
            include pathinfo.conf;
        }

        location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
        {
            expires      30d;
        }

        location ~ .*\.(js|css)?$
        {
            expires      12h;
        }

        location ~ /.well-known {
            allow all;
        }

        location ~ /\.
        {
            deny all;
        }

    }

```
