<?php

namespace StackonetNewsGenerator\REST;

use Stackonet\WP\Framework\Traits\ApiPermissionChecker;
use Stackonet\WP\Framework\Traits\ApiResponse;
use Stackonet\WP\Framework\Traits\ApiUtils;
use WP_REST_Controller;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class ApiController
 *
 * @package Stackonet\REST
 */
class ApiController extends WP_REST_Controller {
	use ApiResponse, ApiUtils, ApiPermissionChecker;

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'terapixel-news-generator/v1';
}
