# KISS Core

## What is it?

Kiss Core Microframework on PHP 5.5+ is the lightweight single file packed end-project framework for rapid and fast development highload projects.

1. Single core file in project you create 
2. Simple template engine that allows you only use blocks and iterations (easy to learn for designers)
3. Fetch mechanism to avoid join in SQL database and make cache easier (shards also ofc)
4. Independend task manager and other staff (database, any other data, logs in single folder for single project)
5. Fast routes that converts to nginx rewrite on init. Live route table in action files just with single comment
6. Deploy script to production servers


## Installation guide

### Install from scratch

```bash
git clone git@github.com:dmitrykuzmenkov/kisscore.git
cd ~/kisscore
./make-app myproj
cd ~/myproj
./run > env/log/run.log &

sudo echo '127.0.0.1 myproj.lo' >> /etc/hosts
```

Open in browser http://myproj.lo

### Create application
You can create application with single command
```bash
./make-app APPLICATION
```

This will create skeleton of your project in home folder named APPLICATION. You can pass special variable to configure building of your app.

### Variables to configure application creation
* KISS_MYSQL_PORT=8888
* KISS_MEMCACHED_SESSION_PORT=7777
* KISS_MEMCACHED_PORT=6666

For example:
```bash
export KISS_MYSQL_PORT=12345
./make-app APPLICATION
```

## Dependency

To run project using KISS Core you need

1. Linux (Ubuntu, Centos, Debina and so on)
2. PHP 5.5+ with php-fpm (http://php.net)
3. Nginx (http://nginx.org)
4. Redis (http://redis.io)
5. Memcached (http://memcached.org)
6. MariaDB 10+ (http://mariadb.org)

And also linux knowledge ;)


## More?

Feel free to ask me any question!
