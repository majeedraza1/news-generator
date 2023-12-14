<?php

namespace TeraPixelNewsGenerator\REST;

use Stackonet\WP\Framework\Supports\Validate;
use TeraPixelNewsGenerator\BackgroundProcess\OpenAiReCreateNews;
use TeraPixelNewsGenerator\BackgroundProcess\OpenAiReCreateOldNews;
use TeraPixelNewsGenerator\BackgroundProcess\SyncEventRegistryNews;
use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;
use TeraPixelNewsGenerator\EventRegistryNewsApi\Category;
use TeraPixelNewsGenerator\EventRegistryNewsApi\Client;
use TeraPixelNewsGenerator\EventRegistryNewsApi\Setting;
use TeraPixelNewsGenerator\EventRegistryNewsApi\SyncSettings;
use TeraPixelNewsGenerator\Modules\Keyword\Setting as KeywordSetting;
use TeraPixelNewsGenerator\Modules\Site\SiteStore;
use TeraPixelNewsGenerator\OpenAIApi\Models\BlackListWords;
use TeraPixelNewsGenerator\OpenAIApi\Setting as OpenAIApiSetting;
use TeraPixelNewsGenerator\Providers\GoogleVisionClient;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The AdminSettingController class is responsible for handling the admin settings related to the API controller.
 */
