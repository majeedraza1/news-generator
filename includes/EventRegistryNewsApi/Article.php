<?php

namespace StackonetNewsGenerator\EventRegistryNewsApi;

use Stackonet\WP\Framework\Abstracts\Data;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;

/**
 * Article model
 */
class Article extends Data {
	/**
	 * Get title
	 *
	 * @return string
	 */
	public function get_title(): ?string {
		return $this->get_prop( 'title' );
	}

	/**
	 * Get body
	 *
	 * @return string
	 */
	public function get_body(): ?string {
		return $this->get_prop( 'body' );
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
	 * Get sync settings
	 *
	 * @return SyncSettings
	 */
	public function get_sync_settings(): SyncSettings {
		return new SyncSettings( $this->get_sync_settings_raw() );
	}

	/**
	 * Get sync settings
	 *
	 * @return array
	 */
	public function get_sync_settings_raw(): array {
		$sync_settings = $this->get_prop( 'sync_settings' );
		if ( is_string( $sync_settings ) && strlen( $sync_settings ) ) {
			$sync_settings = maybe_unserialize( $sync_settings );
		}

		return is_array( $sync_settings ) ? $sync_settings : array();
	}

	/**
	 * Get openai news id
	 *
	 * @return int
	 */
	public function get_openai_news_id(): int {
		return (int) $this->get_prop( 'openai_news_id' );
	}

	/**
	 * Get image id
	 *
	 * @return int
	 */
	public function get_image_id(): int {
		return (int) $this->get_prop( 'image_id' );
	}

	/**
	 * Get image URL
	 *
	 * @return string
	 */
	public function get_image_url(): string {
		return (string) $this->get_prop( 'image', '' );
	}

	public function get_body_words_count(): int {
		return (int) $this->get_prop( 'body_words_count' );
	}

	public function title_and_body_words_count(): int {
		return ( (int) $this->get_prop( 'title_words_count' ) + $this->get_body_words_count() );
	}

	/**
	 * Get links
	 *
	 * @return array
	 */
	public function get_links(): array {
		$links = $this->get_prop( 'links' );

		return is_array( $links ) ? $links : array();
	}

	public function get_links_as_string(): string {
		$links = $this->get_links();
		if ( empty( $links ) ) {
			return $this->get_prop( 'url', '' );
		}

		return implode( PHP_EOL, $links );
	}

	/**
	 * Update a field
	 *
	 * @param  string  $column  The column to be updated.
	 * @param  mixed  $value  The value to be updated.
	 *
	 * @return void
	 */
	public function update_field( string $column, $value ) {
		$data            = array( 'id' => $this->get_id() );
		$data[ $column ] = $value;
		( new ArticleStore() )->update( $data );
	}

	/**
	 * Update openai news id
	 *
	 * @param  int  $openai_news_id  OpenAi news id.
	 *
	 * @return void
	 */
	public function update_openai_news_id( int $openai_news_id ) {
		$this->update_field( 'openai_news_id', $openai_news_id );
	}

	/**
	 * Copy article to news
	 *
	 * @return int
	 */
	public function copy_to_news(): int {
		$news_id = $this->get_openai_news_id();
		if ( ! $news_id ) {
			$sync_settings = $this->get_sync_settings();
			$article_data  = array(
				'title'            => $this->get_title(),
				'body'             => $this->get_body(),
				'source_id'        => $this->get_id(),
				'primary_category' => $this->get_primary_category_slug(),
				'sync_status'      => 'in-progress',
				'created_via'      => 'newsapi.ai',
				'sync_setting_id'  => $sync_settings->get_option_id(),
				'live_news'        => $sync_settings->is_live_news_enabled() ? 1 : 0,
			);
			if ( ! $sync_settings->rewrite_metadata() ) {
				$article_data['sync_status']    = 'complete';
				$article_data['openai_skipped'] = 1;
			}
			$news_id = ( new NewsStore() )->create( $article_data );
			$this->update_openai_news_id( $news_id );
		}

		return $news_id;
	}
}
