<?php

namespace StackonetNewsGenerator\OpenAIApi;

use Stackonet\WP\Framework\Supports\Validate;

/**
 * Setting class
 */
class Setting {

	/**
	 * Use linkedin data for instagram
	 *
	 * @return bool
	 */
	public static function use_linkedin_data_for_instagram(): bool {
		$use_linkedin_data = get_option( '_use_linkedin_data_for_instagram', 'yes' );

		return Validate::checked( $use_linkedin_data );
	}

	/**
	 * Use linkedin data for instagram
	 *
	 * @param  mixed  $value  The value to be updated.
	 *
	 * @return bool
	 */
	public static function update_use_linkedin_data_for_instagram( $value ): bool {
		$use_linkedin_data = Validate::checked( $value );
		update_option( '_use_linkedin_data_for_instagram', $use_linkedin_data ? 'yes' : 'no' );

		return $use_linkedin_data;
	}

	/**
	 * News sync method
	 *
	 * @return string
	 */
	public static function news_sync_method(): string {
		$sync_methods = array( 'individual_field', 'full_news' );
		$sync_method  = get_option( '_openai_news_sync_method' );

		return in_array( $sync_method, $sync_methods, true ) ? $sync_method : 'full_news';
	}

	/**
	 * Update news sync method
	 *
	 * @param  mixed  $sync_method  News sync method.
	 *
	 * @return string
	 */
	public static function update_news_sync_method( $sync_method ): string {
		$sync_methods = array( 'individual_field', 'full_news' );
		$sync_method  = in_array( $sync_method, $sync_methods, true ) ? $sync_method : 'full_news';
		update_option( '_openai_news_sync_method', $sync_method );

		return $sync_method;
	}

	/**
	 * Get minimum news count for important tweets
	 *
	 * @return int
	 */
	public static function get_min_news_count_for_important_tweets(): int {
		$min = (int) get_option( '_min_news_count_for_important_tweets' );

		return max( 4, $min );
	}

	/**
	 * Get minimum news count for important tweets
	 *
	 * @param  int|mixed  $value  The value to be updated.
	 *
	 * @return int
	 */
	public static function update_min_news_count_for_important_tweets( $value ): int {
		$value = max( 4, intval( $value ) );
		update_option( '_min_news_count_for_important_tweets', $value );

		return $value;
	}

	/**
	 * If important news for tweet is enabled
	 *
	 * @return bool
	 */
	public static function is_important_news_for_tweets_enabled(): bool {
		$options = get_option( '_important_news_for_tweets_enabled', false );

		return Validate::checked( $options );
	}

	/**
	 * Update important news for tweet is enabled
	 *
	 * @param  mixed  $value  The value to be set.
	 *
	 * @return bool
	 */
	public static function update_important_news_for_tweets_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_important_news_for_tweets_enabled', $value, true );

		return $value;
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function is_auto_sync_enabled(): bool {
		$options = get_option( '_openai_api_auto_sync_enabled', false );

		return Validate::checked( $options );
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function is_news_country_enabled(): bool {
		$options = get_option( '_openai_api_news_country_enabled', true );

		return Validate::checked( $options );
	}

	/**
	 * If auto syncing enabled
	 *
	 * @return bool
	 */
	public static function is_external_link_enabled(): bool {
		$options = get_option( '_openai_api_should_add_external_links', true );

		return Validate::checked( $options );
	}

	/**
	 * If auto syncing enabled
	 *
	 * @param  mixed  $value  The value to be saved.
	 *
	 * @return bool
	 */
	public static function update_external_link_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_openai_api_should_add_external_links', $value, true );

		return $value;
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_is_auto_sync_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_openai_api_auto_sync_enabled', $value );

		return $value;
	}

	/**
	 * Update option for setting auto sync
	 *
	 * @param  mixed  $value  Value to be saved.
	 *
	 * @return bool
	 */
	public static function update_news_country_enabled( $value ): bool {
		$value = Validate::checked( $value );
		update_option( '_openai_api_news_country_enabled', $value );

		return $value;
	}

