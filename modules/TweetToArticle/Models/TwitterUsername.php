<?php

namespace TeraPixelNewsGenerator\Modules\TweetToArticle\Models;

use Stackonet\WP\Framework\Abstracts\OptionModel;

/**
 * TwitterUsername class
 */
class TwitterUsername extends OptionModel {
	/**
	 * Option name
	 *
	 * @var string
	 */
	protected $option_name = '_twitter_usernames';

	/**
	 * Default data
	 *
	 * @var string[]
	 */
	protected $default_data = [
		'user_id'  => '',
		'name'     => '',
		'username' => '',
	];

	/**
	 * Get user info based on username
	 *
	 * @param string $username Twitter username.
	 *
	 * @return false|array
	 */
	public static function find_by_username( string $username ) {
		$options = ( new static() )->get_options();
		$user    = false;
		foreach ( $options as $option ) {
			if ( $username === $option['username'] ) {
				$user = $option;
				break;
			}
		}

		return $user;
	}

	/**
	 * Prepare item for database
	 *
	 * @param array $item The item to be sanitized.
	 *
	 * @return array
	 */
	public function prepare_item_for_database( array $item ): array {
		return [
			'user_id'  => $item['id'],
			'name'     => $item['name'],
			'username' => $item['username'],
		];
	}
}
