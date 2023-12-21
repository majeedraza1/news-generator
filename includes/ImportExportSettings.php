<?php

namespace StackonetNewsGenerator;

use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\Category;
use StackonetNewsGenerator\EventRegistryNewsApi\Setting;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\OpenAIApi\Setting as OpenAIApiSetting;
use StackonetNewsGenerator\Providers\GoogleVisionClient;
use Stackonet\WP\Framework\Media\UploadedFile;
use Stackonet\WP\Framework\Supports\RestClient;

/**
 * ImportExportSettings class
 */
class ImportExportSettings {
	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'export_filters', array( self::$instance, 'custom_export' ) );
			add_action( 'export_wp', array( self::$instance, 'export_wp' ) );
			add_action( 'admin_init', array( self::$instance, 'register_importer' ) );
		}
	}

	public function register_importer() {
		register_importer(
			'falahcoin-import-settings',
			'TeraPixel import News settings',
			'Lets you import those things',
			array( $this, 'do_import' )
		);
	}

	public function do_import() {
		$step = isset( $_REQUEST['step'] ) ? intval( $_REQUEST['step'] ) : 0;
		if ( 0 === $step ) {
			$this->do_import_form();
		}
		if ( 1 === $step ) {
			$files = UploadedFile::get_uploaded_files();
			$file  = $files['import'] ?? false;
			if ( $file instanceof UploadedFile ) {
				$content = file_get_contents( $file->get_file() );
				if ( is_string( $content ) ) {
					$content = json_decode( $content, true );
				}

				if ( is_array( $content ) ) {
					$rest_client = new class() extends RestClient {
						public function __construct() {
//							$this->add_headers( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
							$this->add_headers( 'Content-Type', 'application/json' );
							parent::__construct( rest_url( 'terapixel-news-generator/v1' ) );
						}
					};
					$response    = $rest_client->post( 'settings', wp_json_encode( $content ) );
					if ( is_array( $response ) && isset( $response['success'] ) ) {
						echo 'Settings has been updated.';
					}
				}
			}
			wp_redirect( admin_url( 'admin.php?import=falahcoin-import-settings' ) );
			exit();
		}
	}

	public function do_import_form() {
		?>
        <div class="wrap">
            <h2>Import TeraPixel Settings</h2>
            <div class="narrow">
                <p>Choose a JSON (.json) file to upload, then click Upload file and import.</p>
                <form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form"
                      action="admin.php?import=falahcoin-import-settings&amp;step=1">
                    <p>
                        <label for="upload">Choose a file from your computer:</label>
                        <input type="file" id="upload" name="import" size="25" accept=".json">
                    </p>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary"
                               value="Upload file and import" disabled="">
                    </p>
                </form>
            </div>
        </div>
		<?php
	}

	public function custom_export() {
		?>
        <p>
            <label>
                <input type="radio" name="content" value="falahcoin-settings">
                News settings
            </label>
        </p>
		<?php
	}

	public function export_wp( array $args ) {
		if ( 'falahcoin-settings' === $args['content'] ) {
			$sitename = sanitize_key( get_bloginfo( 'name' ) );
			if ( ! empty( $sitename ) ) {
				$sitename .= '.';
			}
			$date     = gmdate( 'Y-m-d' );
			$filename = $sitename . 'WordPress.' . $date . '.json';

			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );
			echo wp_json_encode( static::get_settings_data(), \JSON_PRETTY_PRINT );
			exit();
		}
	}

	/**
	 * Get settings data
	 *
	 * @return array
	 */
	public static function get_settings_data(): array {
		return array(
			'news_sync'                           => SyncSettings::get_settings( false ),
			'news_api'                            => Setting::get_news_api_keys(),
			'newsapi_auto_sync_enabled'           => Setting::is_auto_sync_enabled(),
			'news_duplicate_checking_enabled'     => Setting::is_duplicate_checking_enabled(),
			'should_remove_image_with_text'       => Setting::should_remove_image_with_text(),
			'min_news_count_for_important_tweets' => OpenAIApiSetting::get_min_news_count_for_important_tweets(),
			'openai_api'                          => OpenAIApiSetting::get_api_settings(),
			'instructions'                        => OpenAIApiSetting::get_instructions(),
			'openai_auto_sync_enabled'            => OpenAIApiSetting::is_auto_sync_enabled(),
			'openai_news_country_enabled'         => OpenAIApiSetting::is_news_country_enabled(),
			'external_link_enabled'               => OpenAIApiSetting::is_external_link_enabled(),
			'important_news_for_tweets_enabled'   => OpenAIApiSetting::is_important_news_for_tweets_enabled(),
			'openai_news_sync_method'             => OpenAIApiSetting::news_sync_method(),
			'instagram_new_news_interval'         => OpenAIApiSetting::get_instagram_new_news_interval(),
			'use_linkedin_data_for_instagram'     => OpenAIApiSetting::use_linkedin_data_for_instagram(),
			'openai_unsync_items_count'           => ArticleStore::get_unsync_items_count(),
			'primary_categories'                  => Category::get_categories(),
			'default_news_category'               => Category::get_default_category(),
			'similarity_in_percent'               => Setting::get_similarity_in_percent(),
			'num_of_days_for_similarity'          => Setting::get_num_of_hours_for_similarity(),
			'news_sync_interval'                  => Setting::get_news_sync_interval(),
			'news_not_before_in_minutes'          => Setting::get_news_not_before_in_minutes(),
			'sync_image_copy_setting_from_source' => Setting::sync_image_copy_setting_from_source(),
			'google_vision_secret_key'            => GoogleVisionClient::get_google_vision_secret_key(),
		);
	}
}
