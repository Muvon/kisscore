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

  public static function init(Request $Request): string {
    $lang_type = config('common.lang_type');
    assert(in_array($lang_type, ['path', 'domain']));

    // Try to find current language from url match
    $lang = match($lang_type) {
      'domain' => strtok(getenv('HTTP_HOST'), '.'),
      'path' => strtok(substr($Request->getUrlPath(), 1), '/'),
      default => ''
    };

    // If we find current language we return as string
    if (isset(static::LANGUAGE_MAP[$lang])) {
      return $lang;
    }

    // No supported language found try to find in headers
    $lang = static::parse();

    $lang_domain = match($lang_type) {
      'domain' => $lang . '.' . config('common.domain'),
      'path' => config('common.domain') . '/' . $lang . '/'
    };

    $url_prefix = config('common.proto') . '://' . $lang_domain;

    $url_path = match($lang_type) {
      'domain' => $Request->getUrlPath(),
      'path' => substr($Request->getUrlPath(), 4)
    };

    Response::redirect($url_prefix . $url_path . '?' . $Request->getUrlQuery());
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
    $info = static::getInfo($lang);
    $list = [];
    foreach (static::LANGUAGE_MAP as $key => $item) {
      $list[] = [
        'language' => $key,
        'name' => static::LANGUAGE_MAP[$key],
        'is_active' => $info['language'] === $key,
      ];
    }

    return $list;
  }
}
