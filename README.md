# KISS Core
[![Code Climate](https://codeclimate.com/github/dmitrykuzmenkov/kisscore/badges/gpa.svg)](https://codeclimate.com/github/dmitrykuzmenkov/kisscore)

## What is it?

Kiss Core Microframework on PHP 7+ is the lightweight single file packed end-project framework for rapid development of very fast projects.

1. Single core file in project you create
2. Simple and very fast template engine that allows you only use blocks and iterations (easy to learn for designers)
3. Fast routes that converts to nginx rewrite on init. Live route table in action files just with single comment
4. Minimum dependecy and maximum profit
5. Plugins with nice approach for easy extend
6. Deploy script to production servers in parallels!


## Installation guide

### Get KISS Core package

Just clone it from git:
```bash
git clone git@github.com:dmitrykuzmenkov/kisscore.git
```

### New application
First you should update your php-fpm and nginx global config to include projects files.
Add the following lines to php-fpm config:
```bash
include = /home/*/*/env/etc/php-fpm.conf
```

And the following lines for nginx config:
```bash
include /home/*/*/env/etc/nginx.conf;
```

Now you can create application with single command in KISS Core dir:
```bash
./create-app ~/Work/myproj
```

That will create your application in folder myproj under home dir, prepare all it environment in local machine and restart php-fpm and nginx.
So be sure that you correctly configured your php-fpm and nginx on your server like I wrote before.

Setup domain for local test:
```bash
sudo echo '127.0.0.1 myproj.lo' >> /etc/hosts
```

Restart services nginx and php-fpm and enjoy in you browser opening your project http://myproj.lo

## Running using Docker containers
You can simple run newly created project using [Yoda](https://github.com/dmitrykuzmenkov/yoda) with [Docker](https://docker.com).
Just install Yoda, change dir of your current project and run in your bash shell:
```bash
yoda start
```

## Update core in existing application
You also can update compiled KISSCore file in your application.
Just run install-core and enjoy!
```bash
./install-core ~/Work/myproj
```

## Folder structure
### Root folders structure
| Folder | Description                                                                            |
|--------|----------------------------------------------------------------------------------------|
| app    | Main project folder with source code, libraries, static and KISSCore                   |
| docker | This folder contains Yoda configs to start project using docker containers             |
| env    | Environment folder with tmp files, generated maps, configs and other special env stuff |

### app skeleton
| Folder         | Description                                                    | Namespace                     |
|----------------|----------------------------------------------------------------|-------------------------------|
| actions        | Action dir, it contains of action files that includes on route |                               |
| bin            | Special bash scripts and bin files                             |                               |
| config         | Config folder with all configs templates and main: app.ini.tpl |                               |
| core.php       | Core file with merged KISSCore classes and functions           |                               |
| frontend.php   | That files handle all nginx dynamic requests (front point)     |                               |
| lib            | Library sources that does not depend on KISSCore               | Lib                           |
| main.php       | Thats is type of front controller, but just a simple flat file |                               |
| plugin         | Plugins made special for KISSCore under Plugin namespace       | Plugin                        |
| src            | All your source code, structured as you want                   | App                           |
| static         | Root nginx dir for static files                                |                               |
| triggers       | Special triggers. It contains flat files with some annotation  |                               |
| vendor         | Thrird party libraries namespaced with PSR-4                   | All other not included before |
| views          | Templates for rendering from action                            |                               |

### env skeleton
| Folder | Description                                                              |
|--------|--------------------------------------------------------------------------|
| bin    | Symlink to app/bin                                                       |
| etc    | Generated configs that uses by application and services                  |
| files  | Static files. You can use it just a like storage                         |
| log    | All logs go here                                                         |
| run    | Pid files with running processes, some special files that depends on run |
| tmp    | It contains temporary files                                              |
| var    | Some application data, configured maps and so on                         |

## Environment variables
- $PROJECT - Your project name
- $PROJECT_DIR - home dir of your project (~/$PROJECT)
- $APP_DIR - application dir with code
- $ENV_DIR - environment dir
- $RUN_DIR - run directory (pid files of running processes and so on)
- $VAR_DIR - Var dir
- $TMP_DIR - for temp files
- $LOG_DIR - all logs from project collect here
- $CONFIG_DIR - configuration files
- $BIN_DIR - for special bin files and scripts
- $STATIC_DIR - public directory for static files
- $KISS_CORE - path to core.php file with KISSCore classes
- $HTTP_HOST - hostname of the machine

## Plugins

There are special plugins to use DB, Cache and other cool staff in KISSCore.  
You can find plugins and help on my github page: https://github.com/dmitrykuzmenkov  
All plugins named in pattern kisscore-plugin-{{name}}

## Actions
All actions are in app/actions folder. You should just create any file, for example test.php and put code in it.
```php
<?php
/**
 * @route test
 * @param string $hello
 */

return 'Hello';
```

If action returns 1 as integer or no return statement then kisscore will try to include same name template and render it using View.  
If action returns string then it will be rendered as is.  
If action returns object or array then it will be rendered as json encoded string.  

Reinit your app and open in project http://myproj/test that will execute this action.  

## Triggers

There are special functionality to trigger some event, catch it and do something. It works like hooks.
First you call trigger_event('test', ['var' => 'test']) in any place of your code. Then you create special trigger file in app/triggers folder.
Annotate trigger with special comments, for example:
```php
<?php
/**
 * @trigger test
 * @param string $var
 */
 echo $var;
```

Prepare environment with *init* call and thats finally done. You now can *trigger_event* and see the result of var you passed in data of event.

## Dependency

To run project using KISS Core you need

1. Only PHP 7+ to run php code :)

or

1. [PHP 7+ with php-fpm](http://php.net)
2. [Nginx](https://nginx.org)
3. [Yoda](https://github.com/dmitrykuzmenkov/yoda) for isolation using [Docker](https://docker.com)

to handle web requests

And also some linux knowledge ;)

## How to extend?
Just use lib dir into your application folder.
You can put there any external module and use it into your project,
KISS core allow you to start MVC fast application in just couple of minutes with minimum dependencies. But you can extends it infinite for sure :) Just try Keep It Simple as possible!

## More?

Feel free to ask me any question!
