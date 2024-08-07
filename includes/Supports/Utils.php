<?php

namespace StackonetNewsGenerator\Supports;

use StackonetNewsGenerator\BackgroundProcess\CopyNewsImage;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateFocusKeyphrase;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsBody;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncNews;

/**
 * Utils
 */
class Utils {
	/**
	 * Is the news is in queue.
	 *
	 * @param  int  $openai_news_id  OpenAi news id.
	 *
	 * @return bool
	 */
	public static function is_in_sync_queue( int $openai_news_id ): bool {
		$pending_focus_keyphrase = OpenAiReCreateFocusKeyphrase::init()->get_pending_background_tasks();
		if ( in_array( $openai_news_id, $pending_focus_keyphrase, true ) ) {
			return true;
		}
		$pending_tasks = OpenAiReCreateNewsBody::init()->get_pending_background_tasks();
		if ( in_array( $openai_news_id, $pending_tasks, true ) ) {
			return true;
		}
		$pending_tasks = CopyNewsImage::init()->get_pending_background_tasks();
		if ( in_array( $openai_news_id, $pending_tasks, true ) ) {
			return true;
		}
		$pending_tasks = OpenAiSyncNews::init()->get_pending_background_tasks();
		if ( in_array( $openai_news_id, $pending_tasks, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Max allowed time
	 *
	 * @return int
	 */
	public static function max_allowed_time(): int {
		$max_exe_time = static::max_execution_time();

		return min( ( $max_exe_time - 15 ), intval( $max_exe_time * .9 ) );
	}

	/**
	 * Max execution time
	 *
	 * @return int
	 */
	public static function max_execution_time(): int {
		return (int) ini_get( 'max_execution_time' );
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	public static function get_memory_limit(): int {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default.
			$memory_limit = '128M';
		}
		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Str word count for UTF-8
	 *
	 * @param  string  $str  Get word count in a string.
	 *
	 * @return int
	 */
	public static function str_word_count_utf8( string $str ): int {
		$a = preg_split( '/\W+/u', $str, - 1, PREG_SPLIT_NO_EMPTY );

		return count( $a );
	}

	/**
	 * Convert bytes to human size
	 *
	 * @param $size
	 * @param $unit
	 *
	 * @return string
	 */
	public static function bytes_to_human_size( $size, $unit = '' ) {
		if ( ( ! $unit && $size >= 1 << 30 ) || 'GB' === $unit ) {
			return number_format( $size / ( 1 << 30 ), 0 ) . 'GB';
		}
		if ( ( ! $unit && $size >= 1 << 20 ) || 'MB' === $unit ) {
			return number_format( $size / ( 1 << 20 ), 0 ) . 'MB';
		}
		if ( ( ! $unit && $size >= 1 << 10 ) || 'KB' === $unit ) {
			return number_format( $size / ( 1 << 10 ), 0 ) . 'KB';
		}

		return number_format( $size ) . ' bytes';
	}


	/**
	 * Get list of images sorted by its width and height
	 *
	 * @param  string  $image_size  The image size.
	 * @param  int  $per_page  Item per page.
	 *
	 * @return array
	 */
	public static function get_images( string $image_size = 'full', int $per_page = 10 ): array {
		$args        = array(
			'order'          => 'DESC',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
		);
		$attachments = get_posts( $args );

		$images = array();

		foreach ( $attachments as $attachment ) {
			if ( ! in_array( $attachment->post_mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				continue;
			}

			$src        = wp_get_attachment_image_src( $attachment->ID, $image_size );
			$_link_url  = get_post_meta( $attachment->ID, '_carousel_slider_link_url', true );
			$_image_alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

			$images[] = array(
				'id'           => $attachment->ID,
				'title'        => $attachment->post_title,
				'description'  => $attachment->post_content,
				'caption'      => $attachment->post_excerpt,
				'alt_text'     => $_image_alt,
				'link_url'     => $_link_url,
				'image_src'    => $src[0],
				'image_width'  => $src[1],
				'image_height' => $src[2],
			);
		}

		$widths  = wp_list_pluck( $images, 'image_width' );
		$heights = wp_list_pluck( $images, 'image_height' );

		// Sort the $images with $widths and $heights descending.
		array_multisort( $widths, SORT_DESC, $heights, SORT_DESC, $images );

		return $images;
	}

	/**
	 * Remove emoji from string
	 *
	 * @param  string  $string  The string to be sanitized.
	 *
	 * @return string
	 */
	public static function remove_emoji( string $string ): string {
		// Match Enclosed Alphanumeric Supplement.
		$regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
		$clear_string       = preg_replace( $regex_alphanumeric, '', $string );

		// Match Miscellaneous Symbols and Pictographs.
		$regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
		$clear_string  = preg_replace( $regex_symbols, '', $clear_string );

		// Match Emoticons.
		$regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
		$clear_string    = preg_replace( $regex_emoticons, '', $clear_string );

		// Match Transport And Map Symbols.
		$regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
		$clear_string    = preg_replace( $regex_transport, '', $clear_string );

		// Match Supplemental Symbols and Pictographs.
		$regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
		$clear_string       = preg_replace( $regex_supplemental, '', $clear_string );

		// Match Miscellaneous Symbols.
		$regex_misc   = '/[\x{2600}-\x{26FF}]/u';
		$clear_string = preg_replace( $regex_misc, '', $clear_string );

		// Match Dingbats.
		$regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
		$clear_string   = preg_replace( $regex_dingbats, '', $clear_string );

		// Replace multiple space into one space.
		$clear_string = preg_replace( '!\s+!', ' ', $clear_string );

		return trim( $clear_string );
	}

	/**
	 * Remove emoji from string
	 *
	 * @param  string  $string  The string to be sanitized.
	 *
	 * @return string
	 */
	public static function remove_emoji_multiline( string $string ): string {
		$lines = array();
		foreach ( explode( PHP_EOL, $string ) as $line ) {
			$lines[] = static::remove_emoji( $line );
		}

		return implode( PHP_EOL, $lines );
	}

	/**
	 * Rest all data
	 *
	 * @return void
	 */
	public static function reset_all_data() {
		global $wpdb;
		foreach ( static::get_tables_list() as $table ) {
			$wpdb->query( "TRUNCATE `{$wpdb->prefix}$table`" );
		}

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE ('%\_transient\_%')" );
	}

	/**
	 * Get tables list
	 *
	 * @return string[]
	 */
	public static function get_tables_list(): array {
		return array(
			'openai_news',
			'event_registry_news',
			'event_registry_news_logs',
			'news_sources',
			'openai_response_log',
			'event_registry_interesting_news',
			'news_keywords',
			'external_links',
			'openai_instagram_fail_log',
			'openai_news_sites',
			'openai_news_tags',
			'openai_news_to_site_logs',
			'twitter_tweets',
		);
	}
}
