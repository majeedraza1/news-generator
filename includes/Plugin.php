<?php

namespace StackonetNewsGenerator;

use StackonetNewsGenerator\Admin\Admin;
use StackonetNewsGenerator\BackgroundProcess\CopyNewsImage;
use StackonetNewsGenerator\BackgroundProcess\DeleteDuplicateImages;
use StackonetNewsGenerator\BackgroundProcess\ExtractArticleInformation;
use StackonetNewsGenerator\BackgroundProcess\OpenAiFindInterestingNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateFocusKeyphrase;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsBody;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNewsTitle;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateOldNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncInstagramFields;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncNews;
use StackonetNewsGenerator\BackgroundProcess\OpenAiSyncTwitterFields;
use StackonetNewsGenerator\BackgroundProcess\ProcessNewsTag;
use StackonetNewsGenerator\BackgroundProcess\SyncEventRegistryNews;
use StackonetNewsGenerator\CLI\Command;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\ClientResponseLog;
use StackonetNewsGenerator\EventRegistryNewsApi\NewsApiCronEvent;
use StackonetNewsGenerator\EventRegistryNewsApi\NewsSource;
use StackonetNewsGenerator\EventRegistryNewsApi\Rest\AdminNewsApiLogController;
use StackonetNewsGenerator\EventRegistryNewsApi\Rest\AdminSyncSettingController;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettingsStore;
use StackonetNewsGenerator\Modules\ExternalLink\ExternalLinkManager;
use StackonetNewsGenerator\Modules\ImportExport\ImportExportManager;
use StackonetNewsGenerator\Modules\Keyword\KeywordManager;
use StackonetNewsGenerator\Modules\NaverDotComNews\NaverDotComNewsManager;
use StackonetNewsGenerator\Modules\NewsCrawler\NewsCrawlerLog;
use StackonetNewsGenerator\Modules\Site\BackgroundSendNewsToSite;
use StackonetNewsGenerator\Modules\Site\BackgroundSendTagsToSite;
use StackonetNewsGenerator\Modules\Site\REST\AdminNewsToSiteLogController;
use StackonetNewsGenerator\Modules\Site\REST\AdminSiteController;
use StackonetNewsGenerator\Modules\Site\REST\SiteController;
use StackonetNewsGenerator\Modules\Site\SiteStore;
use StackonetNewsGenerator\Modules\Site\Stores\NewsToSiteLogStore;
use StackonetNewsGenerator\Modules\TweetToArticle\TweetToArticleManager;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Models\InstagramAttemptLog;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\OpenAIApi\Rest\ApiResponseLogController;
use StackonetNewsGenerator\OpenAIApi\Rest\OpenAiBlacklistController;
use StackonetNewsGenerator\OpenAIApi\Rest\OpenAiController;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsTagStore;
use StackonetNewsGenerator\REST\AdminInstagramAttemptLogController;
use StackonetNewsGenerator\REST\AdminNewsController;
use StackonetNewsGenerator\REST\AdminNewsFilteringController;
use StackonetNewsGenerator\REST\AdminNewsSourceController;
use StackonetNewsGenerator\REST\AdminSettingController;

defined( 'ABSPATH' ) || exit;

/**
 * The main plugin handler class is responsible for initializing plugin. The
 * class registers and all the components required to run the plugin.
 */
class Plugin {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Holds various class instances
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * The plugin main file absolute path.
	 *
	 * @var string
	 */
	private static $plugin_file = '';

	/**
	 * Get plugin info
	 *
	 * @var array
	 */
	private $plugin_data = [
		'Version'     => '',
		'TextDomain'  => '',
		'Name'        => '',
		'RequiresPHP' => '7.0',
	];

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @param  null|string  $plugin_file  The plugin main file absolute path.
	 *
	 * @return self
	 */
	public static function init( ?string $plugin_file = null ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			if ( ! empty( $plugin_file ) ) {
				self::$plugin_file = $plugin_file;
			}

			// Register autoloader.
			self::$instance->register_autoloader();

			// Read plugin data. e.g. version, name, textdomain etc.
			self::$instance->read_plugin_data();

			// Check if PHP version is supported for our plugin.
			if ( ! self::$instance->is_supported_php() ) {
				register_activation_hook( $plugin_file, [ self::$instance, 'auto_deactivate' ] );
				add_action( 'admin_notices', [ self::$instance, 'php_version_notice' ] );

				return self::$instance;
			}

			add_action( 'plugins_loaded', array( self::$instance, 'includes' ) );
			add_action( 'init', array( self::$instance, 'load_plugin_textdomain' ) );
			register_activation_hook( $plugin_file, [ self::$instance, 'activation_includes' ] );
			register_deactivation_hook( $plugin_file, [ self::$instance, 'deactivation_includes' ] );

			// WP-CLI Commands.
			if ( class_exists( \WP_CLI::class ) && class_exists( \WP_CLI_Command::class ) ) {
				\WP_CLI::add_command( 'stackonet-news-generator', Command::class );
			}
		}

