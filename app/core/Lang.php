<?php declare(strict_types=1);

final class Lang {
	const DEFAULT_LANG = 'en';

	const LANGUAGE_MAP = [
		'ru' => 'Русский',
		'en' => 'English',
		'it' => 'Italiano',
		'ko' => '한국어',
		'zh' => '中文',
		'th' => 'ไทย',
		'ar' => 'العربية',
		'ja' => '日本語',
		'vi' => 'Tiếng Việt',
		'fr' => 'Français',
		'de' => 'Deutsch',
		'es' => 'Español',
		'pt' => 'Português',
		'tl' => 'Filipino',
		'eo' => 'Esperanto',
		'eu' => 'Euskara',
		'fy' => 'Frysk',
		'ff' => 'Fula',
		'fo' => 'Føroyskt',
		'ga' => 'Gaeilge',
		'gl' => 'Galego',
		'gn' => 'Guarani',
		'ha' => 'Hausa',
		'hr' => 'Hrvatski',
		'pl' => 'Polski',
		'ro' => 'Română',
		'cs' => 'Čeština',
		'tr' => 'Türkçe',
		'fi' => 'Suomi',
		'sv' => 'Svenska',
		'el' => 'Ελληνικά',
		'be' => 'Беларуская',
		'uk' => 'Українська',
		'kk' => 'Қазақша',
	];

	protected static string $current;
	protected static bool $is_enabled = true;

	/**
	 * @param Request|string $Request
	 * @return string
	 */
	public static function init(Request|string $Request): string {
		/** @var string $lang_type */
		$lang_type = config('common.lang_type');
		assert(in_array($lang_type, ['path', 'domain', 'none']));
		if ($lang_type === 'none') {
			static::$is_enabled = false;
			static::$current = static::DEFAULT_LANG;
			return static::$current;
		}

		$lang = static::detectLanguage($Request, $lang_type);

		/** @var array<string> $configLanguages */
		$configLanguages = config('common.languages');
		if (isset(static::LANGUAGE_MAP[$lang]) && in_array($lang, $configLanguages)) {
			static::$current = $lang;
			return static::$current;
		}

		// No supported language found — parse from headers and redirect
		static::$current = static::parse();
		static::redirectToLanguage($Request, $lang_type);
	}

	/**
	 * @param Request|string $Request
	 * @param string $lang_type
	 * @return string
	 */
	protected static function detectLanguage(Request|string $Request, string $lang_type): string {
		if (is_string($Request)) {
			return $Request;
		}

		if ($lang_type === 'domain') {
			return strtok(getenv('HTTP_HOST') ?: '', '.') ?: '';
		}

		return strtok(substr($Request->getUrlPath(), 1), '/') ?: '';
	}

	/**
	 * @param Request|string $Request
	 * @param string $lang_type
	 */
	protected static function redirectToLanguage(Request|string $Request, string $lang_type): never {
		/** @var Request $Request */
		$url_path = $lang_type === 'domain'
		? $Request->getUrlPath()
		: substr($Request->getUrlPath(), 3);

		$query_str = $Request->getUrlQuery();
		Response::redirect(static::getUrlPrefix() . ($url_path ?: '/') . ($query_str ? '?' . $query_str : ''));
	}

	/**
	 * @return string
	 */
	public static function current(): string {
		return static::$current;
	}

	/**
	 * @return bool
	 */
	public static function isEnabled(): bool {
		return static::$is_enabled;
	}

	/**
	 * @return string
	 */
	public static function getUrlPrefix(): string {
		/** @var string $domain */
		$domain = config('common.domain');
		/** @var string $langType */
		$langType = config('common.lang_type');
		$lang_domain = match ($langType) {
			'domain' => static::$current . '.' . $domain,
			'path' => $domain . '/' . static::$current,
			'none' => $domain,
			default => $domain,
		};

		/** @var int $port */
		$port = config('server.port');
		if ($port !== 80) {
			$lang_domain .= ':' . $port;
		}

		/** @var string $proto */
		$proto = config('common.proto');
		return $proto . '://' . $lang_domain;
	}
  /**
   * Try to parse locale from headers and auto detect it
   *
   * @return string locale that we found in headers
   */
	public static function parse(): string {
		$accept_language = getenv('HTTP_ACCEPT_LANGUAGE') ?: '';
		/** @var array<string> $languages */
		$languages = config('common.languages');
		foreach (array_keys(static::LANGUAGE_MAP) as $lang) {
			if (!isset($languages[$lang])) {
				continue;
			}

			if (str_contains($accept_language, $lang)) {
				return $lang;
			}
		}

		return static::DEFAULT_LANG;
	}

  /**
   * Get compiler for View to replace patter with values
   *
   * @param string $lang
   * @return callable
   */
	public static function getViewCompiler(string $lang): callable {
		return function ($body, $template) use ($lang) {
			return preg_replace_callback(
				'#\#([A-Za-z0-9_]+)\##ius', function ($matches) use ($template, $lang) {
					return static::translate($template . '.' . $matches[1], $lang);
				}, $body
			);
		};
	}

	/**
	 * @param string $lang
	 * @return array{name:string,language:string,is_active:bool}
	 */
	public static function getInfo(string $lang): array {
		return [
			'name' => static::LANGUAGE_MAP[$lang],
			'language' => $lang,
			'is_active' => true,
		];
	}

	/**
	 * @param string $lang
	 * @return array<array{language:string,name:string,is_active:bool}>
	 */
	public static function getList(string $lang): array {
		/** @var array<string> $languages */
		$languages = config('common.languages');
		$list = [];
		foreach (array_keys(static::LANGUAGE_MAP) as $key) {
			if (!in_array($key, $languages)) {
				continue;
			}
			$list[] = [
				'language' => $key,
				'name' => static::LANGUAGE_MAP[$key],
				'is_active' => $lang === $key,
			];
		}

		return $list;
	}

	/**
	 * @param string $key
	 * @param ?string $lang
	 * @return string
	 */
	public static function translate(string $key, ?string $lang = null): string {
		assert(str_contains($key, '.'));
		static $map = [];
		if (!$map) {
			$lang_file = getenv('APP_DIR') . '/lang/' . ($lang ?: static::$current) . '.yml';
			assert(is_file($lang_file));
			$map = yaml_parse_file($lang_file);
		}

		[$template, $translation] = explode('.', $key);
		return $map[$template][$translation] ?? ($map['common'][$translation] ?? '[missing:' . $translation . ']');
	}
}
