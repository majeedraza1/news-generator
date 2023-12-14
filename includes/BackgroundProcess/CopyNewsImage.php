<?php

namespace TeraPixelNewsGenerator\BackgroundProcess;

use finfo;
use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;
use TeraPixelNewsGenerator\EventRegistryNewsApi\Setting;
use TeraPixelNewsGenerator\OpenAIApi\Stores\NewsStore;
use TeraPixelNewsGenerator\Providers\GoogleVisionClient;
use Stackonet\WP\Framework\Media\UploadedFile;
use Stackonet\WP\Framework\Media\Uploader;
use Stackonet\WP\Framework\Supports\Filesystem;
use Stackonet\WP\Framework\Supports\Validate;
use WP_Error;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

class CopyNewsImage extends BackgroundProcessBase {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	public static $instance = null;

	/**
	 * Action
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'copy_image_from_remote';

	/**
	 * Only one instance of the class can be loaded
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function task( $item ) {
		$news_id        = isset( $item['news_id'] ) ? intval( $item['news_id'] ) : 0;
		$openai_news_id = isset( $item['openai_news_id'] ) ? intval( $item['openai_news_id'] ) : 0;
		$article        = ( new ArticleStore() )->find_single( $news_id );
		$news           = ( new NewsStore() )->find_single( $openai_news_id );
		if ( Validate::url( $article['image'] ) && 0 === $news['image_id'] ) {
			$image_id = $this->copy_image_as_webp( $article['image'], $news['title'] );
			if ( is_numeric( $image_id ) ) {
				static::add_attachment_info(
					intval( $image_id ),
					intval( $news['source_id'] ),
					intval( $news['id'] )
				);
			}
		}

		return false;
	}

	public static function download_image_from_url( $file_url ) {
		if ( ! ( is_string( $file_url ) && Validate::url( $file_url ) ) ) {
			return new \WP_Error( 'invalid_url', 'Image url is not valid.' );
		}
		// If the function it's not available, require it.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$mime_types = array( 'image/jpeg', 'image/gif', 'image/png', 'image/webp' );
		$file_ext   = pathinfo( $file_url, PATHINFO_EXTENSION );
		$temp_file  = download_url( $file_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}
		$mime_type = ( new finfo() )->file( $temp_file, FILEINFO_MIME_TYPE );
		if ( ! in_array( $mime_type, $mime_types, true ) ) {
			return new WP_Error( 'unsupported_mime_type', 'unsupported mime type' );
		}

		$google_vision_secret_key = GoogleVisionClient::get_google_vision_secret_key();
		$is_enabled               = Setting::should_remove_image_with_text();
		if ( $is_enabled && ! empty( $google_vision_secret_key ) ) {
			$has_text_on_image = GoogleVisionClient::has_text_on_image( $temp_file );
			if ( $has_text_on_image ) {
				return new WP_Error( 'image_with_text', 'There are some text on image.' );
			}
		}
		$alt_file_ext = str_replace( 'image/', '', $mime_type );
		if ( $file_ext !== $alt_file_ext ) {
			$file_ext = $alt_file_ext;
		}

		return array(
			'temp_file' => $temp_file,
			'mime_type' => $mime_type,
			'extension' => $file_ext,
		);
	}

	/**
	 * Copy image as webp
	 *
	 * @param  mixed|string  $file_url
	 * @param  string  $title
	 * @param  int  $width
	 *
	 * @return int|WP_Error
	 */
	public static function copy_image_as_webp( $file_url, string $title, int $width = 1200 ) {
		$file_info = static::download_image_from_url( $file_url );
		if ( is_wp_error( $file_info ) ) {
			return $file_info;
		}
		list( 'temp_file' => $temp_file ) = $file_info;

		$title        = $title . ' - Featured Image';
		$new_filename = sprintf( '%s.webp', sanitize_title_with_dashes( $title ) );
		$new_filepath = Uploader::get_upload_dir() . DIRECTORY_SEPARATOR . $new_filename;

		try {
			$im     = new \Imagick( $temp_file );
			$height = $im->getImageHeight() / $im->getImageWidth() * $width;

			$im->scaleImage( $width, $height, true );
			$im->setImageFormat( 'webp' );
			$im->setOption( 'webp:method', '6' );
			$im->setOption( 'webp:lossless', 'false' );
			$im->setCompressionQuality( 83 );

			Filesystem::update_file_content( $im->getImageBlob(), $new_filepath );

			return static::generate_attachment_metadata( $new_filepath, 'image/webp', $title );
		} catch ( \ImagickException $e ) {
			return new WP_Error( 'imagick_error', $e->getMessage() );
		}
	}

	public static function add_attachment_info( $attachment_id, int $source_news_id, int $ai_news_id = 0 ) {
		if ( ! is_numeric( $attachment_id ) ) {
			return;
		}
		if ( $source_news_id ) {
			( new ArticleStore() )->update(
				array(
					'id'       => $source_news_id,
					'image_id' => $attachment_id,
				)
			);
		}
		if ( $ai_news_id ) {
			( new NewsStore() )->update(
				array(
					'id'       => $ai_news_id,
					'image_id' => $attachment_id,
				)
			);
		}
	}

	/**
	 * Add attachment data
	 *
	 * @param  UploadedFile  $file  The uploaded UploadedFile object.
	 * @param  string  $file_path  The uploaded file path.
	 *
	 * @return int|WP_Error
	 */
	protected static function generate_attachment_metadata( string $file_path, string $mime_type, string $title ) {
		$upload_dir = wp_upload_dir();
		$data       = [
			'guid'           => str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path ),
			'post_title'     => preg_replace( '/\.[^.]+$/', '', sanitize_text_field( $title ) ),
			'post_status'    => 'inherit',
			'post_mime_type' => $mime_type,
		];

		$attachment_id = wp_insert_attachment( $data, $file_path );

		if ( ! is_wp_error( $attachment_id ) ) {
			// Make sure that this file is included, as wp_read_video_metadata() depends on it.
			require_once ABSPATH . 'wp-admin/includes/media.php';
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once ABSPATH . 'wp-admin/includes/image.php';

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		return $attachment_id;
	}
}
