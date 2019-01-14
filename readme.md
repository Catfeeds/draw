### 安装说明

1. **环境要求**
    php > 7.1.13
    mysql
    redis
    composer

2. 安装

  php安装可参考文章https://my.oschina.net/cxgphper/blog/2050504

+ 在项目根目录执行```composer update```

+ 复制 .env.example文件为.env

  ```
  // mysql设置
  DB_CONNECTION=mysql
  DB_HOST=47.110.44.33
  DB_PORT=3306
  DB_DATABASE=draw
  DB_USERNAME=root
  DB_PASSWORD=Samliang123
  
  // redis设置
  REDIS_HOST=47.110.44.33
  REDIS_PASSWORD=null
  REDIS_PORT=6379
  
  // 图片保存路径
  IMAGE_URL=https://www.sofreely.club/app/
  
  // 微信小程序
  APP_ID=wx0a14836d7cdc3451
  APP_SECRET=847dc40dd35fc8d221ead794f6057bfc
  
  // 定时释放锁定奖品
  // 语法通Linux crontab
  CRON="* * */1 * *"
  
  // 关闭debug
  APP_DEBUG=false
  
  // 运行模式 local本地  production正式
  APP_ENV=production
  
  ```

+ 在项目根目录执行

  ```
  php artisan key:generate
  php artisan jwt:secret
  ```

+ nginx 配置

  root 需要只向 ${PROJECT}/public目录

  ```
  server {
          listen 443;
          server_name sofreely.club;
  
          root /usr/share/nginx/html/draw/public;
  
          location / {
              index  index.php index.html index.htm;
              try_files $uri $uri/ /index.php?$query_string;
          }
  
  
          location ~ /.well-known {
              allow all;
          }
  
          location ~ \.php$ {
              root /usr/share/nginx/html/draw/public;
              fastcgi_pass   127.0.0.1:9000;
              fastcgi_index  index.php;
              fastcgi_param  SCRIPT_FILENAME  										        $document_root$fastcgi_script_name;
              include        fastcgi_params;
          }
  }
  
  ```
