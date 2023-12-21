<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

/**
 * News categories
 */
class Category {
	/**
	 * Get default categories
	 *
	 * @var string[]
	 */
	private static $defaults = [
		'general'       => 'General',
		'business'      => 'Business',
		'entertainment' => 'Entertainment',
		'health'        => 'Health',
		'science'       => 'Science',
		'sports'        => 'Sports',
		'technology'    => 'Technology',
	];

	public static function get_default_category(): string {
		return get_option( '_default_news_category', 'general' );
	}

	public static function set_default_category( string $cat_slug ): string {
		if ( static::exists( $cat_slug ) ) {
			update_option( '_default_news_category', $cat_slug );
		}

		return $cat_slug;
	}

	/**
	 * Check if country exists
	 *
	 * @param string $category
	 *
	 * @return bool
	 */
	public static function exists( string $category ): bool {
		return array_key_exists( strtolower( $category ), self::get_categories() );
	}

	/**
	 * Get categories
	 *
	 * @return array
	 */
	public static function get_categories(): array {
		return (array) get_option( '_falahcoin_news_categories', static::$defaults );
	}

	/**
	 * Update categories
	 *
	 * @param array $value The categories to be updated.
	 *
	 * @return array
	 */
	public static function update_categories( array $value ): array {
		$data       = wp_parse_args( $value, static::$defaults );
		$categories = [];
		foreach ( $data as $slug => $category ) {
			if ( is_numeric( $slug ) && is_array( $category ) && isset( $category['label'], $category['value'] ) ) {
				$slug     = $category['value'];
				$category = $category['label'];
			}
			if ( empty( $category ) ) {
				continue;
			}
			$value = sanitize_text_field( $category );
			$slug  = empty( $slug ) ? sanitize_title_with_dashes( $value ) : $slug;
			$key   = sanitize_title_with_dashes( $slug, '', 'save' );

			$categories[ $key ] = $value;
		}
		update_option( '_falahcoin_news_categories', $categories );

		return $categories;
	}

	/**
	 * Get all categories slug
	 *
	 * @return array
	 */
	public static function all(): array {
		return array_keys( self::get_categories() );
	}

	public static function titles(): array {
		return array_values( static::get_categories() );
	}

	public static function get_slug_by_title( string $title ) {
		return array_search( $title, static::get_categories() );
	}
}
