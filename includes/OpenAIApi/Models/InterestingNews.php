<?php

namespace TeraPixelNewsGenerator\OpenAIApi\Models;

use TeraPixelNewsGenerator\EventRegistryNewsApi\ArticleStore;
use TeraPixelNewsGenerator\EventRegistryNewsApi\SyncSettings;
use TeraPixelNewsGenerator\OpenAIApi\Client;
use TeraPixelNewsGenerator\OpenAIApi\Setting;
use Stackonet\WP\Framework\Abstracts\DatabaseModel;
use WP_Error;

/**
 * InterestingNews class
 */
class InterestingNews extends DatabaseModel {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'event_registry_interesting_news';

	/**
	 * News items
	 *
	 * @var array
	 */
	protected $news = [];

	/**
	 * Find by setting id.
	 *
	 * @param string $setting_id Setting id.
	 *
	 * @return false|static
	 */
	public static function find_by_setting_id( string $setting_id ) {
		$now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$now->modify( '- 15 minutes' );
		$query = static::get_query_builder();
		$query->where( 'setting_id', $setting_id );
		$query->where( 'created_at', $now->format( 'Y-m-d H:i:s' ), '>=' );
		$item = $query->first();
		if ( $item ) {
			return new static( $item );
		}

		return false;
	}

	/**
	 * @param array $articles
	 * @param array $sync_option
	 * @param bool $force
	 *
	 * @return InterestingNews|WP_Error
	 */
	public static function generate_list_via_openai( array $articles, array $sync_option, bool $force = false ) {
		$sync_setting = new SyncSettings( $sync_option );
		$existing     = static::find_by_setting_id( $sync_setting->get_option_id() );
		if ( $existing instanceof self ) {
			return new WP_Error( 'already_synced', 'Settings already synced.' );
		}
		$instruction = $sync_setting->get_prop( 'news_filtering_instruction' );
		if ( empty( $instruction ) ) {
			$instruction = Setting::get_news_filtering_instruction();
		}
		$title_html = '';
		$based_on   = [];
		foreach ( $articles as $index => $article ) {
			$total_words = intval( $article['title_words_count'] ) + intval( $article['body_words_count'] );
			if ( ! Client::is_valid_for_max_token( $total_words ) ) {
				continue;
			}
			$is_duplicate = ArticleStore::is_it_duplicate( $article );
			if ( $is_duplicate ) {
				continue;
			}
			$based_on[] = intval( $article['id'] );
			$title_html .= sprintf( '%s. %s', $index + 1, $article['title'] ) . PHP_EOL;
		}

		$log_data       = [
			'raw_news_ids'            => wp_list_pluck( $articles, 'id' ),
			'news_ids_for_suggestion' => $based_on,
			'openai_api_instruction'  => str_replace( '{{news_titles_list}}', $title_html, $instruction ),
			'sync_settings'           => $sync_setting->get_client_query_args(),
			'setting_id'              => $sync_setting->get_option_id(),
			'primary_category'        => $sync_setting->get_primary_category(),
			'suggested_news_ids'      => [],
			'total_suggested_news'    => 0,
		];
		$log_data['id'] = static::create( $log_data );

		$response = Client::find_interesting_news(
			$title_html,
			$instruction,
			[
				'source_type' => 'sync_settings',
				'source_id'   => $log_data['id'],
			],
			$force
		);

		if ( is_wp_error( $response ) ) {
			static::update(
				[
					'id'                  => $log_data['id'],
					'openai_api_response' => $response->get_error_message(),
				]
			);

			$response->add( 'batch_info', wp_json_encode( $log_data ) );

			return $response;
		}

		$selected_titles = static::parse_openai_response_for_titles( $response );
		$selected        = static::get_select_news_ids_from_title( $articles, $selected_titles, $response );

		$log_data['openai_api_response']  = $response;
		$log_data['suggested_news_ids']   = $selected;
		$log_data['total_suggested_news'] = count( $selected );

		static::update( $log_data );

		return new InterestingNews( $log_data );
	}