	/**
	 * Update options
	 *
	 * @param  array  $options  Options to update.
	 *
	 * @return array
	 */
	public static function update_options( array $options ): array {
		$settings = static::sanitize_options( $options );
		update_option( '_openai_api_settings', $settings );

		return $settings;
	}

	/**
	 * Sanitize options
	 *
	 * @param  array  $options  Options to sanitize.
	 *
	 * @return array
	 */
	public static function sanitize_options( array $options ): array {
		$settings = array();
		foreach ( $options as $item ) {
			if ( ! isset( $item['api_key'], $item['limit_per_day'], $item['organization'] ) ) {
				continue;
			}
			$settings[] = array(
				'api_key'       => sanitize_text_field( $item['api_key'] ),
				'organization'  => sanitize_text_field( $item['organization'] ),
				'limit_per_day' => intval( $item['limit_per_day'] ),
			);
		}

		return $settings;
	}

	/**
	 * Update request count
	 */
	public static function update_request_count() {
		$setting            = self::get_api_setting();
		$option_name        = self::get_count_option_name( $setting['api_key'] );
		$request_sent_today = (int) get_option( $option_name );
		update_option( $option_name, ( $request_sent_today + 1 ) );
	}

	/**
	 * Get api key
	 *
	 * @return array
	 */
	public static function get_api_setting(): array {
		$settings = self::get_api_settings();
		$api_key  = array();
		foreach ( $settings as $setting ) {
			if ( $setting['request_sent'] >= $setting['limit_per_day'] ) {
				continue;
			}
			$api_key = $setting;
			break;
		}

		return $api_key;
	}

	/**
	 * Get api keys
	 *
	 * @return array
	 */
	public static function get_api_settings(): array {
		$settings = array();
		foreach ( self::get_options() as $option ) {
			$option                 = wp_parse_args( $option, static::get_defaults() );
			$option['request_sent'] = (int) get_option( self::get_count_option_name( $option['api_key'] ) );

			$settings[] = $option;
		}

		return $settings;
	}

