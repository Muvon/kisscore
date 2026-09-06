<?php return [
	'common.zones' => ['www', 'api'],
	'common.domain' => 'example.com',
	// Id generation reads this. It has to live in the SHARED fixture rather
	// than a second one: config() memoizes on its first call, so whichever
	// suite calls first decides the config for the whole process.
	'common.epoch' => 1_700_000_000,
];
