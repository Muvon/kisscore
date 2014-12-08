# KISS Core

## What is it?

Kiss Core Microframework on PHP 5.5+ is the lightweight single file packed end-project framework for rapid development of very fast projects.

1. Single core file in project you create 
2. Simple and very fast template engine that allows you only use blocks and iterations (easy to learn for designers)
3. Independend task manager
4. Fast routes that converts to nginx rewrite on init. Live route table in action files just with single comment
5. Minimum dependecy and maximum profit
6. Plugins with nice approach for easy extend
7. Deploy script to production servers in parallels!


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

That will create your application in folder myproj under home dir and start php-fpm in project dir.

Setup domain for local test:
```bash
sudo echo '127.0.0.1 myproj.lo' >> /etc/hosts
```

Finaly done! Just open in your browser http://myproj.lo

Easy, right? Yeah! :)

## Tasks
You can add your own tasks run under project dir in file tasks. Just put every process in new line. After tasks file modification just restart it using special script
```bash
./restart
```

Now process manager must start your own task. enjoy :)

## Plugins

There are special plugins to use DB, Cache and other cool staff in KISSCore.
You can find plugins and help on github here: https://github.com/dmitrykuzmenkov/kisscore-plugins

## Dependency

To run project using KISS Core you need

1. Linux (Ubuntu, Centos, Debina and so on)
2. PHP 5.5+ with php-fpm (http://php.net)
  * igbinary
3. Nginx (http://nginx.org)

And also linux knowledge ;)

You also can check requirements on your system using this bash command in kisscore folder:
```bash
./check
```

## More?

Feel free to ask me any question!
