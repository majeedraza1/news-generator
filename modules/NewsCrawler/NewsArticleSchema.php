<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use Stackonet\WP\Framework\Abstracts\Data;
use StackonetNewsGenerator\Supports\Utils;

/**
 * NewsArticleSchema class
 */
class NewsArticleSchema extends Data {
	/**
	 * Get headline
	 *
	 * @return string
	 */
	public function get_headline(): string {
		return $this->get_prop( 'headline' );
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->get_prop( 'description' );
	}

	/**
	 * Get publish datetime
	 *
	 * @return string
	 */
	public function get_published_datetime(): string {
		return $this->get_prop( 'datePublished' );
	}

	/**
	 * Get modified datetime
	 *
	 * @return string
	 */
	public function get_modified_datetime(): string {
		return $this->get_prop( 'dateModified' );
	}

	/**
	 * Get image object
	 *
	 * @return array
	 */
	public function get_image_object(): array {
		$image = $this->get_prop( 'image' );
		if ( is_array( $image ) && isset( $image['url'] ) ) {
			return $image;
		}

		return array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
		);
	}

	/**
	 * Get image url
	 *
	 * @return string
	 */
	public function get_image_url(): string {
		$image = $this->get_image_object();

		return $image['url'];
	}

	/**
	 * Get description first 5 words
	 *
	 * @return string
	 */
	public function get_description_first_five_words(): string {
		$description = $this->get_description();
		$word_count  = Utils::str_word_count_utf8( $description );
		if ( $word_count >= 5 ) {
			$pieces     = explode( ' ', $description );
			$first_five = implode( ' ', array_splice( $pieces, 0, 5 ) );

			return trim( $first_five );
		}

		return $description;
	}
}
