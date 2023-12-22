<?php

namespace StackonetNewsGenerator\Modules\Site;

use Stackonet\WP\Framework\Supports\Logger;
use Stackonet\WP\Framework\Supports\RestClient;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\Language;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\Modules\Site\Stores\NewsToSiteLogStore;
use StackonetNewsGenerator\OpenAIApi\News;
use WP_Error;

class Site {
	const WEBHOOK_NAMESPACE = 'wp-json/stackonet-news-receiver';
	const WEBHOOK_VERSION = 'v1';
	/**
	 * Get defaults data
	 */
	private static $defaults = array(
		'id'               => 0,
		'site_url'         => '',
		'auth_credentials' => '',
		'auth_username'    => '',
		'auth_password'    => '',
		'auth_type'        => 'Bearer',
		'auth_get_args'    => '',
		'auth_post_params' => '',
	);
	/**
	 * The site data
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * The construct of the class
	 *
	 * @param  array  $data  Raw data to set
	 */
	public function __construct( array $data = array() ) {
		$this->data = wp_parse_args( $data, static::$defaults );
	}

	/**
	 * Get site id
	 *
	 * @return int
	 */
	public function get_id(): int {
		return (int) $this->data['id'] ?? 0;
	}

	/**
	 * Get site url
	 *
	 * @return string
	 */
	public function get_site_url(): string {
		return $this->data['site_url'] ?? '';
	}

	/**
	 * Post a news to a site.
	 *
	 * @param  News  $news  The news object.
	 *
	 * @return array|WP_Error
	 */
	public function post_news( News $news ) {
		if ( ! $news->is_sync_complete() ) {
			return new WP_Error( 'sync_not_complete', 'Syncing is not complete yet.' );
		}
		$news_array                     = $news->to_array();
		$news_array['source_image_uri'] = $news->get_source_image_uri();

		$response = $this->get_client()->post( 'webhook/news', wp_json_encode( $news_array ) );
		if ( is_wp_error( $response ) ) {
			Logger::log( $response );

			return $response;
		}
		$data = $response['data'] ?? array();
		if ( isset( $data['news_id'], $data['news_url'] ) ) {
			NewsToSiteLogStore::create_if_not_exists(
				array(
					'news_id'         => $news->get_id(),
					'site_id'         => $this->get_id(),
					'remote_site_url' => $this->get_site_url(),
					'remote_news_id'  => $data['news_id'],
					'remote_news_url' => $data['news_url'],
				)
			);
		}
		$this->update_last_sync_datetime();

		return $response;
	}

	public function get_client(): RestClient {
		$client = new RestClient( $this->get_base_url() );
		$client->add_headers( 'Content-Type', 'application/json' );
		if ( 'Basic' === $this->get_auth_type() ) {
			$client->add_auth_header(
				base64_encode( sprintf( '%s:%s', $this->data['auth_username'], $this->data['auth_password'] ) ),
				'Basic'
			);
		} elseif ( 'Bearer' === $this->get_auth_type() ) {
			$client->add_auth_header( $this->data['auth_credentials'], 'Bearer' );
		} elseif ( 'Append-to-Url' === $this->get_auth_type() ) {
			$client->set_global_parameter( $this->data['auth_get_args'], $this->data['auth_credentials'] );
		}

		return $client;
	}

	/**
	 * Get base url
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		return sprintf(
			'%s/%s/%s',
			rtrim( $this->data['site_url'], '/' ),
			static::WEBHOOK_NAMESPACE,
			static::WEBHOOK_VERSION
		);
	}

	/**
	 * Get auth type
	 *
	 * @return string
	 */
	public function get_auth_type(): string {
		return $this->data['auth_type'];
	}

	/**
	 * Update last sync datetime
	 *
	 * @return void
	 */
	public function update_last_sync_datetime() {
		( new SiteStore() )->update(
			array(
				'id'                 => (int) $this->data['id'],
				'last_sync_datetime' => current_time( 'mysql', true ),
			)
		);
	}

