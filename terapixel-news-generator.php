<?php
/**
 * Plugin Name: TeraPixel News Generator
 * Description: A WordPress plugin for <strong>TeraPixel</strong>. Get news from NewsApi(newsapi.ai) and rewrite with OpenAi(openai.com) and distribute via webhook.
 * Version: 23.12.14
 * Author: Stackonet Services (Pvt.) Ltd.
 * Author URI: https://stackonet.com
 * Requires at least: 5.3
 * Requires PHP: 7.2
 * Text Domain: terapixel-news-generator
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Begins execution of the plugin.
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function terapixel_news_generator() {
	require_once dirname( __FILE__ ) . '/includes/Plugin.php';

	return \TeraPixelNewsGenerator\Plugin::init( __FILE__ );
}

terapixel_news_generator();
