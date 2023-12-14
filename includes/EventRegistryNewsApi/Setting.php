<?php

namespace TeraPixelNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Supports\Validate;

class Setting {
	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function is_auto_sync_enabled(): bool {
		$options = get_option( '_news_api_auto_sync_enabled', true );

		return Validate::checked( $options );
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_is_auto_sync_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_news_api_auto_sync_enabled', $value );

		return $value;
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function is_duplicate_checking_enabled(): bool {
		$options = get_option( '_is_news_duplicate_checking_enabled', true );

		return Validate::checked( $options );
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_duplicate_checking_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_is_news_duplicate_checking_enabled', $value ? 'yes' : 'no' );

		return $value;
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function should_remove_image_with_text(): bool {
		$options = get_option( '_should_remove_image_with_text', true );

		return Validate::checked( $options );
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_should_remove_image_with_text( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_should_remove_image_with_text', $value ? 'yes' : 'no' );

		return $value;
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function sync_image_copy_setting_from_source(): bool {
		$options = get_option( '_sync_image_copy_setting_from_source', true );

		return Validate::checked( $options );
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_sync_image_copy_setting_from_source( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_sync_image_copy_setting_from_source', $value ? 'yes' : 'no' );

		return $value;
	}

	/**
	 * Update request count
	 */
	public static function update_news_request_count() {
		$api_key            = self::get_news_api_key();
		$option_name        = self::get_news_option_name( $api_key );
		$request_sent_today = (int) get_option( $option_name );
		update_option( $option_name, ( $request_sent_today + 1 ) );
	}

	/**
	 * Get api key
	 *
	 * @return string
	 */
	public static function get_news_api_key(): string {
		$settings = self::get_news_api_keys();
		$api_key  = '';
		foreach ( $settings as $setting ) {
			if ( $setting['request_sent'] >= $setting['limit_per_day'] ) {
				continue;
			}
			$api_key = $setting['api_key'];
			break;
		}

		return $api_key;
	}

	/**
	 * Get api keys
	 *
	 * @return array
	 */
	public static function get_news_api_keys(): array {
		$options  = self::get_options();
		$settings = is_array( $options['news_api'] ) ? $options['news_api'] : [];
		foreach ( $settings as $index => $setting ) {
			$request_sent_today                 = (int) get_option( self::get_news_option_name( $setting['api_key'] ) );
			$settings[ $index ]['request_sent'] = $request_sent_today;
		}

		return $settings;
	}

	/**
	 * Get options
	 *
	 * @return array
	 */
	public static function get_options(): array {
		$default = [
			'news_api'  => [],
			'news_sync' => [],
		];
		$options = (array) get_option( '_event_registry_news_api_settings' );

		return wp_parse_args( $options, $default );
	}

	/**
	 * @param  string  $api_key  The api key.
	 * @param  string  $date  Date string af format 'ymd'
	 *
	 * @return string
	 */
	private static function get_news_option_name( string $api_key, string $date = '' ): string {
		if ( empty( $date ) ) {
			$date = date( 'ymd', time() );
		}

		return sprintf( '_nusify_news_request_per_day_%s_%s', $date, md5( $api_key ) );
	}

	/**
	 * Clear previous days request count
	 */
	public static function clear_previous_daily_request_count(): bool {
		$names = [
			'_nusify_news_request_per_day_',
		];
		global $wpdb;
		$sql = "SELECT * FROM $wpdb->options WHERE 1 = 1 AND";
		$sql .= '(';
		foreach ( $names as $index => $name ) {
			if ( 0 !== $index ) {
				$sql .= ' OR';
			}
			$sql .= $wpdb->prepare( ' option_name LIKE %s', $name . '%' );
		}
		$sql        .= ')';
		$results    = $wpdb->get_results( $sql, ARRAY_A );
		$today_keys = [];
		$news_keys  = self::get_news_api_keys();
		foreach ( $news_keys as $news_key ) {
			$today_keys[] = self::get_news_option_name( $news_key['api_key'] );
			$today_keys[] = self::get_news_option_name( $news_key['api_key'], date( 'ymd', strtotime( 'yesterday' ) ) );
		}
		$items_to_delete = [];
		foreach ( $results as $result ) {
			if ( in_array( $result['option_name'], $today_keys ) ) {
				continue;
			}
			$items_to_delete[] = $result;
		}
		if ( count( $items_to_delete ) ) {
			$ids_to_delete = array_map( 'intval', wp_list_pluck( $items_to_delete, 'option_id' ) );
			$sql           = "DELETE FROM $wpdb->options WHERE option_id IN(" . implode( ',', $ids_to_delete ) . ')';

			return ! ! $wpdb->query( $sql );
		}

		return false;
	}

	public static function get_similarity_in_percent(): int {
		$value = (int) get_option( '_event_registry_news_api_similarity_in_percent', 60 );

		return min( 90, max( 30, $value ) );
	}

	public static function update_similarity_in_percent( int $value ): int {
		$similarity_in_percent = min( 90, max( 30, $value ) );
		update_option( '_event_registry_news_api_similarity_in_percent', $similarity_in_percent );

		return $similarity_in_percent;
	}

	public static function get_num_of_hours_for_similarity(): int {
		$value = (int) get_option( '_num_of_hours_for_similarity' );
		if ( ! $value ) {
			$value = (int) get_option( '_event_registry_news_api_num_of_days_for_similarity', 3 );
			$value = min( 7, max( 1, $value ) ) * 24;
		}

		return max( 1, $value );
	}

	public static function update_num_of_hours_for_similarity( int $value ): int {
		$hours = min( 168, max( 1, $value ) ); // maximum 7 days or (7 * 24) = 168 hours
		update_option( '_num_of_hours_for_similarity', $hours );

		return $hours;
	}

	/**
	 * Get news sources
	 *
	 * @return array
	 */
	public static function get_news_sources(): array {
		$settings = SyncSettings::get_settings();
		$sources  = [];
		foreach ( $settings as $setting ) {
			if ( isset( $setting['sources'] ) && is_array( $setting['sources'] ) ) {
				foreach ( $setting['sources'] as $source ) {
					if ( empty( $source['uri'] ) || empty( $source['title'] ) ) {
						continue;
					}
					$sources[ $source['uri'] ] = [
						'title'        => $source['title'],
						'uri'          => $source['uri'],
						'data_type'    => $source['dataType'],
						'copy_image'   => 1,
						'in_whitelist' => 1,
						'in_blacklist' => 0,
					];
				}
			}
		}

		return $sources;
	}

	/**
	 * Update news sync Interval
	 *
	 * @param  int  $interval  The interval value.
	 */
	public static function update_news_sync_interval( int $interval ) {
		$current_interval = static::get_news_sync_interval();
		$interval         = max( 15, $interval ); // Minimum interval is 15 minutes.
		$interval         = min( 6 * HOUR_IN_SECONDS, $interval ); // Maximum interval is 6 hours.
		if ( $current_interval !== $interval ) {
			update_option( 'news_sync_interval', $interval );
			if ( wp_next_scheduled( 'event_registry_news_api/sync' ) ) {
				wp_clear_scheduled_hook( 'event_registry_news_api/sync' );
			}
			wp_reschedule_event( time(), 'news_sync_interval', 'event_registry_news_api/sync' );
		}

		return $interval;
	}

	/**
	 * Get news sync Interval
	 *
	 * @return int
	 */
	public static function get_news_sync_interval(): int {
		$interval = (int) get_option( 'news_sync_interval', 30 );

		return max( 15, $interval );
	}

	/**
	 * Get news sync max hour from sync time
	 *
	 * @return int
	 */
	public static function get_news_not_before_in_minutes(): int {
		$interval = (int) get_option( 'news_not_before_in_minutes', 15 );

		return max( 15, $interval );
	}

	/**
	 * Update interval.
	 *
	 * @param  int  $interval  The value.
	 *
	 * @return mixed
	 */
	public static function update_news_not_before_in_minutes( int $interval ) {
		$value = max( 1, $interval );
		update_option( 'news_not_before_in_minutes', $value );

		return $value;
	}
}
