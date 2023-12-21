<?php

namespace StackonetNewsGenerator\Modules\Site\Models;

use Stackonet\WP\Framework\Abstracts\Data;

/**
 * NewsToSiteLog model
 */
class NewsToSiteLog extends Data {
	/**
	 * News to site log
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = parent::to_array();

		$data['news_id']    = intval( $data['news_id'] );
		$data['site_id']    = intval( $data['site_id'] );
		$data['created_at'] = mysql_to_rfc3339( $data['created_at'] );
		$data['updated_at'] = mysql_to_rfc3339( $data['updated_at'] );

		return $data;
	}
}
