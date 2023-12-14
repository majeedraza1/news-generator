<?php

namespace TeraPixelNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Supports\RestClient;
use WP_Error;

/**
 * Get client
 */
class Client extends RestClient {
	public function __construct() {
		$this->set_global_parameter( 'apiKey', Setting::get_news_api_key() );
		parent::__construct( 'https://newsapi.ai/api/v1/' );
	}

	/**
	 * Get categories for news
	 *
	 * @return array|WP_Error
	 */
	public function get_categories( array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'count' => 10000, // Get all categories.
			]
		);

		$transient_name = 'news_api_categories_' . md5( wp_json_encode( $args ) );
		$categories     = get_transient( $transient_name );
		if ( false === $categories ) {
			$categories = $this->get( 'suggestCategoriesFast', $args );
			if ( ! is_wp_error( $categories ) ) {
				set_transient( $transient_name, $categories, DAY_IN_SECONDS );
			}
		}

		return $categories;
	}

	/**
	 * Get categories for news
	 *
	 * @return array|WP_Error
	 */
	public function get_locations( string $prefix, array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'lang'   => 'eng', // Required argument.
				'count'  => 2000, // Get all categories.
				'source' => [ 'country' ],
				'prefix' => $prefix,
			]
		);

		$transient_name = 'news_api_locations_' . md5( wp_json_encode( $args ) );
		$locations      = get_transient( $transient_name );
		if ( false === $locations ) {
			$locations = $this->get( 'suggestLocationsFast', $args );
			if ( ! is_wp_error( $locations ) ) {
				set_transient( $transient_name, $locations, DAY_IN_SECONDS );
			}
		}

		return $locations;
	}

	/**
	 * Get categories for news
	 *
	 * @return array|WP_Error
	 */
	public function get_concepts( string $prefix, array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'prefix' => $prefix,
				'lang'   => 'eng',
				'page'   => 1,
				'count'  => 2000,
			]
		);

		$transient_name = 'news_api_concepts_' . md5( wp_json_encode( $args ) );
		$concepts       = get_transient( $transient_name );
		if ( false === $concepts ) {
			$concepts = $this->get( 'suggestConceptsFast', $args );
			if ( ! is_wp_error( $concepts ) ) {
				set_transient( $transient_name, $concepts, DAY_IN_SECONDS );
			}
		}

		return $concepts;
	}

	/**
	 * Get categories for news
	 *
	 * @return array|WP_Error
	 */
	public function get_sources( string $prefix, array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'prefix' => $prefix,
				'lang'   => 'eng',
				'page'   => 1,
				'count'  => 2000,
			]
		);

		$transient_name = 'news_api_sources_' . md5( wp_json_encode( $args ) );
		$concepts       = get_transient( $transient_name );
		if ( false === $concepts ) {
			$concepts = $this->get( 'suggestSourcesFast', $args );
			if ( ! is_wp_error( $concepts ) ) {
				set_transient( $transient_name, $concepts, DAY_IN_SECONDS );
			}
		}

		return $concepts;
	}

	/**
	 * Get articles by location and categories
	 *
	 * @return array|WP_Error {
	 * Data return from response.
	 *
	 * @type int $page Current page.
	 * @type int $totalResults = Total numbers of items available for the query.
	 * @type int $count Items per page.
	 * @type int $pages Total numbers of pages available.
	 * @type object[] $results Array of article object.
	 * }
	 */
	public function get_articles( array $args = [], bool $force = false ) {
		$sanitized_args = $this->get_articles_sanitized_args( $args );
		$transient_name = 'news_api_articles_' . md5( wp_json_encode( $sanitized_args ) );
		$articles       = get_transient( $transient_name );
		if ( false === $articles || $force ) {
			$this->add_headers( 'Content-Type', 'application/json' );
			$articles = $this->get( '/article/getArticles', $sanitized_args );
			Setting::update_news_request_count();
			if ( ! is_wp_error( $articles ) ) {
				if ( is_array( $articles ) && isset( $articles['articles'] ) ) {
					set_transient( $transient_name, $articles['articles'], MINUTE_IN_SECONDS * 15 );
					SyncSettings::update_sync_time( $args );

					return $articles['articles'];
				}
			}
		}

		return $articles;
	}

	/**
	 * Get article sanitized args
	 *
	 * @param  array  $args  The arguments.
	 * @param  bool  $use_advance_query  Should user advance query
	 *
	 * @return array
	 */
	public function get_articles_sanitized_args( array $args, bool $use_advance_query = true ): array {
		$defaults = [
			'dateStart'         => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
			'resultType'        => 'articles',
			'isDuplicateFilter' => 'skipDuplicates',
			'articlesPage'      => 1,
			'articlesCount'     => 100,
			'articlesSortBy'    => 'date',
			'articlesSortByAsc' => false,
			'articleBodyLen'    => - 1, // Use -1 for full article body.
			'dataType'          => 'news',
			'categoryUri'       => '',
			'locationUri'       => '',
			'conceptUri'        => '',
			'sourceUri'         => '',
			'lang'              => '',
			'keyword'           => '',
			'keywordLoc'        => '', // 'title' or 'body' or 'title-or-body' or 'title-and-body'
		];
		$args     = wp_parse_args( $args, $defaults );

		$sanitized_args = [];
		foreach ( $args as $key => $value ) {
			if ( $key && $value && array_key_exists( $key, $defaults ) ) {
				if ( is_array( $value ) ) {
					if ( count( $value ) === 1 ) {
						$value = $value[0];
					} else {
						$value = array_unique( $value );
					}
				}
				$sanitized_args[ $key ] = $value;
			}
		}

		if ( $use_advance_query ) {
			return static::setting_to_args( $sanitized_args );
		}

		return $sanitized_args;
	}

	public static function setting_to_args( array $setting ): array {
		$args = [
			'query'               => '',
			'resultType'          => 'articles',
			'articlesPage'        => $setting['articlesPage'] ?? 1,
			'articlesCount'       => '100',
			'articlesSortBy'      => 'date',
			'articleBodyLen'      => '-1',
			'dataType'            => 'news',
			'includeArticleLinks' => 'true',
		];

		if ( ! isset( $setting['dateStart'] ) ) {
			$setting['dateStart'] = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		}

		if ( ! isset( $setting['dateEnd'] ) ) {
			$setting['dateEnd'] = gmdate( 'Y-m-d', time() );
		}

		$query = [];
		self::format_query_args( $query, $setting, 'conceptUri' );
		self::format_query_args( $query, $setting, 'sourceUri' );
		self::format_query_args( $query, $setting, 'lang' );
		self::format_query_args( $query, $setting, 'categoryUri' );
		self::format_query_args( $query, $setting, 'locationUri' );

		if ( ! empty( $setting['keyword'] ) && ! empty( $setting['keywordLoc'] ) ) {
			if ( in_array( $setting['keywordLoc'], [ 'title', 'body' ], true ) ) {
				$query[] = [
					'keyword'    => $setting['keyword'],
					'keywordLoc' => $setting['keywordLoc'],
				];
			} elseif ( 'title-or-body' === $setting['keywordLoc'] ) {
				$query[] = [
					'$or' => [
						[
							'keyword'    => $setting['keyword'],
							'keywordLoc' => 'title',
						],
						[
							'keyword'    => $setting['keyword'],
							'keywordLoc' => 'body',
						],
					],
				];
			} elseif ( 'title-and-body' === $setting['keywordLoc'] ) {
				$query[] = [
					'$and' => [
						[
							'keyword'    => $setting['keyword'],
							'keywordLoc' => 'title',
						],
						[
							'keyword'    => $setting['keyword'],
							'keywordLoc' => 'body',
						],
					],
				];
			}
		}

		$query[] = [
			'dateStart' => $setting['dateStart'],
			'dateEnd'   => $setting['dateEnd'],
		];

		$filter = [
			'isDuplicate' => 'skipDuplicates',
		];

		if ( count( $query ) ) {
			$query_args = [
				'$query' => [
					'$and' => $query,
				],
			];

			if ( count( $filter ) ) {
				$query_args['$filter'] = $filter;
			}

			$args['query'] = stripslashes( json_encode( $query_args ) );
		}

		return $args;
	}

	public static function format_query_args( array &$query, array $setting, string $key, ?string $target_key = null ) {
		$target_key = $target_key ?? $key;
		if ( isset( $setting[ $key ] ) ) {
			if ( is_string( $setting[ $key ] ) && strlen( $setting[ $key ] ) ) {
				$query[] = [ $target_key => $setting[ $key ] ];
			}
			if ( is_array( $setting[ $key ] ) && count( $setting[ $key ] ) > 0 ) {
				if ( count( $setting[ $key ] ) === 1 ) {
					$query[] = [ $target_key => $setting[ $key ][0] ];
				} else {
					$nested_query = [];
					foreach ( $setting[ $key ] as $value ) {
						$nested_query[] = [ $target_key => $value ];
					}
					$query[] = [ '$or' => $nested_query ];
				}
			}
		}
	}
}
