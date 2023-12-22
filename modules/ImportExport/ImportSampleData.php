<?php

namespace StackonetNewsGenerator\Modules\ImportExport;

use Stackonet\WP\Framework\Supports\Filesystem;
use Stackonet\WP\Framework\Supports\Validate;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\Plugin;
use WP_Error;

/**
 * ImportSampleData class
 */
class ImportSampleData {
	/**
	 * Get file contents
	 *
	 * @param  string  $relative_path  The relative path to sample-data directory.
	 *
	 * @return string|WP_Error
	 */
	public static function file_get_contents( string $relative_path ) {
		$full_path = join(
			'/',
			array( Plugin::init()->get_plugin_path(), 'tests/sample-data', ltrim( $relative_path, '/' ) )
		);
		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'file_not_exists', 'The file does not exists.' );
		}

		$content = Filesystem::get_filesystem()->get_contents( $full_path );
		if ( ! is_string( $content ) ) {
			return new WP_Error( 'file_no_content', 'The file does not have any content.' );
		}
		if ( ! Validate::json( $content ) ) {
			return new WP_Error( 'invalid_json', 'The file does not have any valid json content.' );
		}

		return $content;
	}

	/**
	 * Import sync settings
	 *
	 * @return array|WP_Error
	 */
	public static function sync_settings() {
		$content = static::file_get_contents( 'sync-settings.json' );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$content = json_decode( $content, true );

		return SyncSettings::update_option( $content );
	}

	/**
	 * Import dummy keywords
	 *
	 * @return array|WP_Error
	 */
	public static function keywords() {
		$content = static::file_get_contents( 'keywords.json' );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$content = json_decode( $content, true );

		return Keyword::batch_create( $content );
	}
}
