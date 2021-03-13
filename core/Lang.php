<?php
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

  public static function init(Request $Request): string {
    $lang_type = config('common.lang_type');
    assert(in_array($lang_type, ['path', 'domain', 'none']));
    if ($lang_type === 'none') {
      static::$is_enabled = false;
      static::$current = static::DEFAULT_LANG;
      return static::$current;
    }

    // Try to find current language from url match
    $lang = match($lang_type) {
      'domain' => strtok(getenv('HTTP_HOST'), '.'),
      'path' => strtok(substr($Request->getUrlPath(), 1), '/'),
      default => ''
    };

    // If we find current language we return as string
    if (isset(static::LANGUAGE_MAP[$lang]) && in_array($lang, config('common.languages'))) {
      static::$current = $lang;
      return static::$current;
    }

    // No supported language found try to find in headers
    static::$current = static::parse();

    $url_path = match($lang_type) {
      'domain' => $Request->getUrlPath(),
      'path' => substr($Request->getUrlPath(), 3)
    };

    $query_str = $Request->getUrlQuery();
    Response::redirect(static::getUrlPrefix() . ($url_path ? $url_path : '/') . ($query_str ? '?' . $query_str : ''));
  }

  public static function current(): string {
    return static::$current;
  }

  public static function isEnabled(): bool {
    return static::$is_enabled;
  }

  public static function getUrlPrefix(): string {
    $lang_domain = match(config('common.lang_type')) {
      'domain' => static::$current . '.' . config('common.domain'),
      'path' => config('common.domain') . '/' . static::$current,
      'none' => config('common.domain')
    };

    return config('common.proto') . '://' . $lang_domain;
  }
  /**
   * Try to parse locale from headers and auto detect it
   *
   * @return string locale that we found in headers
   */
  public static function parse(): string {
    $accept_language = getenv('HTTP_ACCEPT_LANGUAGE') ?? '';
    $languages = config('common.languages');
    foreach (static::LANGUAGE_MAP as $lang => $name) {
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
   * @return Callable
   */
  public static function getViewCompiler(string $lang): Callable {
    $lang_file = getenv('APP_DIR') . '/lang/' . $lang . '.yml';
    assert(is_file($lang_file));
    $LANG = yaml_parse_file($lang_file);
    return function ($body, $template) use ($LANG) {
      if (isset($LANG[$template])) {
        return preg_replace_callback('#\#([A-Za-z0-9_]+)\##ius', function ($matches) use ($LANG, $template) {
          return $LANG[$template][$matches[1]] ?? ($LANG['common'][$matches[1]] ?? $matches[0]);
        }, $body);
      }

      return $body;
    };
  }

  public static function getInfo(string $lang): array {
    return [
      'name' => static::LANGUAGE_MAP[$lang],
      'language' => $lang,
      'is_active' => true,
    ];
  }

  public static function getList(string $lang): array {
    $languages = config('common.languages');
    $list = [];
    foreach (static::LANGUAGE_MAP as $key => $item) {
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
}
