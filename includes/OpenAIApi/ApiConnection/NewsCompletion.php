<?php

namespace StackonetNewsGenerator\OpenAIApi\ApiConnection;

use Stackonet\WP\Framework\Supports\Logger;
use StackonetNewsGenerator\BackgroundProcess\CopyNewsImage;
use StackonetNewsGenerator\BackgroundProcess\ProcessNewsTag;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\Modules\ExternalLink\Models\ExternalLink;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Setting;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsTagStore;
use StackonetNewsGenerator\Supports\Country;
use StackonetNewsGenerator\Supports\Utils;
use WP_Error;

/**
 * NewsCompletion class
 */
class NewsCompletion extends OpenAiRestClient {

	/**
	 * Re-create news from OpenAI
	 *
	 * @param  Article  $article  Article object.
	 * @param  float  $start_time  Task start time.
	 * @param  bool  $multistep  Should it sync multiple step.
	 *
	 * @return array|WP_Error
	 */
	public static function to_news( Article $article, float $start_time = 0, bool $multistep = false ) {
		if ( 0 === $start_time ) {
			$start_time = microtime( true );
		}

		$news = NewsStore::find_by_source_id( $article->get_id() );
		if ( ! $news instanceof News ) {
			$news = static::article_to_news( $article );
			if ( is_wp_error( $news ) ) {
				return $news;
			}
		}
		$news = static::news_completions( $news, $start_time, $multistep );
		if ( is_wp_error( $news ) ) {
			return $news;
		}

		return $news->to_array();
	}

	/**
	 * Create news title from a newsapi.ai news article
	 *
	 * @param  Article  $article  The Article object.
	 *
	 * @return News|WP_Error
	 */
	public static function article_to_news( Article $article ) {
		$news = NewsStore::find_by_source_id( $article->get_id() );
		if ( $article->get_openai_news_id() || $news instanceof News ) {
			return new WP_Error(
				'duplicate_news',
				sprintf( 'A news with id #%s already exists for the article.', $article->get_id() )
			);
		}

		// Check if content length within approve limit.
		if ( ! OpenAiRestClient::is_valid_for_max_token( $article->title_and_body_words_count() ) ) {
			$error_message = sprintf(
				'It is going to exceed max token. Total words: %s',
				$article->title_and_body_words_count()
			);
			$article->update_field( 'openai_error', $error_message );

			return new WP_Error( 'exceeded_max_token', $error_message );
		}

		$sync_settings = $article->get_sync_settings();

		$data = array(
			'source_id'        => $article->get_id(),
			'primary_category' => $article->get_primary_category_slug(),
			'primary_concept'  => $article->get_concept_basename(),
			'sync_status'      => 'in-progress',
			'created_via'      => 'newsapi.ai',
			'sync_setting_id'  => $sync_settings->get_option_id(),
			'live_news'        => $sync_settings->is_live_news_enabled() ? 1 : 0,
		);

		$title = static::title_completions( $article );
		if ( is_wp_error( $title ) ) {
			$article->update_field( 'openai_error', $title->get_error_message() );

			return $title;
		}

		$data['title'] = $title;

		$news = new News( $data );

		$news_id = ( new NewsStore() )->create( $data );
		$news->set_id( $news_id );

		// Update article.
		$article->update_openai_news_id( $news_id );

		return $news;
	}

	/**
	 * Complete news sync
	 *
	 * @param  News  $news  The news object.
	 * @param  float  $start_time  Start time.
	 * @param  bool  $multistep  If it should sync multiple steps on same time.
	 *
	 * @return News|WP_Error
	 */
	public static function news_completions( News $news, float $start_time = 0, bool $multistep = false ) {
		if ( 0 === $start_time ) {
			$start_time = microtime( true );
		}
		$max_allowed_time = Utils::max_allowed_time();
		$fields           = self::fields_to_actions();

		foreach ( $fields as $news_column => $callback ) {
			$existing_data = $news->get_prop( $news_column );
			if ( ! ( is_null( $existing_data ) || in_array( $existing_data, array( 0, '0' ), true ) ) ) {
				continue;
			}
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$data = call_user_func_array( $callback, array( $news ) );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( false === $multistep ) {
				return $news;
			}
			if ( ( microtime( true ) - $start_time ) >= $max_allowed_time ) {
				return $news;
			}
		}

		$statistic = ApiResponseLog::get_completion_time_and_requests_count( $news->get_source_id() );

		$news->update_fields(
			array(
				'sync_status'             => 'complete',
				'total_time'              => $statistic['total_time'],
				'total_request_to_openai' => $statistic['total_requests'],
			)
		);

		return $news;
	}