		return self::$instance;
	}

	/**
	 * Load plugin classes
	 */
	private function register_autoloader() {
		if ( file_exists( $this->get_plugin_path() . '/vendor/autoload.php' ) ) {
			include $this->get_plugin_path() . '/vendor/autoload.php';
		} else {
			include_once $this->get_plugin_path() . '/includes/Autoloader.php';

			// instantiate the loader.
			$loader = new Autoloader();

			// register the base directories for the namespace prefix.
			$loader->add_namespace( 'StackonetNewsGenerator', $this->get_plugin_path() . '/includes' );
			$loader->add_namespace( 'StackonetNewsGenerator\Modules', $this->get_plugin_path() . '/modules' );

			// register the autoloader.
			$loader->register();
		}
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'stackonet-news-generator', false,
			basename( $this->get_plugin_path() ) . '/languages' );
	}

	/**
	 * Instantiate the required classes
	 *
	 * @return void
	 */
	public function includes() {
		$this->container['assets']         = Assets::init();
		$this->container['cron_sync_news'] = NewsApiCronEvent::init();

		// Load classes for admin area.
		if ( $this->is_request( 'admin' ) ) {
			$this->admin_includes();
		}

		// Load classes for frontend area.
		if ( $this->is_request( 'frontend' ) ) {
			$this->frontend_includes();
		}

		// Load classes for ajax functionality.
		if ( $this->is_request( 'ajax' ) ) {
			$this->ajax_includes();
		}

		$this->modules_includes();
		$this->background_tasks();
	}

	/**
	 * What type of request is this?
	 *
	 * @param  string  $type  admin, ajax, rest, cron or frontend.
	 *
	 * @return bool
	 */
	private function is_request( string $type ): bool {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'rest':
				return defined( 'REST_REQUEST' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}

		return false;
	}

	/**
	 * Include admin classes
	 *
	 * @return void
	 */
	public function admin_includes() {
		add_action( 'admin_init', array( ArticleStore::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsStore::class, 'create_table' ) );
		add_action( 'admin_init', array( SiteStore::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsTagStore::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsSource::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsSource::class, 'upgrade' ) );
		add_action( 'admin_init', array( InterestingNews::class, 'create_table' ) );
		add_action( 'admin_init', array( ApiResponseLog::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsToSiteLogStore::class, 'create_table' ) );
		add_action( 'admin_init', array( InstagramAttemptLog::class, 'create_table' ) );
		add_action( 'admin_init', array( ClientResponseLog::class, 'create_table' ) );
		add_action( 'admin_init', array( SyncSettingsStore::class, 'create_table' ) );
		add_action( 'admin_init', array( NewsCrawlerLog::class, 'create_table' ) );

		$this->container['admin']                  = Admin::init();
		$this->container['import_export_settings'] = ImportExportSettings::init();
	}

	/**
	 * Include frontend classes
	 *
	 * @return void
	 */
	public function frontend_includes() {
		$this->container['rest_settings']              = AdminSettingController::init();
		$this->container['rest_admin_news']            = AdminNewsController::init();
		$this->container['rest_openapi']               = OpenAiController::init();
		$this->container['rest_openapi_blacklist']     = OpenAiBlacklistController::init();
		$this->container['rest_openapi_site']          = AdminSiteController::init();
		$this->container['rest_remote_site']           = SiteController::init();
		$this->container['rest_news_source']           = AdminNewsSourceController::init();
		$this->container['rest_news_filtering']        = AdminNewsFilteringController::init();
		$this->container['rest_api_log']               = ApiResponseLogController::init();
		$this->container['rest_instagram_log']         = AdminInstagramAttemptLogController::init();
		$this->container['rest_news_to_site_logs']     = AdminNewsToSiteLogController::init();
		$this->container['rest_newsapi_logs']          = AdminNewsApiLogController::init();
		$this->container['rest_newsapi_sync_settings'] = AdminSyncSettingController::init();
	}

	/**
	 * Include frontend classes
	 *
	 * @return void
	 */
	public function ajax_includes() {
		$this->container['ajax'] = Ajax::init();
	}

	/**
	 * Include modules main classes
	 *
	 * @return void
	 */
	public function modules_includes() {
		$this->container['module_tweet_to_article'] = TweetToArticleManager::init();
		$this->container['module_external_link']    = ExternalLinkManager::init();
		$this->container['module_keyword']          = KeywordManager::init();
		$this->container['module_import_export']    = ImportExportManager::init();
		$this->container['module_naver']            = NaverDotComNewsManager::init();
	}

	/**
	 * Background tasks
	 *
	 * @return void
	 */
	public function background_tasks() {
		$this->container['bg_step1']  = SyncEventRegistryNews::init();
		$this->container['bg_step2']  = OpenAiFindInterestingNews::init();
		$this->container['bg_step2b'] = ExtractArticleInformation::init();
		$this->container['bg_step3']  = OpenAiReCreateNewsTitle::init();
		$this->container['bg_step3a'] = OpenAiReCreateFocusKeyphrase::init();
		$this->container['bg_step3b'] = OpenAiReCreateNewsBody::init();
		$this->container['bg_step3c'] = OpenAiSyncTwitterFields::init();
		$this->container['bg_step4']  = OpenAiSyncNews::init();
		$this->container['bg_step4b'] = ProcessNewsTag::init();
		$this->container['bg_step4c'] = CopyNewsImage::init();
		$this->container['bg_step5']  = BackgroundSendNewsToSite::init();

		$this->container['bg_instagram_fields']        = OpenAiSyncInstagramFields::init();
		$this->container['bg_delete_duplicate_images'] = DeleteDuplicateImages::init();

		// @TODO remove it
		$this->container['bg_sync_old_openai_api'] = OpenAiReCreateOldNews::init();
		$this->container['bg_send_tags_to_site']   = BackgroundSendTagsToSite::init();
	}

	/**
	 * Run on plugin activation
	 *
	 * @return void
	 */
	public function activation_includes() {
		NewsApiCronEvent::schedule_cron_event();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation
	 *
	 * @return void
	 */
	public function deactivation_includes() {
		flush_rewrite_rules();
	}

	/**
	 * Show notice about PHP version
	 *
	 * @return void
	 */
	public function php_version_notice() {
		if ( $this->is_supported_php() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$error = __( 'Your installed PHP Version is: ', 'stackonet-news-generator' ) . PHP_VERSION . '. ';
		$error .= sprintf(
		/* translators: 1: plugin name, 2: php version number */
			__( 'The %1$s plugin requires PHP version %2$s or greater.', 'stackonet-news-generator' ),
			$this->plugin_data['Name'],
			$this->plugin_data['RequiresPHP']
		);
		?>
        <div class="error">
            <p><?php printf( esc_js( $error ) ); ?></p>
        </div>
		<?php
	}

	/**
	 * Bail out if the php version is lower than
	 *
	 * @return void
	 */
	public function auto_deactivate() {
		if ( $this->is_supported_php() ) {
			return;
		}
		deactivate_plugins( plugin_basename( $this->get_plugin_file() ) );
		$error = '<h1>' . __( 'An Error Occurred', 'stackonet-news-generator' ) . '</h1>';
		$error .= '<h2>' . __( 'Your installed PHP Version is: ', 'stackonet-news-generator' ) . PHP_VERSION . '</h2>';
		$error .= '<p>' . sprintf(
			/* translators: 1: plugin name, 2: php version number */
				__( 'The %1$s requires PHP version %2$s or greater', 'stackonet-news-generator' ),
				$this->plugin_data['Name'],
				$this->plugin_data['RequiresPHP']
			) . '</p>';
		$error .= '<p>' . sprintf(
			/* translators: 1: php doc page link start, 2: php doc page link end */
				__( 'The version of your PHP is %1$s unsupported and old %2$s. ', 'stackonet-news-generator' ),
				'<a href="https://php.net/supported-versions.php" target="_blank"><strong>',
				'</strong></a>'
			);
		$error .= __(
			          'You should update your PHP software or contact your host regarding this matter.',
			          'stackonet-news-generator'
		          ) . '</p>';
		$title = __( 'Plugin Activation Error', 'stackonet-news-generator' );
		wp_die( wp_kses_post( $error ), esc_html( $title ), [ 'back_link' => true ] );
	}

	/**
	 * Read plugin data
	 *
	 * @return void
	 */
	private function read_plugin_data() {
		$this->plugin_data = get_file_data(
			$this->get_plugin_file(),
			[
				'Version'     => 'Version',
				'TextDomain'  => 'Text Domain',
				'Name'        => 'Plugin Name',
				'RequiresPHP' => 'Requires PHP',
			]
		);
	}

	/**
	 * Get plugin main file
	 *
	 * @return string
	 */
	public function get_plugin_file(): string {
		return self::$plugin_file;
	}

	/**
	 * Get the plugin url.
	 *
	 * @param  string  $path  Extra path appended to the end of the URL.
	 *
	 * @return string
	 */
	public function get_plugin_url( string $path = '' ): string {
		return plugins_url( $path, $this->get_plugin_file() );
	}

	/**
	 * Get plugin path
	 *
	 * @return string
	 */
	public function get_plugin_path(): string {
		return dirname( $this->get_plugin_file() );
	}

	/**
	 * Get plugin directory/folder name.
	 *
	 * @return string
	 */
	public function get_directory_name(): string {
		return basename( $this->get_plugin_path() );
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_plugin_version(): string {
		if ( ! empty( $this->plugin_data['Version'] ) ) {
			return $this->plugin_data['Version'];
		}

		return gmdate( 'Y.m.d.Gi', filemtime( $this->get_plugin_file() ) );
	}

	/**
	 * Check if the PHP version is supported
	 *
	 * @return bool
	 */
	public function is_supported_php(): bool {
		return version_compare( PHP_VERSION, $this->plugin_data['RequiresPHP'], '>=' );
	}
}
