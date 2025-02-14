<?php

namespace JeffreyBostoenExtensions\Reporting\UI;

// iTop internals
use Dict;

/**
 * Class DownloadPDF.Enables the user to download (prompted) a PDF version of a template.
 */
abstract class BaseDownloadPDF extends Base {
	
	
	/**
	 * @inheritDoc
	 *
	 */
	public static function GetTarget() : string {
		return '_self';
	}
	
	/**
	 * @inheritDoc
	*/
	public static function GetTitle() : string {
		
		return Dict::S('UI:Report:DownloadPDF');
		
	}
	
	/**
	 * @inheritDoc
	*/
	public static function GetURLParameters() : array {		
	
		return array_merge(static::GetSpecificURLParameters(), [
			'action' => 'download_pdf',
		]);
		
	}
	
}
