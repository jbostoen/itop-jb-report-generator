<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

/**
 * Class Report. A class that contains content as well as a MIME type.
 */
class Report {

	/**
	 * @var string $sContent The content of the report.
	 */
	public string $sContent = '';

	/**
	 * @var string $sMimeType The MIME type of the report.
	 */
	public string $sMimeType = '';
	
	/**
	 * Constructor.
	 *
	 * @param string $sContent
	 * @param string $sMimeType
	 */
	public function __construct(string $sContent, string $sMimeType) {
		
		$this->sContent = $sContent;
		$this->sMimeType = $sMimeType;

	}

}

