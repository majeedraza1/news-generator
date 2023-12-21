<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

class Country {
	/**
	 * Get all countries codes
	 *
	 * @return array
	 */
	public static function all(): array {
		return array_keys( self::countries() );
	}

	public static function countries(): array {
		return [
			'ae' => 'United Arab Emirates',
			'ar' => 'Argentina',
			'at' => 'Austria',
			'au' => 'Australia',
			'be' => 'Belgium',
			'bg' => 'Bulgaria',
			'br' => 'Brazil',
			'ca' => 'Canada',
			'ch' => 'Switzerland',
			'cn' => 'China',
			'co' => 'Colombia',
			'cu' => 'Cuba',
			'cz' => 'Czech Republic',
			'de' => 'Germany',
			'eg' => 'Egypt',
			'fr' => 'France',
			'gb' => 'United Kingdom',
			'gr' => 'Greece',
			'hk' => 'Hong Kong',
			'hu' => 'Hungary',
			'id' => 'Indonesia',
			'ie' => 'Ireland',
			'il' => 'Israel',
			'in' => 'India',
			'it' => 'Italy',
			'jp' => 'Japan',
			'kr' => 'South Korea',
			'lt' => 'Lithuania',
			'lv' => 'Latvia',
			'ma' => 'Morocco',
			'mx' => 'Mexico',
			'my' => 'Malaysia',
			'ng' => 'Nigeria',
			'nl' => 'Netherlands',
			'no' => 'Norway',
			'nz' => 'New Zealand',
			'ph' => 'Philippines',
			'pl' => 'Poland',
			'pt' => 'Portugal',
			'ro' => 'Romania',
			'rs' => 'Serbia',
			'ru' => 'Russia',
			'sa' => 'Saudi Arabia',
			'se' => 'Sweden',
			'sg' => 'Singapore',
			'si' => 'Slovenia',
			'sk' => 'Slovakia',
			'th' => 'Thailand',
			'tr' => 'Turkey',
			'tw' => 'Taiwan',
			'ua' => 'Ukraine',
			'us' => 'United States',
			've' => 'Venezuela',
			'za' => 'South Africa',
		];
	}

	/**
	 * Check if country exists
	 *
	 * @param string|null $country_code
	 *
	 * @return bool
	 */
	public static function exists( ?string $country_code ): bool {
		return array_key_exists( strtolower( $country_code ), self::countries() );
	}
}
