<?php

namespace StackonetNewsGenerator\OpenAIApi;

use ArrayObject;
use Stackonet\WP\Framework\Abstracts\Data;
use Stackonet\WP\Framework\Supports\Logger;
use Stackonet\WP\Framework\Supports\Validate;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\Modules\Keyword\Models\Keyword;
use StackonetNewsGenerator\Modules\Site\BackgroundSendNewsToSite;
use StackonetNewsGenerator\Modules\Site\Site;
use StackonetNewsGenerator\Modules\Site\SiteStore;
use StackonetNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsTagStore;
use StackonetNewsGenerator\Supports\Country;

/**
 * News model
 */
class News extends Data {

	protected $source_news = null;
	protected $tags        = array();

	/**
	 * Get list of fields to sync with openai
	 *
	 * @return string[]
	 */
	public static function get_fields_sync_with_openai(): array {
		$fields = array_keys( NewsCompletion::fields_to_actions() );

		return array_merge( array( 'title' ), $fields );
	}

	/**
	 * Sanitize tag
	 *
	 * @param  mixed $string  The string to be sanitized.
	 *
	 * @return string
	 */
	public static function sanitize_tag( $string ): string {
		if ( ! is_string( $string ) ) {
			return '';
		}
		$string = sanitize_text_field( $string );
		$string = ucfirst( $string );
		$string = preg_replace( '/[^a-zA-Z0-9\s]/', '', $string );

		// Remove number and dot from beginning of string.
		$string = preg_replace( '/^[0-9]*\.?/', '', $string );

		// Remove whitespace from beginning and ending of string.
		$string = trim( $string );

		// String contains only numbers.
		if ( preg_match( '/^[0-9]+$/', $string ) ) {
			return '';
		}

		// String contains only single character.
		if ( strlen( $string ) < 2 ) {
			return '';
		}

		// String contains more than five words.
		if ( str_word_count( $string ) > 5 ) {
			return '';
		}

		return $string;
	}

	/**
	 * Get data
	 *
	 * @return array
	 */
	public function get_data(): array {
		return array(
			'id'                       => $this->get_id(),
			'source_id'                => $this->get_source_id(),
			'title'                    => $this->get_title(),
			'content'                  => $this->get_content(),
			'meta_description'         => $this->get_meta_description(),
			'focus_keyphrase'          => $this->get_focus_keyphrase(),
			'facebook_text'            => $this->get_facebook_text(),
			'instagram_heading'        => $this->get_instagram_heading(),
			'instagram_subheading'     => $this->get_instagram_subheading(),
			'instagram_body'           => $this->get_instagram_body(),
			'instagram_hashtag'        => $this->get_instagram_hashtag(),
			'important_for_instagram'  => $this->is_important_for_instagram(),
			'tweet'                    => $this->get_tweet(),
			'important_for_tweet'      => $this->is_important_for_tweet(),
			'tumblr_text'              => $this->get_tumblr_text(),
			'medium_text'              => $this->get_medium_text(),
			'linkedin_text'            => $this->get_linkedin_text(),
			'tags'                     => $this->get_tags(),
			'tags_info'                => $this->get_tags_with_description(),
			'news_faqs'                => $this->get_news_faqs(),
			'image_id'                 => $this->get_image_id(),
			'image'                    => $this->get_image(),
			'category'                 => $this->get_primary_category(),
			'openai_category'          => $this->get_openai_category(),
			'openai_category_response' => $this->get_prop( 'openai_category_response' ),
			'created_via'              => $this->get_created_via(),
			'sync_status'              => $this->get_sync_status(),
			'concept'                  => $this->get_prop( 'concept' ),
			'sync_setting_id'          => $this->get_sync_setting_id(),
			'sync_setting'             => $this->get_sync_setting(),
			'live_news'                => $this->get_prop( 'live_news' ),
			'source_title'             => $this->get_prop( 'source_title' ),
			'source_uri'               => $this->get_prop( 'source_uri' ),
			'country'                  => array(
				'in_title' => Validate::checked( $this->get_prop( 'has_country_in_title' ) ),
				'code'     => $this->get_prop( 'country_code' ),
				'name'     => Country::get_country_name( $this->get_prop( 'country_code' ) ?? '' ),
			),
			'total_time'               => (int) $this->get_prop( 'total_time' ),
			'total_request_to_openai'  => (int) $this->get_prop( 'total_request_to_openai' ),
			'created'                  => $this->get_prop( 'created_at' ),
			'updated'                  => $this->get_prop( 'updated_at' ),
			'instagram_image'          => $this->get_instagram_image(),
			'extra_images'             => $this->get_extra_images(),
			'extra_videos'             => $this->get_extra_videos(),
		);
	}

