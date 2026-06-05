<?php return [
	['pattern' => 'home', 'zone' => 'www', 'action' => 'home', 'params' => []],
	['pattern' => 'users', 'zone' => 'api', 'action' => 'users/list', 'params' => []],
	['pattern' => 'users/(\d+)', 'zone' => 'api', 'action' => 'users/get', 'params' => ['id']],
	['pattern' => 'blog/([^/]+)/(\d+)', 'zone' => 'www', 'action' => 'blog/post', 'params' => ['slug', 'id']],
];
