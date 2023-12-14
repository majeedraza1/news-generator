<?php

namespace TeraPixelNewsGenerator\Supports;

use TeraPixelNewsGenerator\Plugin;

class Country {
	public static function get_country_name( string $code ): string {
		$countries = static::all();

		return $countries[ $code ] ?? $code;
	}

	public static function all() {
		return include Plugin::init()->get_plugin_path() . '/languages/countries.php';
	}

	public static function exists( string $code ): bool {
		$countries = static::all();

		return array_key_exists( $code, $countries );
	}
}
