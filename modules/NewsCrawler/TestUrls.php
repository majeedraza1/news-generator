<?php

namespace StackonetNewsGenerator\Modules\NewsCrawler;

class TestUrls {
	protected static $urls = [
		'https://www.thedailystar.net/news/bangladesh/news/eight-our-institutions-usurped-3544876', // passed.
		'http://www.beyondpost.co.kr/view.php?ud=20240215082611165746a9e4dd7f_30',
		'https://biz.chosun.com/policy/politics/2024/02/15/FMQNKAEW6BFGDKLSHBXCS6CDHY/?utm_source=naver&utm_medium=original&utm_campaign=biz',// Failed, load news via javaScript
		'https://biz.sbs.co.kr/article/20000157337?division=NAVER', // Failed, load news via javaScript
		'http://www.fashionbiz.co.kr/TN/?cate=2&recom=2&idx=205743',
		'https://www.thebell.co.kr/free/content/ArticleView.asp?key=202401260749236200107516',
		'http://shapla.test/markup-html-tags-and-formatting/',
	];

	public static function get_news_url( int $index ): string {
		$max   = count( static::$urls ) - 1;
		$index = max( 0, min( $index, $max ) );

		return static::$urls[ $index ];
	}
}