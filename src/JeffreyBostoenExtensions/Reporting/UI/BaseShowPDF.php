<?php

namespace JeffreyBostoenExtensions\Reporting\UI;

// iTop internals
use Dict;

/**
 * Class BaseShowPDF. Enables the user to view a PDF version of a template.
 */
abstract class BaseShowPDF extends Base {
	
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
		
		return Dict::S('UI:Report:ShowPDF');
		
	}
	
	/**
	 * @inheritDoc
	*/
	public static function GetURLParameters() : array {		
	
		return array_merge(parent::GetURLParameters(), [
			'action' => 'show_pdf',
		]);
		
	}
	
}