	/**
	 * Get source news id
	 *
	 * @return int
	 */
	public function get_source_id(): int {
		return (int) $this->get_prop( 'source_id' );
	}

	/**
	 * Get news title
	 *
	 * @return string|null
	 */
	public function get_title(): ?string {
		return stripslashes( $this->get_prop( 'title' ) );
	}

	/**
	 * Get news content
	 *
	 * @return string|null
	 */
	public function get_content(): ?string {
		return $this->get_prop( 'body' );
	}

	/**
	 * Get meta description for better SEO
	 *
	 * @return string|null
	 */
	public function get_meta_description(): ?string {
		return $this->get_prop( 'meta' );
	}

	/**
	 * Get focus key-phrase
	 *
	 * @return string|null
	 */
	public function get_focus_keyphrase(): ?string {
		return $this->get_prop( 'focus_keyphrase' );
	}

	/**
	 * Get text to share on facebook.com
	 *
	 * @return string|null
	 */
	public function get_facebook_text(): ?string {
		return $this->get_prop( 'facebook' );
	}

	/**
	 * Get instagram text
	 *
	 * @return string
	 */
	public function get_instagram_heading(): ?string {
		return $this->get_prop( 'instagram_heading' );
	}

	/**
	 * Get instagram text
	 *
	 * @return string
	 */
	public function get_instagram_subheading(): ?string {
		return $this->get_prop( 'instagram_subheading' );
	}

	/**
	 * Get instagram text
	 *
	 * @return string
	 */
	public function get_instagram_body(): ?string {
		return $this->get_prop( 'instagram_body' );
	}

	/**
	 * Get instagram hashtag
	 *
	 * @return string
	 */
	public function get_instagram_hashtag(): ?string {
		return $this->get_prop( 'instagram_hashtag' );
	}

	/**
	 * Check if it has instagram content
	 *
	 * @return bool
	 */
	public function has_instagram_content(): bool {
		$heading = $this->get_instagram_heading();
		$body    = $this->get_instagram_body();

		return ! empty( $heading ) && ! empty( $body );
	}

	/**
	 * Get text to share as tweet on twitter.com
	 *
	 * @return string|null
	 */
	public function get_tweet(): ?string {
		return $this->get_prop( 'tweet' );
	}

	/**
	 * If it is important for tweet
	 *
	 * @return bool
	 */
	public function is_important_for_tweet(): bool {
		return Validate::checked( $this->get_prop( 'important_for_tweet' ) );
	}

	/**
	 * If it is important for tweet
	 *
	 * @return bool
	 */
	public function is_important_for_instagram(): bool {
		return Validate::checked( $this->get_prop( 'use_for_instagram' ) );
	}

	/**
	 * Get tumblr text
	 *
	 * @return string|null
	 */
	public function get_tumblr_text(): ?string {
		return $this->get_prop( 'tumblr' );
	}

	/**
	 * Get medium text
	 *
	 * @return string|null
	 */
	public function get_medium_text(): ?string {
		return $this->get_prop( 'medium' );
	}

	/**
	 * Get linkedin text
	 *
	 * @return string|null
	 */
	public function get_linkedin_text(): ?string {
		return $this->get_prop( 'linkedin_text' );
	}

