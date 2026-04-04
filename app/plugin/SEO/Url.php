<?php declare(strict_types=1);

namespace Plugin\SEO;

final class Url {
	/**
	 * @param string $str
	 * @param array{
	 *   delimiter?:string,
	 *   limit?:int|null,
	 *   lowercase?:bool,
	 *   replacements?:array<string,string>,
	 *   transliterate?:bool,
	 * } $options
	 * @return string
	 */
	public static function getSlug(string $str, array $options = []): string {
		// Make sure string is in UTF-8 and strip invalid UTF-8 characters
		$converted = mb_convert_encoding($str, 'UTF-8', mb_list_encodings());
		$str = $converted !== false ? $converted : $str;

		$defaults = [
			'delimiter' => '-',
			'limit' => null,
			'lowercase' => true,
			'replacements' => [],
			'transliterate' => false,
		];

		// Merge options
		$options = array_merge($defaults, $options);

		$char_map = [
			// Russian
			'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
			'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
			'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
			'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э­' => 'E', 'Ю' => 'Yu',
			'Я' => 'Ya',
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
			'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
			'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
			'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
			'я' => 'ya',
		];

		// Make custom replacements
		$str = (string)preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

		// Transliterate characters to ASCII
		$str = str_replace(array_keys($char_map), $char_map, $str);

		// Replace non-alphanumeric characters with our delimiter
		$str = (string)preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

		// Remove duplicate delimiters
		$str = (string)preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

		// Truncate slug to max. characters
		$str = mb_substr($str, 0, ($options['limit'] ?: mb_strlen($str, 'UTF-8')), 'UTF-8');

		// Remove delimiter from ends
		$str = trim($str, $options['delimiter']);

		// Remove not latin letter
		$str = (string)preg_replace('/[^a-z0-9\_\-]+/ui', '', $str);

		return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
	}

}