class AdminSettingController extends ApiController {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_items' ),
//					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'create_item' ),
//					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/locations',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_countries' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/categories',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_categories' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/concepts',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_concepts' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/sources',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_sources' ),
				),
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'create_source' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/sync',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'sync_news' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/sync-all',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'sync_all_news' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/settings/sync-with-openai',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'sync_openai_news' ),
				),
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_items( $request ) {
		$news_sync_settings = SyncSettings::get_settings();
		$settings           = array(
			'news_sync'                           => $news_sync_settings,
			'news_api'                            => Setting::get_news_api_keys(),
			'newsapi_auto_sync_enabled'           => Setting::is_auto_sync_enabled(),
			'news_duplicate_checking_enabled'     => Setting::is_duplicate_checking_enabled(),
			'should_remove_image_with_text'       => Setting::should_remove_image_with_text(),
			'min_news_count_for_important_tweets' => OpenAIApiSetting::get_min_news_count_for_important_tweets(),
			'openai_api'                          => OpenAIApiSetting::get_api_settings(),
			'instructions'                        => OpenAIApiSetting::get_instructions(),
			'openai_auto_sync_enabled'            => OpenAIApiSetting::is_auto_sync_enabled(),
			'openai_news_country_enabled'         => OpenAIApiSetting::is_news_country_enabled(),
			'external_link_enabled'               => OpenAIApiSetting::is_external_link_enabled(),
			'important_news_for_tweets_enabled'   => OpenAIApiSetting::is_important_news_for_tweets_enabled(),
			'openai_news_sync_method'             => OpenAIApiSetting::news_sync_method(),
			'instagram_new_news_interval'         => OpenAIApiSetting::get_instagram_new_news_interval(),
			'use_linkedin_data_for_instagram'     => OpenAIApiSetting::use_linkedin_data_for_instagram(),
			'openai_unsync_items_count'           => ArticleStore::get_unsync_items_count(),
			'primary_categories'                  => Category::get_categories(),
			'default_news_category'               => Category::get_default_category(),
			'similarity_in_percent'               => Setting::get_similarity_in_percent(),
			'num_of_days_for_similarity'          => Setting::get_num_of_hours_for_similarity(),
			'news_sync_interval'                  => Setting::get_news_sync_interval(),
			'news_not_before_in_minutes'          => Setting::get_news_not_before_in_minutes(),
			'sync_image_copy_setting_from_source' => Setting::sync_image_copy_setting_from_source(),
			'google_vision_secret_key'            => GoogleVisionClient::get_google_vision_secret_key(),
			'keyword_news_sync_interval'          => KeywordSetting::get_sync_interval(),
			'keyword_item_per_sync'               => KeywordSetting::get_item_per_sync(),
			'keyword_global_instruction'          => KeywordSetting::get_global_instruction(),
		);

		$news_sync_query_info = array();
		foreach ( $news_sync_settings as $setting ) {
			$news_sync_query_info[] = self::get_openai_article_http_query_info( $setting );
		}

		$active_api_key = Setting::get_news_api_key();

		return $this->respondOK(
			array(
				'settings'                     => $settings,
				'blacklist_words'              => ( new BlackListWords() )->get_options(),
				'news_sync_fields'             => SyncSettings::news_sync_fields(),
				'active_news_api_key'          => $active_api_key,
				'news_sync_query_info'         => $news_sync_query_info,
				'google_vision_test_url'       => add_query_arg(
					array( 'action' => 'test_google_vision' ),
					admin_url( 'admin-ajax.php' )
				),
				'important_news_for_instagram' => add_query_arg(
					array( 'action' => 'important_news_for_instagram' ),
					admin_url( 'admin-ajax.php' )
				),
			)
		);
	}

	public function get_categories( WP_REST_Request $request ) {

		$prefix = $request->get_param( 'prefix' );
		$prefix = ! empty( $prefix ) ? sanitize_text_field( $prefix ) : '';
		if ( strlen( $prefix ) < 2 ) {
			return $this->respondUnprocessableEntity();
		}
		$categories = ( new Client() )->get_categories(
			array(
				'prefix' => $prefix,
				'count'  => 20,
			)
		);
		if ( is_wp_error( $categories ) ) {
			return $this->respondWithWpError( $categories );
		}

		return $this->respondOK( $categories );
	}

	/**
	 * Get openAI article HTTP query info
	 *
	 * @param  array  $setting
	 *
	 * @return array
	 */
	public static function get_openai_article_http_query_info( array $setting ): array {
		$client = new Client();
		$client->add_headers( 'Content-Type', 'application/json' );
		$sanitized_args = $client->get_articles_sanitized_args( $setting, true );
		list( $url, $args ) = $client->get_url_and_arguments(
			'GET',
			'/article/getArticles',
			$sanitized_args
		);
		$args = array_merge( array( 'url' => $url ), $args );
		list( $url2, $args2 ) = $client->get_url_and_arguments(
			'POST',
			'/article/getArticles',
			$sanitized_args
		);
		$args2 = array_merge( array( 'url' => $url2 ), $args2 );

		return array(
			'get'  => $args,
			'post' => $args2,
		);
	}

	public function get_countries( WP_REST_Request $request ): WP_REST_Response {
		$prefix = $request->get_param( 'prefix' );
		$prefix = ! empty( $prefix ) ? sanitize_text_field( $prefix ) : '';
		if ( strlen( $prefix ) < 2 ) {
			return $this->respondUnprocessableEntity();
		}
		$locations = ( new Client() )->get_locations( $prefix );
		if ( is_wp_error( $locations ) ) {
			return $this->respondWithWpError( $locations );
		}

		return $this->respondOK( $locations );
	}

	public function get_concepts( WP_REST_Request $request ) {

		$prefix = $request->get_param( 'prefix' );
		$prefix = ! empty( $prefix ) ? sanitize_text_field( $prefix ) : '';
		if ( strlen( $prefix ) < 2 ) {
			return $this->respondUnprocessableEntity();
		}
		$concepts = ( new Client() )->get_concepts(
			$prefix,
			array(
				'count' => 20,
			)
		);
		if ( is_wp_error( $concepts ) ) {
			return $this->respondWithWpError( $concepts );
		}

		return $this->respondOK( $concepts );
	}

	public function get_sources( WP_REST_Request $request ) {
		$prefix = $request->get_param( 'prefix' );
		$prefix = ! empty( $prefix ) ? sanitize_text_field( $prefix ) : '';
		if ( strlen( $prefix ) < 2 ) {
			return $this->respondUnprocessableEntity();
		}
		$concepts = ( new Client() )->get_sources(
			$prefix,
			array(
				'count' => 100,
			)
		);
		if ( is_wp_error( $concepts ) ) {
			return $this->respondWithWpError( $concepts );
		}

		return $this->respondOK( $concepts );
	}

	/**
	 * @inheritDoc
	 */
	public function create_item( $request ) {
		$google_news_api = $request->get_param( 'news_api' );
		$google_news_api = is_array( $google_news_api ) ? $google_news_api : array();
		$settings        = array();
		foreach ( $google_news_api as $item ) {
			if ( ! isset( $item['api_key'], $item['limit_per_day'] ) ) {
				continue;
			}
			$settings['news_api'][] = array(
				'api_key'       => sanitize_text_field( $item['api_key'] ),
				'limit_per_day' => intval( $item['limit_per_day'] ),
			);
		}

		update_option( '_event_registry_news_api_settings', $settings, true );

		$news_sync             = $request->get_param( 'news_sync' );
		$news_sync             = is_array( $news_sync ) ? $news_sync : array();
		$settings['news_sync'] = SyncSettings::update_option( $news_sync );

		$openai_api             = $request->get_param( 'openai_api' );
		$openai_api             = is_array( $openai_api ) ? $openai_api : array();
		$settings['openai_api'] = OpenAIApiSetting::update_options( $openai_api );

		$instructions             = $request->get_param( 'instructions' );
		$instructions             = is_array( $instructions ) ? $instructions : array();
		$settings['instructions'] = OpenAIApiSetting::update_instruction_options( $instructions );

		$is_news_sync_enabled                  = $request->get_param( 'newsapi_auto_sync_enabled' );
		$settings['newsapi_auto_sync_enabled'] = Setting::update_is_auto_sync_enabled( $is_news_sync_enabled );

		$is_duplicate_checking_enabled               = $request->get_param( 'news_duplicate_checking_enabled' );
		$settings['news_duplicate_checking_enabled'] = Setting::update_duplicate_checking_enabled( $is_duplicate_checking_enabled );

		$is_enabled                           = $request->get_param( 'openai_auto_sync_enabled' );
		$settings['openai_auto_sync_enabled'] = OpenAIApiSetting::update_is_auto_sync_enabled( $is_enabled );

		$should_remove_image_with_text             = $request->get_param( 'should_remove_image_with_text' );
		$settings['should_remove_image_with_text'] = Setting::update_should_remove_image_with_text( $should_remove_image_with_text );

		$is_enabled                                    = $request->get_param( 'important_news_for_tweets_enabled' );
		$settings['important_news_for_tweets_enabled'] = OpenAIApiSetting::update_important_news_for_tweets_enabled( $is_enabled );

		$is_country_enabled                      = $request->get_param( 'openai_news_country_enabled' );
		$settings['openai_news_country_enabled'] = OpenAIApiSetting::update_news_country_enabled( $is_country_enabled );

		$min_count                                       = $request->get_param( 'min_news_count_for_important_tweets' );
		$settings['min_news_count_for_important_tweets'] = OpenAIApiSetting::update_min_news_count_for_important_tweets( $min_count );

		$primary_categories             = $request->get_param( 'primary_categories' );
		$settings['primary_categories'] = Category::update_categories( $primary_categories );

		$default_news_category             = $request->get_param( 'default_news_category' );
		$settings['default_news_category'] = Category::set_default_category( $default_news_category );

		$similarity_in_percent             = (int) $request->get_param( 'similarity_in_percent' );
		$settings['similarity_in_percent'] = Setting::update_similarity_in_percent( $similarity_in_percent );

		$num_of_days_for_similarity             = (int) $request->get_param( 'num_of_days_for_similarity' );
		$settings['num_of_days_for_similarity'] = Setting::update_num_of_hours_for_similarity( $num_of_days_for_similarity );

		$news_sync_interval             = (int) $request->get_param( 'news_sync_interval' );
		$settings['news_sync_interval'] = Setting::update_news_sync_interval( $news_sync_interval );

		$news_not_before_in_minutes             = (int) $request->get_param( 'news_not_before_in_minutes' );
		$settings['news_not_before_in_minutes'] = Setting::update_news_not_before_in_minutes( $news_not_before_in_minutes );

		$sync_image_copy_setting_from_source             = $request->get_param( 'sync_image_copy_setting_from_source' );
		$settings['sync_image_copy_setting_from_source'] = Setting::update_sync_image_copy_setting_from_source( $sync_image_copy_setting_from_source );

		$news_sync_method                    = $request->get_param( 'openai_news_sync_method' );
		$settings['openai_news_sync_method'] = OpenAIApiSetting::update_news_sync_method( $news_sync_method );

		$use_linkedin_data                           = $request->get_param( 'use_linkedin_data_for_instagram' );
		$settings['use_linkedin_data_for_instagram'] = OpenAIApiSetting::update_use_linkedin_data_for_instagram( $use_linkedin_data );

		$instagram_new_news_interval             = $request->get_param( 'instagram_new_news_interval' );
		$settings['instagram_new_news_interval'] = OpenAIApiSetting::update_instagram_new_news_interval( $instagram_new_news_interval );

		$external_link_enabled             = $request->get_param( 'external_link_enabled' );
		$settings['external_link_enabled'] = OpenAIApiSetting::update_external_link_enabled( $external_link_enabled );

		$google_vision_secret_key             = $request->get_param( 'google_vision_secret_key' );
		$settings['google_vision_secret_key'] = GoogleVisionClient::update_google_vision_secret_key( $google_vision_secret_key );

		$keyword_news_sync_interval             = $request->get_param( 'keyword_news_sync_interval' );
		$settings['keyword_news_sync_interval'] = KeywordSetting::update_sync_interval( $keyword_news_sync_interval );

		$keyword_item_per_sync             = $request->get_param( 'keyword_item_per_sync' );
		$settings['keyword_item_per_sync'] = KeywordSetting::update_item_per_sync( $keyword_item_per_sync );

		$keyword_global_instruction             = $request->get_param( 'keyword_global_instruction' );
		$settings['keyword_global_instruction'] = KeywordSetting::update_global_instruction( $keyword_global_instruction );

		// @TODO make it background
		SiteStore::send_general_data_to_sites();

		return $this->respondCreated( $settings );
	}

	/**
	 * Sync news
	 *
	 * @param  WP_REST_Request  $request  Full details of request.
	 *
	 * @return WP_REST_Response
	 */
	public function sync_news( WP_REST_Request $request ) {
		$option_id = $request->get_param( 'option_id' );
		$setting   = SyncSettings::get_setting( $option_id );
		if ( ! is_array( $setting ) ) {
			return $this->respondNotFound();
		}

		$data = ArticleStore::sync_news( $setting );
		if ( is_wp_error( $data ) ) {
			return $this->respondWithWpError( $data );
		}

		return $this->respondOK(
			array(
				'settings'             => array(
					'news_sync' => SyncSettings::get_settings(),
					'news_api'  => Setting::get_news_api_keys(),
				),
				'active_news_api_key'  => Setting::get_news_api_key(),
				'records_ids'          => $data['records_ids'] ?? array(),
				'existing_records_ids' => $data['existing_records_ids'] ?? array(),
				'new_records_ids'      => $data['new_records_ids'] ?? array(),
			)
		);
	}

	public function sync_all_news() {
		SyncEventRegistryNews::sync();

		return $this->respondAccepted();
	}

	/**
	 * Sync openAI news
	 *
	 * @return WP_REST_Response
	 */
	public function sync_openai_news( WP_REST_Request $request ) {
		$date = $request->get_param( 'date' );
		if ( Validate::date( $date ) ) {
			$items = ArticleStore::find_by_date( $date );

			if ( count( $items ) < 1 ) {
				return $this->respondNotFound();
			}

			foreach ( $items as $_item ) {
				OpenAiReCreateNews::add_to_sync( (int) $_item['id'] );
			}
		} else {
			OpenAiReCreateOldNews::init()->push_to_queue( array( 'limit' => 100 ) );
		}

		return $this->respondAccepted();
	}

	/**
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'rest_api_init', array( self::$instance, 'register_routes' ) );
		}

		return self::$instance;
	}

	public function create_source( WP_REST_Request $request ): WP_REST_Response {
		$data = array(
			'',
		);

		return $this->respondCreated( $request );
	}
}
