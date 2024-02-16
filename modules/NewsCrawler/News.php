<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

use Exception;
use StackonetNewsGenerator\Supports\Utils;
use Symfony\Component\DomCrawler\Crawler;

/**
 * News class
 */
class News {
	/**
	 * Symfony Crawler class
	 *
	 * @var Crawler
	 */
	protected $crawler;

	/**
	 * SiteSetting class
	 *
	 * @var SiteSetting|null
	 */
	protected $site_setting;

	/**
	 * All JSON+LD data
	 *
	 * @var array
	 */
	protected $json_ld = array();

	/**
	 * News article JSON+LD data
	 *
	 * @var null|false|NewsArticleSchema
	 */
	protected $news_article_json_ld = null;

	/**
	 * Properties to array
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'heading'            => $this->get_heading(),
			'summery'            => $this->get_summery(),
			'meta_title'         => $this->get_meta_title(),
			'has_json_ld_schema' => $this->has_json_ld_schema_markup(),
			'og:title'           => $this->get_opengraph_title(),
			'og:description'     => $this->get_opengraph_description(),
			'og:image'           => $this->get_opengraph_image(),
			'search_keywords'    => $this->get_search_keywords(),
			'article'            => $this->get_article(),
			'schema'             => $this->get_news_article_schema(),
		);
	}

	/**
	 * Class constructor
	 *
	 * @param  Crawler  $crawler  Symfony Crawler component.
	 * @param  SiteSetting|null  $site_setting  Site settings.
	 */
	public function __construct( Crawler $crawler, ?SiteSetting $site_setting = null ) {
		$this->set_crawler( $crawler );
		$this->site_setting = $site_setting;
	}

	/**
	 * Get crawler class
	 *
	 * @return Crawler
	 */
	public function get_crawler(): Crawler {
		return $this->crawler;
	}

	/**
	 * Set news crawler
	 *
	 * @param  Crawler  $crawler  News crawler.
	 *
	 * @return void
	 */
	public function set_crawler( Crawler $crawler ): void {
		$this->crawler = $crawler;
	}

	/**
	 * Get site setting
	 *
	 * @return SiteSetting|null
	 */
	public function get_site_setting(): ?SiteSetting {
		return $this->site_setting;
	}

	/**
	 * Set site setting
	 *
	 * @param  SiteSetting  $site_setting  Site settings.
	 *
	 * @return void
	 */
	public function set_site_setting( SiteSetting $site_setting ): void {
		$this->site_setting = $site_setting;
	}

	/**
	 * If it has schema markup
	 *
	 * @return bool
	 */
	public function has_json_ld_schema_markup(): bool {
		return false !== strpos( $this->crawler->text(), 'schema.org' );
	}

	/**
	 * Get json ld list
	 *
	 * @return array
	 */
	public function get_json_ld_list(): array {
		if ( empty( $this->json_ld ) ) {
			if ( $this->has_json_ld_schema_markup() ) {
				$list = $this->crawler->filter( 'script[type="application/ld+json"]' );
				$list->each(
					function ( Crawler $node ) {
						$text            = $node->text();
						$this->json_ld[] = json_decode( $text, true );
					}
				);
			}
		}

		return $this->json_ld;
	}

	/**
	 * Get news article schema json
	 *
	 * @return false|NewsArticleSchema
	 */
	public function get_news_article_schema() {
		$list_items = $this->get_json_ld_list();
		if ( is_null( $this->news_article_json_ld ) ) {
			$this->news_article_json_ld = false;
			foreach ( $list_items as $item ) {
				if ( isset( $item['@type'] ) && 'NewsArticle' === $item['@type'] ) {
					$this->news_article_json_ld = new NewsArticleSchema( $item );
				} elseif ( isset( $item['@graph'] ) ) {
					foreach ( $item['@graph'] as $nested_item ) {
						if ( 'NewsArticle' === $nested_item['@type'] ) {
							$this->news_article_json_ld = new NewsArticleSchema( $nested_item );
						}
					}
				}
			}
		}

		return $this->news_article_json_ld;
	}

	/**
	 * Has news article schema data
	 *
	 * @return bool
	 */
	public function has_news_article_schema(): bool {
		return is_array( $this->get_news_article_schema() );
	}

	/**
	 * Get article
	 *
	 * @return string
	 */
	public function get_article(): string {
		$article = $this->get_article_from_article_tag();
		if ( ! empty( $article ) ) {
			$article = $this->find_news_content();
		}

		return $article;
	}

