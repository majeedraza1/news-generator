<?php

namespace StackonetNewsGenerator\Modules\Keyword;

/**
 * Class Setting
 *
 * This class represents a setting in a system.
 */
class Setting {
	/**
	 * Retrieve all items.
	 *
	 * @return array An array containing all items.
	 */
	public static function all(): array {
		return array(
			'keyword_news_sync_interval' => static::get_sync_interval(),
			'keyword_item_per_sync'      => static::get_item_per_sync(),
		);
	}

	/**
	 * News Sync Interval
	 */
	public static function get_sync_interval(): int {
		$interval = (int) get_option( '_keyword_news_sync_interval' );

		return min( 360, max( 15, $interval ) );
	}

	/**
	 * Update the news sync interval.
	 *
	 * This method updates the news sync interval value in the WordPress options table.
	 *
	 * @param  int|mixed  $interval  The new news sync interval value to be updated. Must be an integer.
	 *
	 * @return int The updated news sync interval value.
	 */
	public static function update_sync_interval( $interval ): int {
		$interval      = min( 360, max( 15, intval( $interval ) ) );
		$current_value = static::get_sync_interval();
		update_option( '_keyword_news_sync_interval', $interval );

		if ( $current_value !== $interval ) {
			wp_unschedule_hook( 'terapixel_news_generator/keyword_sync' );
		}

		return $interval;
	}

	/**
	 * News Sync Interval
	 */
	public static function get_item_per_sync(): int {
		$interval = (int) get_option( '_keyword_item_per_sync' );

		return max( 1, $interval );
	}

	/**
	 * Update the news sync interval.
	 *
	 * This method updates the news sync interval value in the WordPress options table.
	 *
	 * @param  int|mixed  $item_per_sync  The new news sync interval value to be updated. Must be an integer.
	 *
	 * @return int The updated news sync interval value.
	 */
	public static function update_item_per_sync( $item_per_sync ): int {
		$item_per_sync = max( 1, intval( $item_per_sync ) );
		update_option( '_keyword_item_per_sync', $item_per_sync );

		return $item_per_sync;
	}

	/**
	 * Get instruction for custom keyword
	 *
	 * @return string
	 */
	public static function get_global_instruction(): string {
		$default = 'Please generate blog post with SEO. ';
		$default .= 'The keyword is "{{keyword}}" and we need to add the keyword in title, meta description and contents. ';
		$default .= 'Please insert this keyword more than 5 times in the article with 2000 words. ';
		$default .= 'Add [Title:], [Meta Description:] and [Content:] respectively when starting each section.' . PHP_EOL;
		$default .= 'Do no include word count or any extra description.';

		$option = get_option( '_keyword_global_instruction' );

		return ! empty( $option ) ? $option : $default;
	}

	/**
	 * Updates the global instruction value.
	 *
	 * @param  mixed  $instruction  The new instruction value to be set.
	 *
	 * @return string The updated instruction value.
	 */
	public static function update_global_instruction( $instruction ): string {
		update_option( '_keyword_global_instruction', $instruction );

		return $instruction;
	}
}