	/**
	 * Get tags list
	 *
	 * @return array
	 */
	public function get_tags(): array {
		$raw_tags = $this->get_prop( 'tags' );
		$tags     = array();
		if ( is_string( $raw_tags ) && strlen( $raw_tags ) > 0 ) {
			$tags = explode( ',', $raw_tags );
		}

		return $tags;
	}

	/**
	 * Parse news tags from string
	 *
	 * @param  mixed|string $raw_tags  String containing raw tags.
	 *
	 * @return array
	 */
	public static function parse_tag( $raw_tags ): array {
		if ( ! ( is_string( $raw_tags ) && strlen( $raw_tags ) > 0 ) ) {
			return array();
		}
		$raw_tags = trim( str_replace( 'Tags:', '', $raw_tags ) );

		if ( false !== strpos( $raw_tags, PHP_EOL ) ) {
			$tags = explode( PHP_EOL, $raw_tags );
		} elseif ( false !== strpos( $raw_tags, ',' ) ) {
			$tags = explode( ',', $raw_tags );
		} elseif ( preg_match_all( '/#(?<tags>[^\s#]+)/i', $raw_tags, $matches ) ) {
			$tags = $matches['tags'] ?? array();
		} else {
			$tags = array();
		}

		if ( count( $tags ) ) {
			$tags = array_map( array( __CLASS__, 'sanitize_tag' ), $tags );
			$tags = array_filter( $tags );
		}

		return $tags;
	}

	public function get_tags_with_description(): array {
		if ( empty( $this->tags ) ) {
			$tags = $this->get_tags();
			if ( count( $tags ) ) {
				$tags_info = NewsTagStore::get_tags_for_names(
					$tags,
					array(
						'source_type' => 'openai.com',
						'source_id'   => $this->get_source_id(),
					)
				);
				foreach ( $tags_info as $value ) {
					$this->tags[ $value['slug'] ] = array(
						'name'             => $value['name'],
						'meta_description' => $value['meta_description'],
					);
				}
			}
		}

		return $this->tags;
	}

	public function get_news_faqs(): array {
		$faqs = $this->get_prop( 'news_faqs' );
		if ( is_string( $faqs ) ) {
			$faqs = maybe_unserialize( $faqs );
		}

		return is_array( $faqs ) ? $faqs : array();
	}

	/**
	 * Get image id
	 *
	 * @return int
	 */
	public function get_image_id(): int {
		return intval( $this->get_prop( 'image_id' ) );
	}

	/**
	 * Get image
	 *
	 * @param  string $size  The image size.
	 *
	 * @return ArrayObject
	 */
	public function get_image( string $size = 'full' ): ArrayObject {
		$src    = wp_get_attachment_image_src( $this->get_image_id(), $size );
		$object = new ArrayObject();
		if ( is_array( $src ) ) {
			$object['id']     = $this->get_image_id();
			$object['url']    = $src[0];
			$object['width']  = $src[1];
			$object['height'] = $src[2];
		}

		return $object;
	}

	/**
	 * Get image id
	 *
	 * @return int
	 */
	public function get_instagram_image_id(): int {
		return intval( $this->get_prop( 'instagram_image_id' ) );
	}

	/**
	 * Get image
	 *
	 * @param  string $size  The image size.
	 *
	 * @return ArrayObject
	 */
	public function get_instagram_image( string $size = 'full' ): ArrayObject {
		$src    = wp_get_attachment_image_src( $this->get_instagram_image_id(), $size );
		$object = new ArrayObject();
		if ( is_array( $src ) ) {
			$object['id']     = $this->get_instagram_image_id();
			$object['url']    = $src[0];
			$object['width']  = $src[1];
			$object['height'] = $src[2];
		}

		return $object;
	}