	/**
	 * @param $settings
	 *
	 * @return array
	 */
	public static function sanitize_sync_settings( $settings ): array {
		$options = [];
		foreach ( $settings as $key => $value ) {
			if ( empty( $value ) || in_array( $key, [ 'query_info', 'last_sync' ], true ) ) {
				continue;
			}
			$options[ $key ] = $value;
		}

		return $options;
	}

	public static function parse_openai_response_for_titles( string $string ): array {
		preg_match_all( '/\[(.*?)\]/', $string, $matches );
		$indexes = [];
		if ( isset( $matches[1] ) && count( $matches[1] ) ) {
			foreach ( $matches[1] as $line ) {
				preg_match( '/^(?P<index>\d+)(.)(?P<text>.*)?/', $line, $_match );
				if ( isset( $_match['text'] ) ) {
					$indexes[] = rtrim( str_replace( [ '[', ']' ], '', $_match['text'] ) );
				} else {
					$indexes[] = rtrim( str_replace( [ '[', ']' ], '', $line ) );
				}
			}
		} else {
			preg_match_all( '/(?P<index>\d+)(.)(?P<texts>.*)?/', $string, $_match );
			if ( isset( $_match['texts'] ) ) {
				$indexes = array_map(
					function ( $value ) {
						return trim( str_replace( [ '[', ']' ], '', $value ) );
					},
					$_match['texts']
				);
			}
		}

		return $indexes;
	}

	/**
	 * @param array $articles
	 * @param array $titles
	 * @param $response
	 *
	 * @return array
	 */
	public static function get_select_news_ids_from_title( array $articles, array $titles, $response ): array {
		$selected  = [];
		$_articles = [];
		foreach ( $articles as $article ) {
			$_articles[ $article['id'] ] = $article['title'];
			if (
				in_array( $article['title'], $titles, true ) ||
				false !== strpos( sanitize_text_field( $response ), sanitize_text_field( $article['title'] ) )
			) {
				$selected[] = $article['id'];
			}
		}
		if ( count( $selected ) < count( $titles ) ) {
			foreach ( $titles as $title ) {
				foreach ( $_articles as $article_id => $article_title ) {
					if ( in_array( $article_id, $selected ) ) {
						continue;
					}
					similar_text( $title, $article_title, $percentage );
					if ( $percentage > 80 ) {
						$selected[] = $article_id;
					}
				}
			}
		}

		return array_unique( $selected );
	}