	/**
	 * Get options
	 *
	 * @return array
	 */
	public static function get_options(): array {
		$options = get_option( '_openai_api_settings' );

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Get defaults settings
	 *
	 * @return array
	 */
	private static function get_defaults(): array {
		return array(
			'api_key'       => '',
			'organization'  => '',
			'limit_per_day' => - 1,
		);
	}

	/**
	 * Generate count option name.
	 *
	 * @param  string  $api_key  The api key.
	 * @param  string  $date  Date string af format 'ymd'
	 *
	 * @return string
	 */
	private static function get_count_option_name( string $api_key, string $date = '' ): string {
		if ( empty( $date ) ) {
			$date = gmdate( 'ymd', time() );
		}

		return sprintf( '_openai_request_per_day_%s_%s', $date, md5( $api_key ) );
	}

	/**
	 * Get default for instruction
	 *
	 * @return string[]
	 */
	public static function get_instruction_defaults(): array {
		return array(
			'title'                        => '',
			'body'                         => '',
			'meta'                         => '',
			'tweet'                        => '',
			'facebook'                     => '',
			'instagram_heading'            => '',
			'instagram_subheading'         => '',
			'instagram_body'               => '',
			'instagram_hashtag'            => '',
			'tag'                          => '',
			'tag_meta'                     => '',
			'news_faq'                     => '',
			'tumblr'                       => '',
			'medium'                       => '',
			'linkedin'                     => '',
			'news_filtering'               => '',
			'focus_keyphrase'              => '',
			'category_filter'              => '',
			'news_country'                 => '',
			'interesting_tweets'           => '',
			'important_news_for_tweet'     => '',
			'important_news_for_instagram' => '',
			'remove_blacklist_phrase'      => '',
			'custom_keyword'               => '',
			'beautify_article'             => '',
		);
	}

	/**
	 * Get all instruction
	 *
	 * @return array
	 */
	public static function get_instructions(): array {
		return array(
			'title'                        => static::get_title_instruction(),
			'body'                         => static::get_content_instruction(),
			'meta'                         => static::get_meta_instruction(),
			'tweet'                        => static::get_twitter_instruction(),
			'facebook'                     => static::get_facebook_instruction(),
			'instagram_heading'            => static::get_instagram_heading_instruction(),
			'instagram_subheading'         => static::get_instagram_subheading_instruction(),
			'instagram_body'               => static::get_instagram_body_instruction(),
			'instagram_hashtag'            => static::get_instagram_hashtag_instruction(),
			'tag'                          => static::get_tag_instruction(),
			'tag_meta'                     => static::get_tag_meta_instruction(),
			'news_faq'                     => static::get_news_faq_instruction(),
			'tumblr'                       => static::get_tumblr_instruction(),
			'medium'                       => static::get_medium_instruction(),
			'linkedin'                     => static::get_linkedin_instruction(),
			'news_filtering'               => static::get_news_filtering_instruction(),
			'focus_keyphrase'              => static::get_focus_keyphrase_instruction(),
			'category_filter'              => static::get_category_filter_instruction(),
			'news_country'                 => static::get_news_country_instruction(),
			'interesting_tweets'           => static::get_interesting_tweets_instruction(),
			'important_news_for_tweet'     => static::get_important_news_for_tweet_instruction(),
			'important_news_for_instagram' => static::get_important_news_for_instagram_instruction(),
			'remove_blacklist_phrase'      => static::get_remove_blacklist_phrase_instruction(),
			'custom_keyword'               => static::get_custom_keyword_instruction(),
			'beautify_article'             => static::get_beautify_article_instruction(),
		);
	}

	/**
	 * Get instruction to convert title
	 *
	 * @return string
	 */
	public static function get_title_instruction(): string {
		$instruction = 'Can you please rewrite "{{title}}" in Human tone?' . PHP_EOL;
		$instruction .= 'Please make sure that the title should be SEO based.' . PHP_EOL;
		$instruction .= 'Please hide news agency name or any promotion in between the title.';

		return static::get_instruction_options( 'title', $instruction );
	}

	/**
	 * Get options
	 *
	 * @param  string  $key  Option name.
	 * @param  string|null  $default  Default value.
	 *
	 * @return string|null
	 */
	public static function get_instruction_options( string $key, ?string $default = null ): ?string {
		$defaults = static::get_instruction_defaults();
		$options  = (array) get_option( '_openai_api_instruction_options' );
		$options  = wp_parse_args( $options, $defaults );
		if ( ! empty( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		return $default;
	}

	/**
	 * Get content instruction
	 *
	 * @return string
	 */
	public static function get_content_instruction(): string {
		$instruction = 'Can you please rewrite an article in Human tone about with the title “{{title}}“' . PHP_EOL;
		$instruction .= '{{content}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'Please make sure that the article should be SEO based.' . PHP_EOL;
		$instruction .= 'Please use your own words and sentences to rephrase the content while maintaining the original ideas the article.' . PHP_EOL;
		$instruction .= 'And also make sure that the words limit should be similar to the original article.' . PHP_EOL;
		$instruction .= 'It should be free of plagiarism and reads smoothly users.' . PHP_EOL;
		$instruction .= 'Please hide news agency name or any promotion in between the article.';

		return static::get_instruction_options( 'body', $instruction );
	}

	/**
	 * Get instruction for meta data
	 *
	 * @return string
	 */
	public static function get_meta_instruction(): string {
		$instruction = 'Can you please write a meta description between 150 and 160 characters limit for the article?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'meta', $instruction );
	}

	/**
	 * Get twitter instruction
	 *
	 * @return string
	 */
	public static function get_twitter_instruction(): string {
		$instruction = 'Can you please write a Twitter tweet for the article.' . PHP_EOL;
		$instruction .= ' {{content}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'The tweet should be between 200 and 250 characters limit.';

		return static::get_instruction_options( 'tweet', $instruction );
	}

	/**
	 * Get facebook instruction
	 *
	 * @return string
	 */
	public static function get_facebook_instruction(): string {
		$instruction = 'Can you please write a Facebook post for the article with the hashtags?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'facebook', $instruction );
	}

	/**
	 * Get instagram heading instruction
	 *
	 * @return string
	 */
	public static function get_instagram_heading_instruction(): string {
		$instruction = ' {{content}}' . PHP_EOL . PHP_EOL;

		$instruction .= 'Based on this. Create an engaging headline for Instagram strictly 150 characters. ';
		$instruction .= 'Please craft an article in the style of a leading global news publisher.' . PHP_EOL;
		$instruction .= "Please make it attention-grabbing and suitable for Instagram's audience.";

		return static::get_instruction_options( 'instagram_heading', $instruction );
	}

	/**
	 * Get instagram heading instruction
	 *
	 * @return string
	 */
	public static function get_instagram_subheading_instruction(): string {
		$instruction = ' {{content}}' . PHP_EOL . PHP_EOL;

		$instruction .= 'Heading: {{ig_heading}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'Based on this. Create an engaging sub-headline for Instagram strictly 150 characters. ';
		$instruction .= 'Please craft an article in the style of a leading global news publisher.' . PHP_EOL;
		$instruction .= "Please make it attention-grabbing and suitable for Instagram's audience.";

		return static::get_instruction_options( 'instagram_subheading', $instruction );
	}

	/**
	 * Get instagram body instruction
	 *
	 * @return string
	 */
	public static function get_instagram_body_instruction(): string {
		$instruction = ' {{content}}' . PHP_EOL . PHP_EOL;

		$instruction .= 'Based on this. Can you write a 700 characters body of this news? ';
		$instruction .= 'Please craft an article in the style of a leading global news publisher.' . PHP_EOL;
		$instruction .= "Please make it attention-grabbing and suitable for Instagram's audience.";

		return static::get_instruction_options( 'instagram_body', $instruction );
	}

	/**
	 * Get instagram body instruction
	 *
	 * @return string
	 */
	public static function get_instagram_hashtag_instruction(): string {
		$instruction = ' {{content}}' . PHP_EOL . PHP_EOL;

		$instruction .= 'Based on this news article. Can you generate 3 to 5 hashtag for instagram.com ';

		return static::get_instruction_options( 'instagram_hashtag', $instruction );
	}

	public static function get_instagram_new_news_interval() {
		$interval = (int) get_option( '_instagram_new_news_interval', 30 );

		return max( 15, $interval );
	}

	/**
	 * Update instagram new news interval
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public static function update_instagram_new_news_interval( $value ) {
		$interval = max( 15, (int) $value );
		update_option( '_instagram_new_news_interval', $interval );

		return $interval;
	}

	/**
	 * Get tag instruction
	 *
	 * @return string
	 */
	public static function get_tag_instruction(): string {
		$instruction = 'Can you please write few tags for the article?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'tag', $instruction );
	}

	/**
	 * Get instruction for meta data
	 *
	 * @return string
	 */
	public static function get_tag_meta_instruction(): string {
		$instruction = 'Can you write a meta description to improve SEO score for \'{{tag_name}}\' that group multiple news?';
		$instruction .= ' Limit meta description between 120 and 150 characters including whitespace.';

		return static::get_instruction_options( 'tag_meta', $instruction );
	}

	/**
	 * Get instruction to convert title
	 *
	 * @return string
	 */
	public static function get_news_faq_instruction(): string {
		$instruction = 'Title: {{title}}' . PHP_EOL;
		$instruction .= 'News Article: {{content}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'Based on this above details, Can you write FAQ section?' . PHP_EOL;
		$instruction .= 'Please start question with word (Q:) and answer with word (A:).';

		return static::get_instruction_options( 'news_faq', $instruction );
	}

	/**
	 * Get facebook instruction
	 *
	 * @return string
	 */
	public static function get_tumblr_instruction(): string {
		$instruction = 'Can you please write a tumblr post for the article?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'tumblr', $instruction );
	}

	/**
	 * Get facebook instruction
	 *
	 * @return string
	 */
	public static function get_linkedin_instruction(): string {
		$instruction = 'Can you please write a linkedin post for the news article?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'linkedin', $instruction );
	}

	/**
	 * Get facebook instruction
	 *
	 * @return string
	 */
	public static function get_medium_instruction(): string {
		$instruction = 'Can you please write a medium post for the article?' . PHP_EOL;
		$instruction .= ' {{content}}';

		return static::get_instruction_options( 'medium', $instruction );
	}

	/**
	 * Get instruction to convert title
	 *
	 * @return string
	 */
	public static function get_news_filtering_instruction(): string {
		$instruction = '{{news_titles_list}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'Based on the above news titles, could you select three articles that the audience would find interesting?' . PHP_EOL;
		$instruction .= 'Please reply with only the titles enclosed in square brackets. We don\'t need any descriptions. Thank you!';

		return static::get_instruction_options( 'news_filtering', $instruction );
	}

	public static function get_focus_keyphrase_instruction(): string {
		$instruction = 'Title: {{title}}' . PHP_EOL;
		$instruction .= 'News Article: {{content}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'Based on this above details, Can you write focus keyphrase/keywords?' . PHP_EOL;
		$instruction .= 'One or two words keyphrase are best choice, three words are also acceptable.' . PHP_EOL;
		$instruction .= 'But keyphrase should not exceed more than five words.' . PHP_EOL;
		$instruction .= 'Separate multiple keyphrase with comma. Maximum three keyphrase are allowed.' . PHP_EOL;

		return static::get_instruction_options( 'focus_keyphrase', $instruction );
	}

	public static function get_category_filter_instruction(): string {
		$instruction = 'Title: {{title}}' . PHP_EOL;
		$instruction .= 'News Article: {{content}}' . PHP_EOL;
		$instruction .= 'Categories List:' . PHP_EOL;
		$instruction .= '{{category_list}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'Can you tell me under which category should this title go?' . PHP_EOL;
		$instruction .= 'Only give category. We don\'t need any descriptions. Thank you!' . PHP_EOL;

		return static::get_instruction_options( 'category_filter', $instruction );
	}

	public static function get_news_country_instruction(): string {
		$instruction = 'Title: {{title}}' . PHP_EOL;
		$instruction .= 'News Article: {{content}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'based on the above news, ';
		$instruction .= 'can you tell me if the country name is there on title? Reply with Yes or No.' . PHP_EOL;
		$instruction .= 'Also tell me which country this news is (Replay with ISO country code)?' . PHP_EOL;
		$instruction .= 'Try to find based on any clue like city name or state name or famous person name. ';
		$instruction .= 'Only give answer, no description needed.' . PHP_EOL;
		$instruction .= "Example 1: Title 'Putin to Pay a Visit to Turkey in August, Confirms Erdogan'. Your answer should be Yes, TR as title contains Turkey." . PHP_EOL;
		$instruction .= 'Example 2: No, US; when title does not contain any country name. But news article contain location clue that determine United State.' . PHP_EOL;
		$instruction .= 'Example 3: No, Not Available; There is no location clue in title and content.' . PHP_EOL;

		return static::get_instruction_options( 'news_country', $instruction );
	}

	public static function get_interesting_tweets_instruction(): string {
		$instruction = 'Here is a list of tweets from famous person' . PHP_EOL;
		$instruction .= '{{list_of_tweets}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'based on the above tweets, ';
		$instruction .= 'can you tell me which tweets can be used to write news article?' . PHP_EOL;
		$instruction .= 'Only give answer, no description needed.' . PHP_EOL;

		return static::get_instruction_options( 'interesting_tweets', $instruction );
	}

	public static function get_important_news_for_tweet_instruction(): string {
		$instruction = '{{news_titles_list}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'Based on the above news titles, could you select four articles that the audience would find interesting?' . PHP_EOL;
		$instruction .= 'Please reply with only the titles enclosed in square brackets. We don\'t need any descriptions. Thank you!';

		return static::get_instruction_options( 'important_news_for_tweet', $instruction );
	}

	public static function get_important_news_for_instagram_instruction(): string {
		$instruction = '{{news_titles_list}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'Based on the above news titles, could you select four articles that the audience would find interesting?' . PHP_EOL;
		$instruction .= 'Please reply with only the titles enclosed in square brackets. We don\'t need any descriptions. Thank you!';

		return static::get_instruction_options( 'important_news_for_instagram', $instruction );
	}

	/**
	 * Get instruction for blacklist phrase instruction
	 *
	 * @return string
	 */
	public static function get_remove_blacklist_phrase_instruction(): string {
		$instruction = '{{content}}' . PHP_EOL;
		$instruction .= PHP_EOL;
		$instruction .= 'I suspect you added some phrase that I don\'t want. Please recheck your response and remove any description/instruction.' . PHP_EOL;
		$instruction .= 'We don\'t need any descriptions. Thank you!';

		return static::get_instruction_options( 'remove_blacklist_phrase', $instruction );
	}

	/**
	 * Get instruction for custom keyword
	 *
	 * @return string
	 */
	public static function get_custom_keyword_instruction(): string {
		$instruction = 'Please generate blog post with SEO. ';
		$instruction .= 'The keyword is "{{keyword}}" and we need to add the keyword in title, meta description and contents. ';
		$instruction .= 'Please insert this keyword more than 5 times in the article with 2000 words.';

		return static::get_instruction_options( 'custom_keyword', $instruction );
	}

	/**
	 * Get instruction to beautify ugly news content
	 *
	 * @return string
	 */
	public static function get_beautify_article_instruction(): string {
		$instruction = 'Can you please beautify the article.' . PHP_EOL . PHP_EOL;
		$instruction .= '{{content}}' . PHP_EOL . PHP_EOL;
		$instruction .= 'The article may contain unnecessary words or phrase and may not be punctuated properly. ';
		$instruction .= 'It may not use line break properly.' . PHP_EOL;

		return static::get_instruction_options( 'beautify_article', $instruction );
	}

	/**
	 * Update instruction options
	 *
	 * @param  array  $options  The options to be updated.
	 *
	 * @return array
	 */
	public static function update_instruction_options( array $options ): array {
		$options  = wp_parse_args( $options, static::get_instruction_defaults() );
		$settings = array();
		foreach ( $options as $key => $value ) {
			$settings[ $key ] = sanitize_textarea_field( $value );
		}
		update_option( '_openai_api_instruction_options', $settings );

		return $settings;
	}

	/**
	 * Get sync fields
	 *
	 * @return array
	 */
	public static function get_fields_to_sync(): array {
		$fields = get_option( '_openai_api_fields_to_sync' );
		if ( is_array( $fields ) ) {
			return $fields;
		}

		return array_keys( static::default_fields_to_sync() );
	}

	/**
	 * Should sync field
	 *
	 * @param  string  $field  The field to be sync.
	 *
	 * @return bool
	 */
	public static function should_sync_field( string $field ): bool {
		return in_array( $field, static::get_fields_to_sync(), true );
	}

	/**
	 * Update field to sync
	 *
	 * @param  array|mixed  $fields  The fields to be synced.
	 *
	 * @return array
	 */
	public static function update_fields_to_sync( $fields ): array {
		if ( is_array( $fields ) ) {
			$available_fields = array_keys( static::default_fields_to_sync() );
			$sanitized        = array();
			foreach ( $fields as $field ) {
				if ( in_array( $field, $available_fields, true ) ) {
					$sanitized[] = $field;
				}
			}
			update_option( '_openai_api_fields_to_sync', $sanitized );

			return $sanitized;
		}

		return static::get_fields_to_sync();
	}

	/**
	 * Fields to sync
	 *
	 * @return string[]
	 */
	public static function default_fields_to_sync(): array {
		return array(
			'image_id'        => esc_html__( 'Thumbnail Image', 'stackonet-news-generator' ),
			'openai_category' => esc_html__( 'Category', 'stackonet-news-generator' ),
			'tags'            => esc_html__( 'Tags', 'stackonet-news-generator' ),
			'meta'            => esc_html__( 'Meta Description', 'stackonet-news-generator' ),
			'focus_keyphrase' => esc_html__( 'Focus keyphrase', 'stackonet-news-generator' ),
			'facebook'        => esc_html__( 'Facebook Content', 'stackonet-news-generator' ),
			'tweet'           => esc_html__( 'Twitter Content', 'stackonet-news-generator' ),
			'instagram'       => esc_html__( 'Instagram Content', 'stackonet-news-generator' ),
			'news_faqs'       => esc_html__( 'FAQs', 'stackonet-news-generator' ),
			'country_code'    => esc_html__( 'Country', 'stackonet-news-generator' ),
		);
	}
}
