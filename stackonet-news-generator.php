<?php
/**
 * Plugin Name: Stackonet News Generator
 * Description: A WordPress plugin to get news from NewsApi(newsapi.ai) and rewrite with OpenAi(openai.com) and distribute via webhook.
 * Version: 2024.01.11
 * Author: Stackonet Services (Pvt.) Ltd.
 * Author URI: https://stackonet.com
 * Requires at least: 5.3
 * Requires PHP: 7.2
 * Text Domain: stackonet-news-generator
 * Domain Path: /languages
 *
 * @package StackonetNewsGenerator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Begins execution of the plugin.
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function stackonet_news_generator() {
	require_once __DIR__ . '/includes/Plugin.php';

	return \StackonetNewsGenerator\Plugin::init( __FILE__ );
}

stackonet_news_generator();
