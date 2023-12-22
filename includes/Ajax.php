<?php

namespace StackonetNewsGenerator;

use Stackonet\WP\Framework\Supports\Filesystem;
use StackonetNewsGenerator\BackgroundProcess\DeleteDuplicateImages;
use StackonetNewsGenerator\BackgroundProcess\OpenAiReCreateNews;
use StackonetNewsGenerator\EventRegistryNewsApi\Article;
use StackonetNewsGenerator\EventRegistryNewsApi\ArticleStore;
use StackonetNewsGenerator\EventRegistryNewsApi\SyncSettings;
use StackonetNewsGenerator\Modules\Keyword\OpenAiClient;
use StackonetNewsGenerator\OpenAIApi\Client as OpenAIApiClient;
use StackonetNewsGenerator\OpenAIApi\Models\ApiResponseLog;
use StackonetNewsGenerator\OpenAIApi\Models\BlackListWords;
use StackonetNewsGenerator\OpenAIApi\Models\InterestingNews;
use StackonetNewsGenerator\OpenAIApi\News;
use StackonetNewsGenerator\OpenAIApi\Stores\NewsStore;
use StackonetNewsGenerator\Providers\GoogleVisionClient;
use StackonetNewsGenerator\Supports\Utils;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

class Ajax {

	/**
	 * The instance of the class
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return self
	 */
	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();

