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

### Get KISS Core package

Just clone it from git:
```bash
git clone git@github.com:dmitrykuzmenkov/kisscore.git
```

### New application
You can create application with single command in KISS Core dir:
```bash
./make-app myproj
```

That will create your application in folder myproj under home dir. After that your need to run tasks in new application. Just go to into that dir and start run daemon:
```bash
cd ~/myproj
./run > env/log/run.log &
```

Setup domain for local test:
```bash
sudo echo '127.0.0.1 myproj.lo' >> /etc/hosts
```

Finaly done! Just open in your browser http://myproj.lo

### Extra config before application creation
There are special variables to set bunch of params that is used by make-app script:
* KISS_MYSQL_PORT=8888
* KISS_MEMCACHED_SESSION_PORT=7777
* KISS_MEMCACHED_PORT=6666

You can use it like this for example:
```bash
export KISS_MYSQL_PORT=12345
./make-app APPLICATION
```

This will create your project with mysql database on custom port 12345.

## Dependency

To run project using KISS Core you need

1. Linux (Ubuntu, Centos, Debina and so on)
2. PHP 5.5+ with php-fpm (http://php.net)
  * PDO
  * Memcache
  * igbinary
3. Nginx (http://nginx.org)
4. Memcached (http://memcached.org)
5. MariaDB 10+ (http://mariadb.org)

And also linux knowledge ;)


## More?

Feel free to ask me any question!