	public function get_sync_settings() {
		$response = $this->get_client()->get(
			'webhook/data',
			array( 'action' => 'update_sync_settings' )
		);
		if ( ! is_wp_error( $response ) ) {
			$this->update_last_sync_datetime();
		}

		return $response;
	}

	/**
	 * Get tags list
	 *
	 * @return array|WP_Error
	 */
	public function get_tags_list() {
		$response = $this->get_client()->get(
			'webhook/data',
			array(
				'action'                => 'get_tags_list',
				'hide_with_description' => true,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data']['tags'] ?? array();
	}

	/**
	 * Get categories list
	 *
	 * @return array|WP_Error
	 */
	public function get_categories_list() {
		$response = $this->get_client()->get( 'categories' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'] ?? array();
	}

	/**
	 * Send general data to site
	 *
	 * @return array|WP_Error
	 */
	public function send_general_data() {
		return $this->post_data( static::general_data(), 'general_data' );
	}

	/**
	 * Post data
	 *
	 * @param  array  $data  The data to be passed.
	 * @param  string  $action  The action.
	 *
	 * @return array|WP_Error
	 */
	public function post_data( array $data, string $action ) {
		$response = $this->get_client()->post(
			'webhook/data',
			$this->format_for_response( $action, $data )
		);
		if ( ! is_wp_error( $response ) ) {
			$this->update_last_sync_datetime();
		}

		return $response;
	}

	/**
	 * Format data for response
	 *
	 * @param  string  $action  The name of action.
	 * @param  mixed  $payload  The data to send for an action.
	 *
	 * @return false|string
	 */
	public function format_for_response( string $action, $payload ) {
		return wp_json_encode(
			array(
				'action'  => $action,
				'payload' => $payload,
			)
		);
	}

	/**
	 * General data to be sent to site
	 *
	 * @return array
	 */
	public static function general_data(): array {
		$settings   = SyncSettings::get_settings();
		$concepts   = array();
		$sources    = array();
		$locations  = array();
		$categories = array();
		foreach ( $settings as $setting ) {
			if ( isset( $setting['concepts'] ) && is_array( $setting['concepts'] ) ) {
				foreach ( $setting['concepts'] as $concept ) {
					$concepts[] = array(
						'label' => $concept['label']['eng'],
						'value' => $concept['uri'],
						'type'  => $concept['type'],
					);
				}
			}
			if ( isset( $setting['sources'] ) && is_array( $setting['sources'] ) ) {
				foreach ( $setting['sources'] as $source ) {
					$sources[] = array(
						'label' => $source['title'],
						'value' => $source['uri'],
						'type'  => $source['dataType'],
					);
				}
			}
			if ( isset( $setting['categories'] ) && is_array( $setting['categories'] ) ) {
				foreach ( $setting['categories'] as $category ) {
					$categories[] = array(
						'label'     => $category['label'],
						'value'     => $category['uri'],
						'parentUri' => $category['parentUri'],
					);
				}
			}
			if ( isset( $setting['locations'] ) && is_array( $setting['locations'] ) ) {
				foreach ( $setting['locations'] as $location ) {
					$locations[] = array(
						'label' => $location['label']['eng'],
						'value' => $location['wikiUri'],
						'type'  => $location['type'],
					);
				}
			}
		}
		$languages = array();
		foreach ( Language::languages() as $code => $label ) {
			$languages[] = array(
				'label' => $label,
				'value' => $code,
			);
		}

		$mainCategories = array();
		foreach ( Category::get_categories() as $cat => $cat_label ) {
			$mainCategories[] = array(
				'label' => $cat_label,
				'value' => $cat,
			);
		}

		return array(
			'languages'         => $languages,
			'concepts'          => $concepts,
			'categories'        => $categories,
			'locations'         => $locations,
			'sources'           => $sources,
			'primaryCategories' => $mainCategories,
		);
	}
}