	/**
	 * Get news content
	 *
	 * @return string
	 */
	public function find_news_content(): string {
		try {
			$body = $this->crawler->filter( 'body' )->html();

			return $this->sanitize_article( wp_strip_all_tags( $body ) );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	/**
	 * Get article from html article tag
	 *
	 * @return string
	 */
	public function get_article_from_article_tag(): string {
		try {
			$article = $this->crawler->filter( 'body article' )->first()->text( null, false );

			return $this->sanitize_article( $article );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	/**
	 * Get meta property.
	 *
	 * @param  string  $property  Property name.
	 * @param  mixed  $default_value  Default value.
	 *
	 * @return string
	 */
	public function get_meta_property( string $property, $default_value = '' ): string {
		try {
			return $this->crawler->filter( sprintf( 'meta[property="%s"]', $property ) )->attr( 'content' );
		} catch ( Exception $exception ) {
			return $default_value;
		}
	}

	/**
	 * Get meta property.
	 *
	 * @param  string  $name  Property name.
	 * @param  mixed  $default_value  Default value.
	 *
	 * @return string
	 */
	public function get_meta_name( string $name, $default_value = '' ): string {
		try {
			return $this->crawler->filter( sprintf( 'meta[name="%s"]', $name ) )->attr( 'content' );
		} catch ( Exception $exception ) {
			return $default_value;
		}
	}

	/**
	 * Get heading one
	 *
	 * @return string
	 */
	public function get_heading(): string {
		$schema = $this->get_news_article_schema();
		if ( $schema instanceof NewsArticleSchema ) {
			return $schema->get_headline();
		}
		$heading = $this->get_opengraph_title();
		if ( ! empty( $heading ) ) {
			return $heading;
		}
		try {
			return $this->crawler->filter( 'body h1' )->text();
		} catch ( Exception $exception ) {
			return '';
		}
	}

	/**
	 * Get news summery
	 *
	 * @return string
	 */
	public function get_summery(): string {
		$schema = $this->get_news_article_schema();
		if ( $schema instanceof NewsArticleSchema ) {
			return $schema->get_description();
		}
		$og_description = $this->get_opengraph_description();
		if ( ! empty( $og_description ) ) {
			return $og_description;
		}

		return '';
	}

	/**
	 * Get description first 5 words
	 *
	 * @return string
	 */
	public function get_summery_first_five_words(): string {
		$description = $this->get_summery();
		$word_count  = Utils::str_word_count_utf8( $description );
		if ( $word_count > 5 ) {
			$pieces     = explode( ' ', $description );
			$first_five = implode( ' ', array_splice( $pieces, 0, 5 ) );

			return trim( $first_five );
		}

		return $description;
	}

	/**
	 * Get meta title
	 *
	 * @return string
	 */
	public function get_meta_title(): string {
		return $this->get_meta_property( 'title' );
	}

	/**
	 * Get opengraph title
	 *
	 * @return string
	 */
	public function get_opengraph_title(): string {
		return $this->get_meta_property( 'og:title' );
	}

	/**
	 * Get opengraph description
	 *
	 * @return string
	 */
	public function get_opengraph_description(): string {
		return $this->get_meta_property( 'og:description' );
	}

	/**
	 * Get opengraph image
	 *
	 * @return string
	 */
	public function get_opengraph_image(): string {
		return $this->get_meta_property( 'og:image' );
	}

	/**
	 * Get search keywords
	 *
	 * @return string
	 */
	public function get_search_keywords(): string {
		$keywords = $this->get_meta_name( 'keywords' );
		if ( empty( $keywords ) ) {
			$keywords = $this->get_meta_property( 'search_keywords' );
		}

		return $keywords;
	}

	/**
	 * Sanitize article
	 *
	 * @param  string  $raw_article  Raw article.
	 *
	 * @return string
	 */
	public function sanitize_article( string $raw_article ): string {
		// Remove all content before starting text.
		$summery = $this->get_summery_first_five_words();
		if ( ! empty( $summery ) ) {
			$start_index = mb_strpos( $raw_article, $summery );
			if ( false !== $start_index ) {
				$raw_article = mb_substr( $raw_article, $start_index );
			}
		}

		$lines           = explode( PHP_EOL, $raw_article );
		$sanitized_lines = array();
		$empty_line      = 0;
		foreach ( $lines as $line ) {
			if ( $empty_line >= 4 ) {
				break;
			}
			$line  = trim( $line );
			$count = Utils::str_word_count_utf8( $line );
			if ( $count < 10 ) {
				$sanitized_lines[] = '';
				$empty_line        += 1;
			} else {
				$sanitized_lines[] = $line;
				$empty_line        = 0;
			}
		}
		$raw_article = implode( PHP_EOL, $sanitized_lines );

		return trim( $raw_article );
	}
}
