<?php

namespace StackonetNewsGenerator\OpenAIApi;

use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncInstagramFields;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncTwitterFields;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\OpenAiRestClient;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use WP_Error;

/**
 * OpenAI Rest Client
 */
class Client extends OpenAiRestClient {
	/**
	 * Find interesting news ids
	 *
	 * @param  string  $content  News title.
	 * @param  string  $instruction  Instruction for OpenAI.
	 * @param  array  $source_info  News source information.
	 * @param  bool  $force  Get from api response.
	 *
	 * @return string|WP_Error
	 */
	public static function find_interesting_news(
		string $content,
		string $instruction,
		array $source_info = array(),
		bool $force = false
	) {
		$instruction = str_replace( '{{news_titles_list}}', $content, $instruction );
		$cache_key   = 'openai_find_interesting_news_' . md5( $instruction );
		$result      = get_transient( $cache_key );
		if ( empty( $result ) || $force ) {
			$result = ( new static() )->completions(
				$instruction,
				array(
					'percentage'  => 70,
					'group'       => 'interesting_news',
					'source_type' => $source_info['source_type'] ?? '',
					'source_id'   => $source_info['source_id'] ?? 0,
				)
			);
			if ( ! is_wp_error( $result ) ) {
				set_transient( $cache_key, $result, DAY_IN_SECONDS );
			}
		}

		return $result;
	}

	/**
	 * Tag meta completion
	 *
	 * @param  string  $content
	 * @param  bool  $force
	 *
	 * @return string|WP_Error
	 */
	public static function tag_meta_completions( string $content, array $source_info = array(), bool $force = false ) {
		$instruction = Setting::get_tag_meta_instruction();
		$instruction = str_replace( '{{tag_name}}', $content, $instruction );
		$cache_key   = 'openai_tag_meta_completions_' . md5( $instruction );
		$result      = get_transient( $cache_key );
		if ( empty( $result ) || $force ) {
			$result = ( new static() )->completions(
				$instruction,
				array(
					'percentage'  => 70,
					'group'       => 'tag_meta_description',
					'source_type' => $source_info['source_type'] ?? '',
					'source_id'   => $source_info['source_id'] ?? 0,
				)
			);
			if ( ! is_wp_error( $result ) ) {
				set_transient( $cache_key, $result, DAY_IN_SECONDS );
			}
		}

		return $result;
	}

