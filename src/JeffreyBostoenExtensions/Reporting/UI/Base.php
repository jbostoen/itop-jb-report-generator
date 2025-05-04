<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\UI;

// iTop internals.
use utils;

/**
 * Interface iBase.
 * Implement this interface to hook into the UI of iTop's console (backend) to show a button or add a menu action.
 * 
 * When implementing a new UI element, use JeffreyBostoenExtensions\Reporting\UI\Base instead.
 */
interface iBase {
	
	/**
	 * Whether a button should be shown instead of a menu item.
	 *
	 * @return bool
	 *
	 */
	public static function ForceButton() : bool;
	
	/**
	 * Returns the precedence (order. Low = first, high = later)
	 *
	 * @return Float
	 *
	 */
	public static function GetPrecedence() : float;
	
	/**
	 * Gets the HTML target. Uusally '_blank' or '_self'
	 *
	 * @return string
	 *
	 */
	public static function GetTarget() : string;
	
	/**
	 * Title of the menu item or button.
	 * 
	 * @return string
	 *
	 */
	public static function GetTitle() : string;
	
	/**
	 * URL Parameters.  
	 * This function should NOT be overruled when creating a leaf class.
	 * 
	 * @return array
	 */
	public static function GetURLParameters() : array;
	
	/**
	 * URL Parameters that are specific for the leaf class.
	 * 
	 * @return array
	 */
	public static function GetSpecificURLParameters() : array;
	
	/**
	 * Whether this extension is applicable. By default, the UI element is NOT applicable!
	 *
	 * @return bool
	 *
	 */
	public static function IsApplicable() : bool;

	/**
	 * Whether the URL parameters ($_REQUEST) match the URL parameters specified by this UI element.
	 *
	 * @return boolean
	 */
	public static function MatchesURLParameters() : bool;
	
}

/**
 * Class Base. Extend this class (and make it applicable) to add actions (buttons or menus) in iTop.
 * 
 * Hint: Class documentation could start with:
 * Enables the user to (generate a report, view a PDF, attach a PDF, ...) of ...
 */
abstract class Base implements iBase {
	
	/**
	 * @inheritDoc
	 *
	 */
	public static function ForceButton() : bool {
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetPrecedence() : float {
		return 100;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetTarget() : string {
		return '_blank';
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetTitle() : string {
		return '';
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetURLParameters() : array {
		return [];
	}

	/**
	 * @inheritDoc
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

	/**
	 * @inheritDoc
	 */
	public static function MatchesURLParameters() : bool {

		$aParameters = static::GetURLParameters();
		
		foreach($aParameters as $sKey => $sValue) {

			// If a parameter is not set or does not match the expected value: It does not match.
			if(utils::ReadParam($sKey, null, true, 'raw_data') != $sValue) {
				return false;
			}

		}

		// If the above logic didn't find something to exclude this, it matches.
		return true;

	}
	
}