	/**
	 * Generate a field value
	 *
	 * @param  News  $news  The news object.
	 * @param  string  $property_name  Property name.
	 *
	 * @return string|array| WP_Error
	 */
	public static function generate_field_value( News $news, string $property_name ) {
		$fields   = self::fields_to_actions();
		$callback = $fields[ $property_name ] ?? false;
		if ( 'production' !== wp_get_environment_type() ) {
			Logger::log( sprintf( 'News: %s; Generating property: %s', $news->get_id(), $property_name ) );
		}
		if ( ! is_callable( $callback ) ) {
			Logger::log(
				sprintf(
					'News: %s; No callable method found for property %s.',
					$news->get_id(),
					$property_name
				)
			);

			return new WP_Error(
				'no_callable',
				sprintf( 'No callable method found for property %s.', $property_name )
			);
		}
		$existing_data = $news->get_prop( $property_name );
		if ( ! empty( $existing_data ) ) {
			Logger::log(
				sprintf(
					'News: %s; Data already exists for property %s.',
					$news->get_id(),
					$property_name
				)
			);

			return $news;
		}

		$value = call_user_func( $callback, $news );
		if ( is_wp_error( $value ) ) {
			Logger::log( $value );

			return $value;
		}
		if ( 'production' !== wp_get_environment_type() ) {
			Logger::log(
				array(
					'message' => 'Generated news property.',
					'field'   => $property_name,
					'value'   => $value,
				)
			);
		}
		$news->set_prop( $property_name, $value );
		$news->update_field( $property_name, $value );

		return $news;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  Article  $article  The Article object.
	 *
	 * @return string|WP_Error
	 */
	public static function title_completions( Article $article ) {
		$response = static::recreate_article(
			array(
				'title'   => sanitize_text_field( $article->get_title() ),
				'content' => sanitize_textarea_field( $article->get_body() ),
			),
			Setting::get_title_instruction(),
			'title',
			$article->get_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$title = sanitize_text_field( $response );
		if ( ! ( is_string( $title ) && mb_strlen( $title ) > 10 ) ) {
			return new WP_Error( 'title_length_error', 'Generated title is too short.' );
		}

		if ( Utils::str_word_count_utf8( $title ) > 30 ) {
			return new WP_Error(
				'title_length_error',
				sprintf( 'Generated title is too long (%s words): %s', Utils::str_word_count_utf8( $title ), $title )
			);
		}

		return $title;
	}

	/**
	 * Recreate article data
	 *
	 * @param  array  $data  The data to be used for instruction.
	 * @param  string  $instruction  Instruction for OpenAI.
	 * @param  string  $group  Group.
	 * @param  int  $object_id  News object id.
	 *
	 * @return string|WP_Error
	 */
	protected static function recreate_article(
		array $data,
		string $instruction,
		string $group = 'unknown',
		int $object_id = 0
	) {
		$cache_key = sprintf( 'openai_news_source_%s_%s', $group, $object_id );

		// Check if it is available on transient cache.
		$result = get_transient( $cache_key );
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( 'tag_meta_description' !== $group ) {
			// Check if it is available on api response log.
			$log = ApiResponseLog::get_log( $object_id, $group );
			if ( $log ) {
				return static::filter_api_response( $log );
			}
		}

		$prompt = $instruction;
		foreach ( $data as $key => $value ) {
			$placeholder = stripslashes( sanitize_text_field( $key ) );
			$prompt      = str_replace( '{{' . $placeholder . '}}', $value, $prompt );
		}

		$result = ( new static() )->completions(
			$prompt,
			array(
				'percentage'  => 'content' === $group ? 48 : 70,
				'group'       => $group,
				'source_type' => 'newsapi.ai',
				'source_id'   => $object_id,
			)
		);
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		}

		return $result;
	}

	public static function remove_blacklist_phrase(
		string $instruction,
		string $group = 'unknown',
		int $object_id = 0
	) {
		$cache_key = sprintf( 'openai_blacklist_filter_%s_%s', $group, $object_id );

		// Check if it is available on transient cache.
		$result = get_transient( $cache_key );
		if ( ! empty( $result ) ) {
			return $result;
		}

		$result = ( new static() )->completions(
			$instruction,
			array(
				'percentage'  => 'content' === $group ? 48 : 70,
				'group'       => $group,
				'source_type' => 'newsapi.ai',
				'source_id'   => $object_id,
			)
		);
		if ( ! is_wp_error( $result ) ) {
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The news object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_body( News $news ) {
		$article  = new Article( $news->get_source_news() );
		$data     = array(
			'title'         => sanitize_text_field( $article->get_title() ),
			'content'       => sanitize_textarea_field( $article->get_body() ),
			'newsapi:links' => sanitize_textarea_field( $article->get_links_as_string() ),
			'openai:title'  => sanitize_text_field( $news->get_title() ),
		);
		$response = static::recreate_article(
			$data,
			Setting::get_content_instruction(),
			'content',
			$article->get_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = stripslashes( $response );

		if ( mb_strlen( $content ) < 100 ) {
			return new WP_Error( 'rest_content_strlen_error', 'Generated content is too short.' );
		}

		if ( Setting::is_external_link_enabled() ) {
			$content = ExternalLink::add_links( $content );
		}

		$news->update_field( 'body', $content );

		return $content;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_meta( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_meta_instruction(),
			'meta_description',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$meta = sanitize_text_field( $response );

		$news->update_field( 'meta', $meta );

		return $meta;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_focus_keyphrase( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_focus_keyphrase_instruction(),
			'focus_keyphrase',
			$news->get_source_id()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$string = stripslashes( $response );
		if ( strlen( $string ) > 255 ) {
			$string = substr( $string, 0, 255 );
		}

		$news->update_field( 'focus_keyphrase', $string );

		return $string;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_tweet( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_twitter_instruction(),
			'tweet',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tweet = stripslashes( $response );

		$news->update_field( 'tweet', $tweet );

		return $tweet;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_facebook( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_facebook_instruction(),
			'facebook',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$facebook_text = stripslashes( $response );
		$news->update_field( 'facebook', $facebook_text );

		return $facebook_text;
	}

	public static function generate_instagram_heading( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_instagram_heading_instruction(),
			'instagram_heading',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response          = stripslashes( $response );
		$instagram_heading = sanitize_text_field( $response );

		// Remove all hashtag from heading.
		preg_match_all( '/#\w+/', $instagram_heading, $matches );
		$matches           = ( $matches[0] ?? array() );
		$instagram_heading = sanitize_text_field( str_replace( $matches, '', $instagram_heading ) );

		$news->update_field( 'instagram_heading', $instagram_heading );

		return $instagram_heading;
	}

	public static function generate_instagram_subheading( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_instagram_subheading_instruction(),
			'instagram_subheading',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response          = stripslashes( $response );
		$instagram_heading = sanitize_text_field( $response );

		// Remove all hashtag from heading.
		preg_match_all( '/#\w+/', $instagram_heading, $matches );
		$matches           = ( $matches[0] ?? array() );
		$instagram_heading = sanitize_text_field( str_replace( $matches, '', $instagram_heading ) );

		$news->update_field( 'instagram_subheading', $instagram_heading );

		return $instagram_heading;
	}

	public static function generate_instagram_body( News $news ) {
		$use_linkedin  = Setting::use_linkedin_data_for_instagram();
		$linkedin_text = $news->get_linkedin_text();
		if ( $use_linkedin && ! empty( $linkedin_text ) ) {
			// Remove all hashtag from heading.
			preg_match_all( '/#\w+/', $linkedin_text, $matches );
			$matches        = ( $matches[0] ?? array() );
			$linkedin_text  = str_replace( 'JUST IN:', '', $linkedin_text );
			$instagram_body = trim( str_replace( $matches, '', $linkedin_text ) );
			$instagram_body = Utils::remove_emoji_multiline( $instagram_body );

			$news->update_field( 'instagram_body', $instagram_body );

			return $instagram_body;
		}

		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_instagram_body_instruction(),
			'instagram_body',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response       = stripslashes( $response );
		$instagram_body = sanitize_textarea_field( $response );

		// Remove all hashtag from heading.
		preg_match_all( '/#\w+/', $instagram_body, $matches );
		$matches        = ( $matches[0] ?? array() );
		$instagram_body = trim( str_replace( $matches, '', $instagram_body ) );
		$instagram_body = Utils::remove_emoji_multiline( $instagram_body );

		$news->update_field( 'instagram_body', $instagram_body );

		return $instagram_body;
	}

	/**
	 * Generate instagram hashtag
	 *
	 * @param  News  $news
	 *
	 * @return string|WP_Error
	 */
	public static function generate_instagram_hashtag( News $news ) {
		$use_linkedin  = Setting::use_linkedin_data_for_instagram();
		$linkedin_text = $news->get_linkedin_text();
		if ( $use_linkedin && ! empty( $linkedin_text ) ) {
			// Remove all hashtag from heading.
			$hashtag = str_replace( 'JUST IN:', '', $linkedin_text );
			preg_match_all( '/#\w+/', $hashtag, $matches );
			$hashtag = implode( ' ', ( $matches[0] ?? array() ) );

			$news->update_field( 'instagram_hashtag', $hashtag );

			return $hashtag;
		}
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_instagram_hashtag_instruction(),
			'instagram_hashtag',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = stripslashes( $response );
		$hashtag  = sanitize_text_field( $response );

		// Sanitize hashtag.
		preg_match_all( '/#\w+/', $hashtag, $matches );
		$hashtag = implode( ' ', ( $matches[0] ?? array() ) );

		$news->update_field( 'instagram_hashtag', $hashtag );

		return $hashtag;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_linkedin_text( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_linkedin_instruction(),
			'linkedin_text',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$linkedin_text = stripslashes( $response );
		$news->update_field( 'linkedin_text', $linkedin_text );

		return $linkedin_text;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_tags( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_tag_instruction(),
			'tags',
			$news->get_source_id()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tags_text = stripslashes( $response );
		$tags_text = NewsTagStore::parse_news_tags( $tags_text );

		// Save tags to database.
		if ( $tags_text ) {
			$tags = explode( ',', $tags_text );
			if ( count( $tags ) > 7 ) {
				$tags = array_slice( $tags, 0, 7 );
			}
			$bg_process  = ProcessNewsTag::init();
			$should_save = false;
			foreach ( $tags as $tag_name ) {
				$tag = NewsTagStore::first_or_create( $tag_name );
				if ( empty( $tag['meta_description'] ) ) {
					$bg_process->push_to_queue(
						array(
							'task'        => 'generate_meta_description',
							'id'          => $tag['id'],
							'source_type' => 'openai.com',
							'source_id'   => $news->get_source_id(),
						)
					);
					$should_save = true;
				}
			}
			if ( $should_save ) {
				$bg_process->save()->dispatch();
			}
		}

		$news->update_field( 'tags', $tags_text );

		return $tags_text;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return array|WP_Error
	 */
	public static function generate_news_faqs( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_news_faq_instruction(),
			'faqs',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = explode( 'Q:', $response );
		$faqs  = array();
		foreach ( $items as $item ) {
			if ( false === strpos( $item, 'A:' ) ) {
				continue;
			}
			$faq    = explode( 'A:', $item );
			$faqs[] = array(
				'question' => trim( $faq[0] ),
				'answer'   => trim( $faq[1] ),
			);
		}

		$news->update_field( 'news_faqs', maybe_serialize( $faqs ) );

		return $faqs;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_tumblr( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_tumblr_instruction(),
			'tumblr',
			$news->get_source_id()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = stripslashes( $response );

		$news->update_field( 'tumblr', $text );

		return $text;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_medium( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_medium_instruction(),
			'medium',
			$news->get_source_id()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$medium_text = stripslashes( $response );
		$news->update_field( 'medium', $medium_text );

		return $medium_text;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return string|WP_Error
	 */
	public static function generate_openai_category( News $news ) {
		$categories                    = Category::titles();
		$placeholders                  = static::get_placeholders( $news );
		$placeholders['category_list'] = sanitize_textarea_field( implode( PHP_EOL, $categories ) );

		$response = static::recreate_article(
			$placeholders,
			Setting::get_category_filter_instruction(),
			'category_filter',
			$news->get_source_id()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$category = stripslashes( $response );
		$category = str_replace( array( 'News Category:', 'Category:' ), '', $category );
		$category = trim( $category );

		if ( false !== strpos( $category, ',' ) ) {
			$_categories = explode( ',', $category );
			foreach ( $_categories as $_category ) {
				$slug     = Category::get_slug_by_title( trim( $_category ) );
				$category = trim( $_category );
				if ( false !== $slug ) {
					break;
				}
			}
		}

		$slug = Category::get_slug_by_title( $category );
		if ( false === $slug ) {
			$news->update_field( 'openai_category_response', $response );
			$news->update_field( 'openai_category', '' );

			return new WP_Error( 'invalid_category_response', 'Invalid category response', $response );
		}

		$news->update_field( 'openai_category', $slug );

		return $slug;
	}

	/**
	 * Recreate from OpenAI Api
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return array|WP_Error
	 */
	public static function generate_country_code( News $news ) {
		$response = static::recreate_article(
			static::get_placeholders( $news ),
			Setting::get_news_country_instruction(),
			'news_country',
			$news->get_source_id()
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$country_info = sanitize_text_field( $response );

		list( $in_title, $country_code ) = explode( ',', $country_info );

		$has_country_code = false === strpos( strtolower( $country_code ), 'not available' );
		$country_code     = substr( trim( $country_code ), 0, 2 );

		if ( ! ( $has_country_code && Country::exists( $country_code ) ) ) {
			$news->update_field( 'country_code', '' );
			$news->update_field( 'has_country_in_title', 0 );
		} else {
			$news->update_field( 'country_code', $country_code );
			$news->update_field( 'has_country_in_title', 'yes' === strtolower( $in_title ) ? 1 : 0 );
		}

		return array(
			'country_code'         => $country_code,
			'has_country_in_title' => 'yes' === strtolower( $in_title ),
		);
	}

	/**
	 * Generate image id.
	 *
	 * @param  News  $news  The News object.
	 *
	 * @return int
	 */
	public static function generate_image_id( News $news ) {
		$image_id = get_transient( 'news_image_id_' . $news->get_id() );
		if ( false === $image_id ) {
			$article       = new Article( $news->get_source_news() );
			$attachment_id = CopyNewsImage::copy_image_as_webp( $article->get_image_url(), $news->get_title() );
			$image_id      = is_numeric( $attachment_id ) ? $attachment_id : 0;
			$news->update_field( 'image_id', $image_id );
			$article->update_field( 'image_id', $image_id );

			set_transient( 'news_image_id_' . $news->get_id(), $image_id, HOUR_IN_SECONDS );
		}

		return $image_id;
	}

	/**
	 * Get placeholders
	 *
	 * @param  News  $news  The news object.
	 *
	 * @return array
	 */
	public static function get_placeholders( News $news ): array {
		$args = array(
			'title'      => sanitize_text_field( $news->get_title() ),
			'content'    => sanitize_textarea_field( $news->get_content() ),
			'ig_heading' => sanitize_textarea_field( $news->get_instagram_heading() ),
		);

		$article = new Article( $news->get_source_news() );

		$args['newsapi:title']   = sanitize_text_field( $article->get_title() );
		$args['newsapi:content'] = sanitize_textarea_field( $article->get_body() );
		$args['newsapi:links']   = sanitize_textarea_field( $article->get_links_as_string() );

		return $args;
	}

	/**
	 * Get table column name to sync method name
	 *
	 * @return array
	 */
	public static function fields_to_actions(): array {
		return array(
			'body'            => array( __CLASS__, 'generate_body' ),
			'image_id'        => array( __CLASS__, 'generate_image_id' ),
			'tags'            => array( __CLASS__, 'generate_tags' ),
			'meta'            => array( __CLASS__, 'generate_meta' ),
			'focus_keyphrase' => array( __CLASS__, 'generate_focus_keyphrase' ),
			'facebook'        => array( __CLASS__, 'generate_facebook' ),
			'tweet'           => array( __CLASS__, 'generate_tweet' ),
			'news_faqs'       => array( __CLASS__, 'generate_news_faqs' ),
			'country_code'    => array( __CLASS__, 'generate_country_code' ),
			'openai_category' => array( __CLASS__, 'generate_openai_category' ),
		);
	}
}