	/**
	 * @param  News[]  $news_items
	 * @param  array  $source_info
	 * @param  bool  $force
	 *
	 * @return int[]|WP_Error
	 */
	public static function find_important_news_for_tweet( array $news_items, bool $force = false ) {
		$news_ids  = wp_list_pluck( $news_items, 'id' );
		$cache_key = 'find_important_news_for_tweet_' . md5( wp_json_encode( $news_ids ) );
		$result    = get_transient( $cache_key );

		if ( false === $result || $force ) {
			$result = array();
			if ( count( $news_items ) < 1 ) {
				InstagramAttemptLog::success( 'No news', array(), InstagramAttemptLog::LOG_FOR_TWITTER );

				return $result;
			}
			$min_news = Setting::get_min_news_count_for_important_tweets();
			if ( count( $news_items ) < $min_news ) {
				foreach ( $news_items as $item ) {
					OpenAiSyncTwitterFields::add_to_queue( $item );
					$item->update_field( 'important_for_tweet', 1 );
					$result[] = $item->get_id();
				}
				set_transient( $cache_key, $result, DAY_IN_SECONDS );

				InstagramAttemptLog::success(
					sprintf( 'Chosen without sending to OpenAI as only %s news are there.', count( $result ) ),
					$result,
					InstagramAttemptLog::LOG_FOR_TWITTER
				);

				foreach ( $result as $news_id ) {
					InstagramAttemptLog::success(
						sprintf(
							'News #%s is selected for twitter use without sending to openAI. Updating database. Pushing to background task.',
							$news_id
						),
						array( $news_id ),
						InstagramAttemptLog::LOG_FOR_TWITTER
					);
				}

				return $result;
			}
			$title_html = '';
			foreach ( $news_items as $index => $news ) {
				$title_html .= sprintf( '%s. %s', $index + 1, $news->get_title() ) . PHP_EOL;
			}

			$instruction = Setting::get_important_news_for_tweet_instruction();
			$prompt      = str_replace( '{{news_titles_list}}', $title_html, $instruction );

			$response = ( new static() )->completions(
				$prompt,
				array(
					'percentage'  => 70,
					'group'       => 'find_important_news_for_tweet',
					'source_type' => 'sync_settings',
					'source_id'   => wp_generate_uuid4(),
				)
			);
			if ( is_wp_error( $response ) ) {
				InstagramAttemptLog::error(
					'OpenAI error: ' . $response->get_error_message(),
					InstagramAttemptLog::LOG_FOR_TWITTER
				);

				return $response;
			}

			$response = stripslashes( $response );
			$result   = array();
			if ( strlen( $response ) ) {
				$titles = explode( PHP_EOL, $response );
				foreach ( $titles as $title ) {
					foreach ( $news_items as $item ) {
						similar_text( $title, $item->get_title(), $percent );
						if ( $percent > 90 ) {
							InstagramAttemptLog::success(
								sprintf(
									'News #%s is selected for twitter use. Updating database. Pushing to background task.',
									$item->get_id()
								),
								array( $item->get_id() ),
								InstagramAttemptLog::LOG_FOR_TWITTER
							);
							( new NewsStore() )->update(
								array(
									'id'                  => $item->get_id(),
									'important_for_tweet' => 1,
								)
							);
							OpenAiSyncTwitterFields::add_to_queue( $item );
							$result[] = $item->get_id();
						}
					}
				}
			}
			if ( count( $result ) < $min_news ) {
				InstagramAttemptLog::error(
					sprintf(
						'Suggested news (%s news) are less than minimum required news (%s news)',
						count( $result ),
						$min_news
					),
					InstagramAttemptLog::LOG_FOR_TWITTER
				);
			}
			set_transient( $cache_key, $result, DAY_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * @param  News[]  $news_items
	 * @param  array  $source_info
	 * @param  bool  $force
	 *
	 * @return int[]|WP_Error
	 */
	public static function find_important_news_for_instagram( array $news_items, bool $force = false ) {
		$news_ids  = wp_list_pluck( $news_items, 'id' );
		$cache_key = 'find_important_news_for_instagram_' . md5( wp_json_encode( $news_ids ) );
		$result    = get_transient( $cache_key );

		if ( false === $result || $force ) {
			$result = array();
			if ( count( $news_items ) < 1 ) {
				InstagramAttemptLog::success( 'No news' );

				return $result;
			}
			// No need to send to OpenAI when there is only one news.
			if ( count( $news_items ) < 2 ) {
				$item = $news_items[0];
				OpenAiSyncInstagramFields::add_to_queue( $news_items[0] );
				OpenAiSyncTwitterFields::add_to_queue( $news_items[0] );
				$item->update_field( 'use_for_instagram', 1 );
				$item->update_field( 'important_for_tweet', 1 );
				$result[] = $item->get_id();
				set_transient( $cache_key, $result, DAY_IN_SECONDS );

				InstagramAttemptLog::success( 'Chosen without sending to OpenAI as only one news is there.', $result );

				return $result;
			}
			$title_html = '';
			foreach ( $news_items as $index => $news ) {
				$title_html .= sprintf( '%s. %s', $index + 1, $news->get_title() ) . PHP_EOL;
			}

			$instruction = Setting::get_important_news_for_instagram_instruction();
			$prompt      = str_replace( '{{news_titles_list}}', $title_html, $instruction );

			$response = ( new static() )->completions(
				$prompt,
				array(
					'percentage'  => 70,
					'group'       => 'find_important_news_for_instagram',
					'source_type' => 'openai.com',
					'source_id'   => 0,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$response = stripslashes( $response );
			if ( strlen( $response ) ) {
				$titles = explode( PHP_EOL, $response );
				foreach ( $titles as $title ) {
					foreach ( $news_items as $item ) {
						similar_text( $title, $item->get_title(), $percent );
						if ( $percent > 90 ) {
							InstagramAttemptLog::success(
								sprintf(
									'News #%s is selected for instagram use. Updating database. Pushing to background task.',
									$item->get_id()
								)
							);
							( new NewsStore() )->update(
								array(
									'id'                => $item->get_id(),
									'use_for_instagram' => 1,
									'important_for_tweet' => 1,
								)
							);
							OpenAiSyncInstagramFields::add_to_queue( $item );
							OpenAiSyncTwitterFields::add_to_queue( $item );
							$result[] = $item->get_id();
						}
					}
				}
			}

			set_transient( $cache_key, $result, DAY_IN_SECONDS );
			InstagramAttemptLog::success( 'OpenAI find important news for instagram.', $result );
		}

		return $result;
	}
}
