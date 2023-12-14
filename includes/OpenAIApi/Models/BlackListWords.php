<?php

namespace TeraPixelNewsGenerator\OpenAIApi\Models;

use TeraPixelNewsGenerator\OpenAIApi\ApiConnection\NewsCompletion;
use TeraPixelNewsGenerator\OpenAIApi\Setting;
use Stackonet\WP\Framework\Abstracts\OptionModel;

class BlackListWords extends OptionModel {
	protected $option_name = '_openai_black_list_words';
	protected $default_data = [
		'phrase' => '',
	];

	protected $columns = [ 'body', '' ];

	public static function get_existing_news( int $words_count = 5 ) {
		$phrases = self::get_all( $words_count );
		global $wpdb;
		$table = $wpdb->prefix . 'openai_news';

		$sql = "SELECT * FROM {$table} WHERE ";
		foreach ( $phrases as $index => $phrase ) {
			if ( $index > 0 ) {
				$sql .= ' OR';
			}
			$sql .= " body LIKE '%" . esc_sql( $phrase ) . "%'";
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results;
	}

	public static function get_all( $max_words = 'full' ): array {
		$options = ( new static() )->get_options();
		$phrases = [];
		if ( count( $options ) ) {
			$phrases = wp_list_pluck( $options, 'phrase' );
			if ( is_numeric( $max_words ) ) {
				foreach ( $phrases as $index => $phrase ) {
					$pieces            = explode( ' ', $phrase );
					$phrases[ $index ] = implode( ' ', array_splice( $pieces, 0, intval( $max_words ) ) );
				}
			}
		}

		return $phrases;
	}

	/**
	 * Remove blacklist from content
	 *
	 * @param string $content The content to be checked.
	 *
	 * @return string
	 */
	public static function remove_blacklist_phrase( string $content, array $args = [] ): string {
		$similarity = static::get_blacklist_phrase_info_by_similarity( $content );
		if ( ! empty( $similarity['modified'] ) ) {
			$instruction = Setting::get_remove_blacklist_phrase_instruction();
			$instruction = str_replace( '{{content}}', $content, $instruction );
			$instruction = str_replace( '{{suspected_phrase}}', $similarity['matched_paragraph'], $instruction );

			$group     = $args['group'] ?? '';
			$source_id = $args['source_id'] ?? '';
			$result    = NewsCompletion::remove_blacklist_phrase( $instruction, $group, $source_id );
			if ( is_wp_error( $result ) ) {
				return $similarity['modified'];
			}

			return $result;
		}

		return $content;
	}

	/**
	 * Get blacklist phrase information
	 *
	 * @param string $content The content need to be checked.
	 *
	 * @return array
	 */
	public static function get_blacklist_phrase_info_by_similarity( string $content ): array {
		$blacklist_words = self::get_all();
		$data            = [
			'max_match'         => 0,
			'matched_paragraph' => '',
			'matched_against'   => '',
			'modified'          => '',
			'original'          => $content,
		];
		$percents        = [];
		if ( $blacklist_words ) {
			$is_modified = false;
			$paragraphs  = explode( PHP_EOL, $content );
			if ( count( $paragraphs ) > 1 ) {
				foreach ( $paragraphs as $index => $paragraph ) {
					foreach ( $blacklist_words as $phrase ) {
						$percentage = 0;
						similar_text( $paragraph, $phrase, $percentage );
						$percents[] = $percentage;
						if ( isset( $paragraphs[ $index ] ) && $percentage > 40 ) {
							$data['matched_paragraph'] = $paragraphs[ $index ];
							$data['matched_against']   = $phrase;
							unset( $paragraphs[ $index ] );
							$is_modified = true;
						}
					}
				}
			}

			if ( $is_modified ) {
				$data['modified'] = trim( implode( PHP_EOL, $paragraphs ) );
			}
		}

		$data['max_match'] = count( $percents ) ? max( $percents ) : 0;

		return $data;
	}

	/**
	 * Check content using first few words of blacklist words.
	 *
	 * @param string $content The content to be checked.
	 * @param int $words_count Words count.
	 *
	 * @return array
	 */
	public static function get_blacklist_phrase_info_by_strpos( string $content, int $words_count = 3 ): array {
		$blacklist_words = self::get_all( $words_count );
		$data            = [
			'max_match'         => 0,
			'matched_paragraph' => '',
			'matched_against'   => '',
			'modified'          => '',
			'original'          => $content,
		];

		if ( $blacklist_words ) {
			$is_modified = false;
			$paragraphs  = explode( PHP_EOL, $content );
			if ( count( $paragraphs ) > 1 ) {
				foreach ( $paragraphs as $index => $paragraph ) {
					foreach ( $blacklist_words as $phrase ) {
						if ( false !== strpos( $paragraph, $phrase ) ) {
							$data['matched_paragraph'] = $paragraphs[ $index ];
							$data['matched_against']   = $phrase;
							unset( $paragraphs[ $index ] );
							$is_modified = true;
						}
					}
				}
			}

			if ( $is_modified ) {
				$data['modified'] = trim( implode( PHP_EOL, $paragraphs ) );
			}
		}

		return $data;
	}
}
