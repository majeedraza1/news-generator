<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

/**
 * Language class
 */
class Language {
	/**
	 * Get all countries codes
	 *
	 * @return array
	 */
	public static function all(): array {
		return array_keys( self::languages() );
	}

	/**
	 * All supported languages
	 *
	 * @return string[]
	 */
	public static function languages(): array {
		return [
			'eng' => 'English',
			'spa' => 'Spanish',
			'cat' => 'Catalan',
			'por' => 'Portuguese',
			'deu' => 'German',
			'ita' => 'Italian',
			'slv' => 'Slovene',
			'hrv' => 'Croatian',
			'srp' => 'Serbian',
			'sqi' => 'Albanian',
			'fra' => 'French',
			'ces' => 'Czech',
			'slk' => 'Slovak',
			'eus' => 'Basque',
			'gle' => 'Irish',
			'pol' => 'Polish',
			'hun' => 'Hungarian',
			'nld' => 'Dutch',
			'gsw' => 'Swiss German',
			'swe' => 'Swedish',
			'fin' => 'Finnish',
			'nob' => 'Norwegian',
			'lav' => 'Latvian',
			'lit' => 'Lithuanian',
			'est' => 'Estonian',
			'isl' => 'Icelandic',
			'dan' => 'Danish',
			'ell' => 'Greek',
			'ron' => 'Romanian',
			'bul' => 'Bulgarian',
			'kat' => 'Georgian',
			'rus' => 'Russian',
			'ara' => 'Arabic',
			'tur' => 'Turkish',
			'ind' => 'Indonesian',
			'vie' => 'Vietnamese',
			'ukr' => 'Ukrainian',
			'bel' => 'Belarusian',
			'hye' => 'Armenian',
			'zho' => 'Chinese',
			'jpn' => 'Japanese',
			'kor' => 'Korean',
			'aze' => 'Azerbaijani',
			'kaz' => 'Kazakh',
			'heb' => 'Hebrew',
			'fas' => 'Persian',
			'kur' => 'Kurdish',
			'hin' => 'Hindi',
			'urd' => 'Urdu',
			'kan' => 'Kannada',
			'ben' => 'Bengali',
			'mal' => 'Malayalam',
			'tha' => 'Thai',
			'mar' => 'Marathi',
			'tam' => 'Tamil',
			'pan' => 'Panjabi',
			'guj' => 'Gujarati',
		];
	}

	/**
	 * Check if language exists
	 *
	 * @param string|null $code
	 *
	 * @return bool
	 */
	public static function exists( ?string $code ): bool {
		return array_key_exists( strtolower( $code ), self::languages() );
	}
}