			add_action( 'wp_ajax_news_generator_test', array( self::$instance, 'do_ajax_testing' ) );
			add_action( 'wp_ajax_test_google_vision', array( self::$instance, 'test_google_vision' ) );
			add_action( 'wp_ajax_debug_interesting_news', array( self::$instance, 'debug_interesting_news' ) );
			add_action( 'wp_ajax_debug_blacklist_item', array( self::$instance, 'debug_blacklist_item' ) );
			add_action( 'wp_ajax_delete_old_articles', array( self::$instance, 'delete_old_articles' ) );
			add_action( 'wp_ajax_delete_old_logs', array( self::$instance, 'delete_old_logs' ) );
			add_action( 'wp_ajax_delete_old_news_filter', array( self::$instance, 'delete_old_news_filter' ) );
			add_action( 'wp_ajax_delete_duplicate_image', array( self::$instance, 'delete_duplicate_image' ) );
			add_action(
				'wp_ajax_important_news_for_instagram',
				array( self::$instance, 'important_news_for_instagram' )
			);
			add_action( 'wp_ajax_import_test_settings', array( self::$instance, 'import_test_settings' ) );
			add_action( 'wp_ajax_debug_openai_logs', array( self::$instance, 'debug_openai_logs' ) );
		}

		return self::$instance;
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function do_ajax_testing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$instruction = "1. Lufthansa Group to Buy up to 100 737 MAX Jets in First Boeing Single-Aisle Order in Nearly 30 Years
2. Cold start to Tuesday, warming up as the week goes on
3. One of the Best Illustrated Children's Books for 2024 Is A Wink At Coronavirus by Kiran Katib
4. Governor Kemp announces five appointments to state Superior Courts
5. Pebblebrook Hotel Trust Provides Operating Update
6. Georgia gas prices decline before Christmas
7. Lufthansa Group to Buy up to 100 737 MAX Jets in First Boeing Single-Aisle Order in Nearly 30 Years - Boeing (NYSE:BA)
8. This e-commerce brand is growing as fast as the babies and toddlers it's clothing
9. Logan Airport works to get back on track after storm caused ground stops
10. Operation Exodus: Thousands of soldiers returning home for the holidays
11. Georgia announces employee retention pay supplement
12. Harpoon Therapeutics Abstract for HPN328 Accepted for Rapid Oral Presentation at the 2024 American Society of Clinical Oncology Genitourinary Cancers Symposium
13. Trinity Capital Inc. Provides $40 Million Term Loan to Taysha Gene Therapies
14. VPR Brands Acquires CARTDUB: Setting New Standards in Cannabis and Hemp Oil Recovery - VPR Brands (OTC:VPRB)
16. Waters Corporation Named to Dow Jones Sustainability Index for the Third Consecutive Year
18. Mosquito Sex Just Got Interesting: EPA goes national with new biocontrol method
19. Lufthansa Group Orders 40 Airbus, 40 Boeing Aircraft
20. Deli salads sold locally, made in Ohio, recalled: FDA
21. US moves to protect old growth forests as climate change threatens their survival - Jamaica Observer
22. Mayor-elect Angie Nelson Deuitch has appointed an all-new Michigan City Board of Public Works & Safety.
23. California: CPPA clarifies registration procedures and requirements
24. Whitmer approves state-funded $42M renovation of Eastern Michigan building
25. Accenture Confirms Business Outlook For FY24
26. Mama's Creations, Inc. Announces Pricing of the Public Secondary Offering of Common Stock by Selling Stockholders
27. Repligen Corporation to Present at 42nd Annual J.P. Morgan Healthcare Conference
28. Clinical Trial Supplies Market is Expected to Reach $6.3 Billion | MarketsandMarkets.
29. Harpoon Therapeutics Abstract for HPN328 Accepted for Rapid Oral Presentation at the 2024 American Society of Clinical Oncology Genitourinary Cancers Symposium - Harpoon Therapeutics (NASDAQ:HARP)
30. Cardlytics Announces Multi-Year Contract Renewal with Lloyds Bank Plc - Cardlytics (NASDAQ:CDLX)
31. Repligen Corporation to Present at 42nd Annual J.P. Morgan Healthcare Conference - Repligen (NASDAQ:RGEN)
32. Cardlytics Announces Multi-Year Contract Renewal with Lloyds Bank Plc
33. ADM Broadens Global Flavors Capabilities with Agreement to Acquire UK-Based FDL
34. AYR Cannabis Dispensary Announces Opening of Two New Dispensaries in Florida
35. Singapore Airlines Nonstop Flights from Singapore to London
36. ENGIE Announces Commissioning of its 100MW+ Sun Valley Utility Scale Battery Storage Project in U.S.
37. Elevat Announces Groundbreaking Technical Partnership with Microsoft to Revolutionize IoT with the Heavy Machinery industries
38. Major cleanup underway after storm batters Northeastern US, knocks out power and floods roads
39. Nebraska lawmakers focused on workforce development as top issue for the 2024 session
40. 'Armed and dangerous' man wanted in connection to deadly Griffin shooting: Police
41. Spectral AI Names Industry Veteran and Former Top MiMedx Financial Executive Peter M. Carlson as CFO
42. Humble Imports, Inc., the Wholly Owned Subsidiary of ECD Automotive Design, Reports 68% Increase in Revenue, Positive Net Income, for Third Quarter of 2023
43. Multi-vehicle accident closes I-79 S near Lost Creek
44. Univar Solutions Named on TIME's Best Companies for Future Leaders 2024 List
45. Cerence Pioneers Automotive-Specific LLM in Collaboration with NVIDIA, Powering the Future of In-Car Experiences
46. Bluefield Union Mission preparing for Christmas dinner and food bag distribution
47. Balfour Beatty Announces Executive Appointments for US Operations
48. Grant Street Bridge now open
49. B+C Station Awards 2023: CBS Stations Builds Local TV for Today  --  and Tomorrow
50. Bluefield ordinance would require registration, insurance for ATV, UTV vehicles
51. Major cleanup underway after storm batters Northeastern US, knocks...
52. US announces 10-nation force against Houthis in Red Sea
53. Philadelphia 76ers new arena plan update faces scrutiny at 1st official city review
54. FuelCell Energy: Fiscal Q4 Earnings Snapshot
55. Fewer Breaking Into Market As Home Prices Soar Higher
 Based on the above news titles, could you select one article that would be interesting to the Google search audience and is specifically a news article? No promotion or PR News. Please reply with only the titles enclosed in square brackets. We don't need any descriptions. Thank you!";
		$response    = '[37. Elevat Announces Groundbreaking Technical Partnership with Microsoft to Revolutionize IoT with the Heavy Machinery industries]';

		$news = InterestingNews::parse_openai_response_for_titles( $response );
		var_dump(
			array(
				'news' => $news,
			)
		);

		die();
	}

	/**
	 * Import test settings
	 *
	 * @return void
	 */
	public function import_test_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		$file = Plugin::init()->get_plugin_path() . '/tests/sample-data/sync-settings.json';
		if ( file_exists( $file ) ) {
			$content = Filesystem::get_filesystem()->get_contents( $file );
			if ( $content ) {
				$content = json_decode( $content, true );
				SyncSettings::update_option( $content );
			}
		}

		wp_die();
	}

	/**
	 * Import test settings
	 *
	 * @return void
	 */
	public function debug_openai_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'Sorry. This link only for developer to do some testing.',
					'stackonet-news-generator'
				)
			);
		}

		if ( wp_verify_nonce( $_REQUEST['_token'] ?? '', 'debug_openai_logs' ) ) {
			$log_id = isset( $_REQUEST['log_id'] ) ? intval( $_REQUEST['log_id'] ) : 0;
			$log    = ApiResponseLog::find_single( $log_id );
			if ( ! $log instanceof ApiResponseLog ) {
				wp_die( 'No log found for that id.' );
			}
			$api_response      = $log->get_api_response();
			$assistant_message = OpenAIApiClient::filter_api_response( $api_response );
			if ( 'interesting_news' === $log->get_belongs_to_group() ) {
				$selected_titles = InterestingNews::parse_openai_response_for_titles( $assistant_message );

				$query     = ( new ArticleStore() )->get_query_builder();
				$condition = array();
				foreach ( $selected_titles as $title ) {
					$condition[] = array( 'title', '%' . $title . '%', 'LIKE' );
				}
				$query->where( $condition, 'OR' );

				$items    = $query->get();
				$articles = array();
				foreach ( $items as $item ) {
					$article = new Article( $item );
					if ( ! $article->get_openai_news_id() ) {
						OpenAiReCreateNews::init()->push_to_queue(
							array(
								'news_id'     => $article->get_id(),
								'created_via' => 'interesting-news',
								'batch_id'    => $log->get_id(),
								'created_at'  => current_time( 'mysql', true ),
							)
						);
					}
					$articles[] = $article;
				}
				var_dump( $articles );

				wp_die();
			}
			if ( 'keyword' === $log->get_belongs_to_group() ) {
				$response = OpenAiClient::sanitize_response( $assistant_message );
				var_dump(
					array(
						'formatted' => $response,
						'raw'       => $assistant_message,
					)
				);
				die;
			}
			var_dump( array( $_REQUEST, $log ) );
		}

		wp_die();
	}

	/**
	 * Important news for instagram
	 *
	 * @return void
	 */
	public function important_news_for_instagram() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}
		$news_array = NewsStore::get_news_for_instagram( true );
		if ( count( $news_array ) < 1 ) {
			wp_die( __( 'Sorry. No new news in last one hour.', 'stackonet-news-generator' ) );
		}
		$news_ids = OpenAIApiClient::find_important_news_for_instagram( $news_array, true );
		if ( is_array( $news_ids ) ) {
			foreach ( $news_ids as $news_id ) {
				$news = NewsStore::find_by_id( $news_id );
				$news->send_to_sites( true );
			}
		}

		var_dump(
			array(
				'suggested'  => $news_ids,
				'news_items' => $news_array,
			)
		);
		die;
	}

	public function test_google_vision() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}
		$image_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		if ( empty( $image_id ) ) {
			$images   = Utils::get_images( 'full', 1 );
			$image_id = $images[0]['id'];
		}

		$src = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! is_array( $src ) ) {
			wp_die( 'Could not find any image for that id.' );
		}

		try {
			$im       = new \Imagick( $src[0] );
			$response = ( new GoogleVisionClient() )->detect_text( base64_encode( $im->getImageBlob() ) );
		} catch ( \ImagickException $e ) {
			wp_die( $e->getMessage() );
		}
		var_dump(
			array(
				'image_id'               => $image_id,
				'image_url'              => $src[0],
				'google_vision_response' => $response,
			)
		);
		die;
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function debug_interesting_news() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$news_id = isset( $_GET['news_id'] ) ? intval( $_GET['news_id'] ) : 0;
		$task    = isset( $_GET['task'] ) ? sanitize_text_field( $_GET['task'] ) : '';

		/** @var InterestingNews $log */
		$log = InterestingNews::find_single( $news_id );
		var_dump( $log );
		die();
	}

	/**
	 * A AJAX method just to test some data
	 */
	public function debug_blacklist_item() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$news_id = isset( $_GET['news_id'] ) ? intval( $_GET['news_id'] ) : 0;
		/** @var News $news */
		$news = ( new NewsStore() )->find_single( $news_id );

		$info  = BlackListWords::get_blacklist_phrase_info_by_similarity( $news->get_content() );
		$info2 = BlackListWords::get_blacklist_phrase_info_by_strpos( $news->get_content() );
		var_dump(
			array(
				'news'          => $news,
				'by_similarity' => $info,
				'by_strpos'     => $info2,
			)
		);
		die();
	}

	public function delete_old_articles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 7;
		try {
			ArticleStore::delete_old_articles( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}

	public function delete_old_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 3;
		try {
			ApiResponseLog::delete_old_logs( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}

	public function delete_duplicate_image() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$force = isset( $_GET['force'] );
		$limit = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 100;
		$limit = max( 10, $limit );

		$ids = DeleteDuplicateImages::run( $force, $limit );
		var_dump(
			array(
				'command'  => 'Delete duplicate images',
				'mode'     => $force ? 'instant' : 'background',
				'start_id' => count( $ids ) ? min( $ids ) : false,
				'end_id'   => count( $ids ) ? max( $ids ) : false,
				'ids'      => $ids,
			)
		);
		wp_die();
	}

	public function delete_old_news_filter() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry. This link only for developer to do some testing.', 'stackonet-news-generator' ) );
		}

		$day = isset( $_GET['day'] ) ? intval( $_GET['day'] ) : 3;
		try {
			InterestingNews::delete_old_logs( $day );
		} catch ( \Exception $e ) {
		}
		wp_die();
	}
}