	/**
	 * Create table
	 */
	public static function create_table() {
		global $wpdb;
		$self    = new static();
		$table   = $self->get_table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
				`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `raw_news_ids` longtext NULL DEFAULT NULL,
                `news_ids_for_suggestion` longtext NULL DEFAULT NULL,
                `suggested_news_ids` text NULL DEFAULT NULL,
    			`openai_news_ids` longtext NULL DEFAULT NULL,
                `openai_news_ids` longtext NULL DEFAULT NULL,
                `openai_api_instruction` longtext NULL DEFAULT NULL,
                `openai_api_response` longtext NULL DEFAULT NULL,
                `total_suggested_news` INT NOT NULL DEFAULT '0',
                `total_recreated_news` INT NOT NULL DEFAULT '0',
                `primary_category` VARCHAR(50) NOT NULL DEFAULT 'general',
                `sync_settings` longtext NULL DEFAULT NULL,
    			`created_via` VARCHAR(50) NULL DEFAULT NULL,
    			`setting_id` CHAR(36) NULL DEFAULT NULL,
				`created_at` datetime NULL DEFAULT NULL,
				`updated_at` datetime NULL DEFAULT NULL,
				PRIMARY KEY (id)
		) {$collate}";

		$version = get_option( $table . '_version', '0.1.0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			update_option( $table . '_version', '1.0.0' );
		}
	}

	public function to_array(): array {
		$data                           = parent::to_array();
		$data['created_at']             = mysql_to_rfc3339( $data['created_at'] );
		$data['updated_at']             = mysql_to_rfc3339( $data['updated_at'] );
		$data['openai_api_instruction'] = nl2br( $data['openai_api_instruction'] );
		$data['openai_api_response']    = nl2br( $data['openai_api_response'] );
		$data['sync_settings']          = $this->get_sync_settings();

		return $data;
	}

	public function get_sync_settings(): array {
		$settings = $this->get_prop( 'sync_settings' );
		$settings = is_array( $settings ) ? $settings : [];

		return static::sanitize_sync_settings( $settings );
	}

	public function get_openai_news_ids(): array {
		$ids = $this->get_prop( 'openai_news_ids' );
		$ids = is_array( $ids ) ? $ids : [];
		if ( count( $ids ) < 1 ) {
			$ids = $this->recalculate_openai_news_ids();
		}

		return $ids;
	}

	public function recalculate_openai_news_ids(): array {
		$ids         = [];
		$source_news = $this->get_source_news();
		foreach ( $source_news as $news ) {
			$openai_news_id = isset( $news['openai_news_id'] ) ? intval( $news['openai_news_id'] ) : 0;
			if ( $openai_news_id < 1 ) {
				continue;
			}
			if ( in_array( $news['id'], $this->get_suggested_news_ids(), true ) ) {
				$ids[] = $openai_news_id;
			}
		}
		static::update(
			[
				'id'                   => $this->get_id(),
				'openai_news_ids'      => $ids,
				'total_recreated_news' => count( $ids ),
			]
		);
		$this->set_prop( 'openai_news_ids', $ids );
		$this->set_prop( 'total_recreated_news', count( $ids ) );

		return $ids;
	}

	/**
	 * Get source news
	 *
	 * @return array
	 */
	public function get_source_news(): array {
		if ( empty( $this->news ) ) {
			$this->news = ( new ArticleStore() )->find_multiple(
				[
					'id__in' => $this->get_prop( 'raw_news_ids' ),
					'limit'  => count( $this->get_prop( 'raw_news_ids' ) ),
				]
			);
		}

		return $this->news;
	}

	public function get_suggested_news_ids(): array {
		$ids = $this->get_prop( 'suggested_news_ids' );

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	public function recalculate_suggested_news_ids(): array {
		$response = $this->get_prop( 'openai_api_response' );
		$articles = ( new ArticleStore() )->find_multiple(
			[
				'id__in' => $this->get_prop( 'news_ids_for_suggestion' ),
				'limit'  => count( $this->get_prop( 'news_ids_for_suggestion' ) ),
			]
		);
		$titles   = static::parse_openai_response_for_titles( $response );
		$selected = static::get_select_news_ids_from_title( $articles, $titles, $response );
		$this->set_prop( 'suggested_news_ids', $selected );
		$this->set_prop( 'total_suggested_news', count( $selected ) );
		static::update(
			[
				'suggested_news_ids'   => $selected,
				'total_suggested_news' => count( $selected ),
				'id'                   => $this->get_id(),
			]
		);

		return $selected;
	}

	/**
	 * Delete old logs
	 *
	 * @param int $day Number of days.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function delete_old_logs( int $day = 3 ) {
		global $wpdb;
		$self  = new static();
		$table = $self->get_table_name();

		$day         = max( 1, $day );
		$day_or_days = 1 === $day ? '- 1 day' : sprintf( '- %s days', $day );
		$datetime    = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$datetime->modify( $day_or_days );

		$sql = "DELETE FROM `{$table}` WHERE 1 = 1";
		$sql .= $wpdb->prepare( " AND created_at <= %s", $datetime->format( 'Y-m-d H:i:s' ) );

		$wpdb->query( $sql );
	}
}
