<?php

namespace JeffreyBostoenExtensions\Reporting\UI;

// iTop internals
use Dict;

/**
 * Class AttachPDF. Enables the user to attach a PDF version of a template.
 */
abstract class BaseAttachPDF extends Base {
	
	/**
	 * @var int $iRank Rank. Lower number = goes first.
	 * Note: this should definitely run before the regular TwigToPDF.
	 */
	public static $iRank = 49;
	
	
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
		
		return Dict::S('UI:Report:AttachPDF');
		
	}
	
	/**
	 * @inheritDoc
	*/
	public static function GetURLParameters() : array {		
	
		return array_merge(static::GetSpecificURLParameters(), [
			'action' => 'attach_pdf',
		]);
		
	}
	
	/**
	 * Override this method to add specific parameters.
	 * 
	 * @return array
	*/
	public static function GetSpecificURLParameters() : array {		
	
		return [];
		
	}
	
	/**
	 * @inheritDoc
	*/
	public static function IsApplicable() : bool {
		
		return false;
		
	}
	
}

