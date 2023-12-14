<?php

namespace TeraPixelNewsGenerator;

use TeraPixelNewsGenerator\BackgroundProcess\DeleteDuplicateImages;
use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;
use TeraPixelNewsGenerator\OpenAIApi\Client as OpenAIApiClient;
use TeraPixelNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use TeraPixelNewsGenerator\OpenAIApi\Models\BlackListWords;
use TeraPixelNewsGenerator\OpenAIApi\Models\InterestingNews;
use TeraPixelNewsGenerator\OpenAIApi\News;
use TeraPixelNewsGenerator\OpenAIApi\Stores\NewsStore;
use TeraPixelNewsGenerator\Providers\GoogleVisionClient;
use TeraPixelNewsGenerator\Supports\Utils;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Ajax {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'wp_ajax_terapixel_news_generator_test', array( self::$instance, 'do_ajax_testing' ) );
			add_action( 'wp_ajax_test_google_vision', array( self::$instance, 'test_google_vision' ) );
			add_action( 'wp_ajax_debug_interesting_news', array( self::$instance, 'debug_interesting_news' ) );
			add_action( 'wp_ajax_debug_blacklist_item', array( self::$instance, 'debug_blacklist_item' ) );
			add_action( 'wp_ajax_delete_old_articles', array( self::$instance, 'delete_old_articles' ) );
			add_action( 'wp_ajax_delete_old_logs', array( self::$instance, 'delete_old_logs' ) );
			add_action( 'wp_ajax_delete_old_news_filter', array( self::$instance, 'delete_old_news_filter' ) );
			add_action( 'wp_ajax_delete_duplicate_image', array( self::$instance, 'delete_duplicate_image' ) );
			add_action(
				'wp_ajax_important_news_for_instagram',
				array( self::$instance, 'important_news_for_instagram' )
			);
		}

		return self::$instance;
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function do_ajax_testing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$response = "1. Mashantucket Pequot Tribe\n2. Blue Camp CT\n3. Preston Planning and Zoning Commission\n4. New London Superior Court\n5. Blue Water Recreational Campground Resort";

		$news = NewsStore::find_by_id( 324 );
		var_dump(
			array(
				'news'   => $news,
				'_tags'  => $news->get_prop( 'tags' ),
				'_tags2' => explode( ',', $news->get_prop( 'tags' ) ),
				'tags'   => $news->get_tags(),
			)
		);

		die();
	}

	/**
	 * Important news for instagram
	 *
	 * @return void
	 */
	public function important_news_for_instagram() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}
		$news_array = NewsStore::get_news_for_instagram( true );
		if ( count( $news_array ) < 1 ) {
			wp_die( __( 'Sorry. No new news in last one hour.', 'terapixel-news-generator' ) );
		}
		$news_ids = OpenAIApiClient::find_important_news_for_instagram( $news_array, true );
		if ( is_array( $news_ids ) ) {
			foreach ( $news_ids as $news_id ) {
				$news = NewsStore::find_by_id( $news_id );
				$news->send_to_sites( true );
			}
		}

		var_dump(
			array(
				'suggested'  => $news_ids,
				'news_items' => $news_array,
			)
		);
		die;
	}

	public function test_google_vision() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}
		$image_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		if ( empty( $image_id ) ) {
			$images   = Utils::get_images( 'full', 1 );
			$image_id = $images[0]['id'];
		}

		$src = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! is_array( $src ) ) {
			wp_die( 'Could not find any image for that id.' );
		}

		try {
			$im       = new \Imagick( $src[0] );
			$response = ( new GoogleVisionClient() )->detect_text( base64_encode( $im->getImageBlob() ) );
		} catch ( \ImagickException $e ) {
			wp_die( $e->getMessage() );
		}
		var_dump(
			array(
				'image_id'               => $image_id,
				'image_url'              => $src[0],
				'google_vision_response' => $response,
			)
		);
		die;
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function debug_interesting_news() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$news_id = isset( $_GET['news_id'] ) ? intval( $_GET['news_id'] ) : 0;
		$task    = isset( $_GET['task'] ) ? sanitize_text_field( $_GET['task'] ) : '';

		/** @var InterestingNews $log */
		$log = InterestingNews::find_single( $news_id );
		var_dump( $log );
		die();
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function debug_blacklist_item() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$news_id = isset( $_GET['news_id'] ) ? intval( $_GET['news_id'] ) : 0;
		/** @var News $news */
		$news = ( new NewsStore() )->find_single( $news_id );

		$info  = BlackListWords::get_blacklist_phrase_info_by_similarity( $news->get_content() );
		$info2 = BlackListWords::get_blacklist_phrase_info_by_strpos( $news->get_content() );
		var_dump(
			array(
				'news'          => $news,
				'by_similarity' => $info,
				'by_strpos'     => $info2,
			)
		);
		die();
	}

	public function delete_old_articles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 7;
		try {
			ArticleStore::delete_old_articles( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}

	public function delete_old_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 3;
		try {
			ApiResponseLog::delete_old_logs( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}

	public function delete_duplicate_image() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$force = isset( $_GET['force'] );
		$limit = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 100;
		$limit = max( 10, $limit );

		$ids = DeleteDuplicateImages::run( $force, $limit );
		var_dump(
			array(
				'command'  => 'Delete duplicate images',
				'mode'     => $force ? 'instant' : 'background',
				'start_id' => count( $ids ) ? min( $ids ) : false,
				'end_id'   => count( $ids ) ? max( $ids ) : false,
				'ids'      => $ids,
			)
		);
		wp_die();
	}

	public function delete_old_news_filter() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'terapixel-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 3;
		try {
			InterestingNews::delete_old_logs( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}
}