	/**
	 * Get image id
	 *
	 * @return int[]
	 */
	public function get_extra_images_ids(): array {
		$ids        = maybe_unserialize( $this->get_prop( 'extra_images' ) );
		$images_ids = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$images_ids[] = intval( $id );
			}
		}

		return $images_ids;
	}

	public function get_extra_images( string $size = 'full' ): array {
		$images = array();
		foreach ( $this->get_extra_images_ids() as $id ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( is_array( $src ) ) {
				$images[] = array(
					'id'     => $id,
					'url'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
				);
			}
		}

		return $images;
	}

	/**
	 * Get image id
	 *
	 * @return int[]
	 */
	public function get_extra_videos_ids(): array {
		$ids       = maybe_unserialize( $this->get_prop( 'extra_videos' ) );
		$video_ids = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$video_ids[] = intval( $id );
			}
		}

		return $video_ids;
	}


	/**
	 * Get extra videos
	 *
	 * @return array
	 */
	public function get_extra_videos(): array {
		$videos = array();
		foreach ( $this->get_extra_videos_ids() as $video_id ) {
			$url = wp_get_attachment_url( $video_id );
			if ( ! $url ) {
				continue;
			}
			$metadata = wp_get_attachment_metadata( $video_id );
			$videos[] = array(
				'id'        => $video_id,
				'url'       => $url,
				'width'     => $metadata['width'] ?? 0,
				'height'    => $metadata['height'] ?? 0,
				'mime_type' => $metadata['mime_type'] ?? '',
			);
		}

		return $videos;
	}

	/**
	 * Get primary category
	 *
	 * @return array
	 */
	public function get_primary_category(): array {
		$slug  = $this->get_primary_category_slug();
		$label = ucwords( str_replace( '-', ' ', $slug ) );
		$cats  = Category::get_categories();
		if ( isset( $cats[ $slug ] ) ) {
			$label = $cats[ $slug ];
		}

		return array(
			'name' => $label,
			'slug' => $slug,
		);
	}

	/**
	 * Get primary category slug
	 *
	 * @return string
	 */
	public function get_primary_category_slug(): string {
		return $this->get_prop( 'primary_category', 'general' );
	}

	/**
	 * Get primary category
	 *
	 * @return array
	 */
	public function get_openai_category(): array {
		$slug  = $this->get_openai_category_slug();
		$label = ucwords( str_replace( '-', ' ', $slug ) );
		$cats  = Category::get_categories();
		if ( isset( $cats[ $slug ] ) ) {
			$label = $cats[ $slug ];
		}

		return array(
			'name' => $label,
			'slug' => $slug,
		);
	}

	/**
	 * Get primary category slug
	 *
	 * @return string
	 */
	public function get_openai_category_slug(): string {
		$slug = $this->get_prop( 'openai_category' );
		if ( empty( $slug ) ) {
			$slug = $this->get_primary_category_slug();
		}

		return $slug;
	}

	/**
	 * Get sync status
	 *
	 * @return string
	 */
	public function get_sync_status(): string {
		$statuses = array( 'in-progress', 'complete', 'fail' );
		$status   = $this->get_prop( 'sync_status', 'in-progress' );

		return in_array( $status, $statuses, true ) ? $status : '';
	}

	/**
	 * Get source title
	 *
	 * @return mixed
	 */
	public function get_source_title() {
		if ( ! $this->has_prop( 'source_title' ) ) {
			$source_news = $this->get_source_news();
			$this->set_prop( 'source_title', $source_news['source_title'] ?? '' );
		}

		return $this->get_prop( 'source_title' );
	}

	/**
	 * Get source image url
	 *
	 * @return string
	 */
	public function get_source_image_uri(): string {
		if ( ! $this->has_prop( 'source_image_uri' ) ) {
			$source_news = $this->get_source_news();
			$this->set_prop( 'source_image_uri', $source_news['image'] ?? '' );
		}

		$url = $this->get_prop( 'source_image_uri' );

		return Validate::url( $url ) ? $url : '';
	}

	/**
	 * Get source news
	 *
	 * @return array
	 */
	public function get_source_news(): array {
		if ( is_null( $this->source_news ) ) {
			$article = ( new ArticleStore() )->find_single( $this->get_source_id() );
			if ( is_array( $article ) ) {
				$this->set_source_news( $article );
			} else {
				$this->source_news = array();
			}
		}

		return $this->source_news;
	}

	/**
	 * Set source news
	 *
	 * @param  array $article  The source article.
	 *
	 * @return void
	 */
	public function set_source_news( array $article ): void {
		$this->source_news = $article;
		$this->set_prop( 'concept', $article['concept'] ?? '' );
		$this->set_prop( 'source_title', $article['source_title'] ?? '' );
		$this->set_prop( 'source_uri', $article['source_uri'] ?? '' );
		$this->set_prop( 'source_image_uri', $article['image'] ?? '' );
	}

	public function get_source_uri() {
		if ( ! $this->has_prop( 'source_uri' ) ) {
			$source_news = $this->get_source_news();
			$this->set_prop( 'source_uri', $source_news['source_uri'] ?? '' );
		}

		return $this->get_prop( 'source_uri' );
	}

	/**
	 * Check if it had title and content
	 *
	 * @return bool
	 */
	public function has_title_and_content(): bool {
		return ! empty( $this->get_title() ) && ! empty( $this->get_content() );
	}

	/**
	 * Check if sync complete
	 *
	 * @return bool
	 */
	public function is_sync_complete(): bool {
		return 'complete' === $this->get_sync_status();
	}

	/**
	 * Check if sync is failed
	 *
	 * @return bool
	 */
	public function is_sync_fail(): bool {
		return 'fail' === $this->get_sync_status();
	}

	/**
	 * Check if sync is failed
	 *
	 * @return bool
	 */
	public function is_in_progress(): bool {
		return 'in-progress' === $this->get_sync_status();
	}

	public function total_sync_step(): int {
		return count( static::get_fields_sync_with_openai() );
	}

	/**
	 * Sync step done
	 *
	 * @return int
	 */
	public function sync_step_done(): int {
		$done = 0;
		foreach ( static::get_fields_sync_with_openai() as $column ) {
			if ( ! empty( $this->get_prop( $column ) ) ) {
				++$done;
			}
		}

		return $done;
	}

	/**
	 * Send news to sites
	 *
	 * @param  bool $force  Should send immediately.
	 *
	 * @return void
	 */
	public function send_to_sites( bool $force = false ) {
		$sites = $this->get_sites_list();
		foreach ( $sites as $site_data ) {
			$site = new Site( $site_data );
			if ( $force ) {
				$site->post_news( $this );
			} else {
				BackgroundSendNewsToSite::add_to_queue( $site->get_id(), $this->get_id() );
			}
		}
	}

	/**
	 * Get sites list to send news
	 *
	 * @return array
	 */
	public function get_sites_list(): array {
		$sites       = ( new SiteStore() )->find_multiple();
		$sites_count = count( $sites );
		if ( 1 === $sites_count ) {
			return $sites;
		}
		$concept              = $this->get_concept();
		$category_slug        = $this->get_primary_category_slug();
		$openai_category_slug = $this->get_openai_category_slug();
		$placeholders         = array(
			'http://en.wikipedia.org/wiki/'  => '',
			'https://en.wikipedia.org/wiki/' => '',
		);
		$concept              = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $concept );
		$sites_to_send        = array();
		foreach ( $sites as $site ) {
			$sync_settings = maybe_unserialize( $site['sync_settings'] );
			foreach ( $sync_settings as $sync_setting ) {
				$sync_concept = $sync_setting['concept'] ?? '';
				$sync_concept = str_replace(
					array_keys( $placeholders ),
					array_values( $placeholders ),
					$sync_concept
				);
				if ( ! empty( $sync_concept ) && ! empty( $concept ) ) {
					similar_text( $sync_concept, $concept, $percent );
					if ( $percent >= 90 ) {
						$sites_to_send[ $site['id'] ] = $site;
					}
				}
				$sync_category = $sync_setting['primaryCategory'] ?? '';
				if ( ! empty( $sync_category ) && ! empty( $category_slug ) ) {
					similar_text( $sync_category, $category_slug, $percent );
					if ( $percent >= 90 ) {
						$sites_to_send[ $site['id'] ] = $site;
					}
				}
				if ( ! empty( $sync_category ) && ! empty( $openai_category_slug ) ) {
					similar_text( $sync_category, $openai_category_slug, $percent );
					if ( $percent >= 90 ) {
						$sites_to_send[ $site['id'] ] = $site;
					}
				}
				if ( $this->is_manual() || 1 === $sites_count ) {
					$sites_to_send[ $site['id'] ] = $site;
				}
			}
		}

		return array_values( $sites_to_send );
	}

	/**
	 * Get concept
	 *
	 * @return string
	 */
	public function get_concept(): string {
		if ( ! $this->has_prop( 'concept' ) ) {
			$source_news = $this->get_source_news();
			$concept     = $source_news['concept'] ?? '';
			if ( is_string( $concept ) ) {
				$this->set_prop( 'concept', $concept );
			}
		}

		$concept = $this->get_prop( 'concept' );

		return is_string( $concept ) ? $concept : '';
	}

	/**
	 * Is it created manually?
	 *
	 * @return bool
	 */
	public function is_manual(): bool {
		return 'manual' === $this->get_created_via();
	}

	/**
	 * Get created via
	 *
	 * @return string
	 */
	public function get_created_via(): string {
		$created_via = $this->get_prop( 'created_via' );

		return (string) $created_via;
	}

	/**
	 * Get sync setting id
	 *
	 * @return string
	 */
	public function get_sync_setting_id(): string {
		return (string) $this->get_prop( 'sync_setting_id' );
	}

	/**
	 * Get sync setting
	 *
	 * @return array|false
	 */
	public function get_sync_setting() {
		if ( 'newsapi.ai' === $this->get_created_via() && $this->get_sync_setting_id() ) {
			$settings = SyncSettings::get_setting( $this->get_sync_setting_id() );
			if ( is_array( $settings ) ) {
				$_setting = array();
				foreach ( $settings as $key => $value ) {
					if (
						in_array( $key, array( 'query_info', 'last_sync' ), true ) ||
						( ! is_bool( $value ) && empty( $value ) )
					) {
						continue;
					}
					$_setting[ $key ] = $value;
				}

				return $_setting;
			}

			return $settings;
		}
		if ( 'keyword' === $this->get_created_via() ) {
			$keyword = Keyword::find_single( $this->get_sync_setting_id() );
			if ( $keyword ) {
				return array( 'keyword' => $keyword['keyword'] );
			}
		}

		return false;
	}

	/**
	 * Get logs
	 *
	 * @return array|ApiResponseLog[]
	 */
	public function get_logs(): array {
		return ApiResponseLog::get_logs( $this->get_source_id() );
	}

	/**
	 * Recalculate sync status
	 *
	 * @return void
	 */
	public function recalculate_sync_status() {
		if ( empty( $this->get_title() ) || empty( $this->get_content() ) ) {
			return;
		}
		// Last sync step value.
		$openai_category = $this->get_prop( 'openai_category' );
		if ( is_null( $openai_category ) ) {
			return;
		}

		if ( $this->sync_step_done() >= round( $this->total_sync_step() * .7 ) ) {
			$this->update_field( 'sync_status', 'complete' );
		}
	}

	/**
	 * Update field.
	 *
	 * @param  string $column  table column name.
	 * @param  mixed  $value  The value to be updated.
	 *
	 * @return void
	 */
	public function update_field( string $column, $value ) {
		$this->set_prop( $column, $value );
		$this->apply_changes();
		$data            = array( 'id' => $this->get_id() );
		$data[ $column ] = $value;
		( new NewsStore() )->update( $data );
	}

	/**
	 * Update fields
	 *
	 * @param  array $data  The array of data to be updated.
	 *
	 * @return void
	 */
	public function update_fields( array $data ) {
		$_data = array( 'id' => $this->get_id() );
		foreach ( $data as $column => $value ) {
			$this->set_prop( $column, $value );
			$_data[ $column ] = $value;
		}
		$this->apply_changes();
		( new NewsStore() )->update( $_data );
	}
}
