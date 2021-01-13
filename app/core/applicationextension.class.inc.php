<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-04-09 16:58:14
 *
 * Definitions of interfaces for the report generator
 */

namespace jb_itop_extensions\report_generator;

// Internals
use \ReflectionClass;

// iTop internals
use \DBOBject;
use \DBObjectSet;
use \iPopupMenuExtension;
use \MetaModel;
use \URLPopupMenuItem;
use \utils;

/**
 * Interface iReportTool.
 * Provides methods to enrich data or perform other actions.
 */
interface iReportTool {
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable();
	
	/**
	 * Rendering hook
	 *
	 * @param \Array $aReportData Twig data
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function EnrichData(&$aReportData, $oSet_Objects);
	
	/**
	 * Action hook
	 *
	 * @param \Array $aReportData Report data
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function DoExec($aReportData, $oSet_Objects);

}

interface iReport {
	
	/**
	 * If a button should be shown instead of a menu item
	 *
	 * @return \Boolean
	 *
	 */
	public static function ForceButton();
	
	/**
	 * Returns the precedence (order. Low = first, high = later)
	 *
	 * @return \Float
	 *
	 */
	public static function GetPrecedence();
	
	/**
	 * Gets the HTML target. Uusally '_blank' or '_self'
	 *
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTarget();
	
	/**
	 * Title of the menu item or button
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 * 
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTitle(DBObjectSet $oSet_Objects, $sView);
	
	/**
	 * URL Parameters
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 * 
	 * @return \Array
	 */
	public static function GetURLParameters(DBObjectSet $oSet_Objects, $sView);
	
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView);
	
}

/**
 * Class DefaultReport just represents a basic report to extend.
 */
abstract class DefaultReport implements iReport {
	
	/**
	 * @var \String $sModuleName Name of the module where this is defined
	 */
	public const sModuleDir = 'jb-report-generator';
	
	/**
	 * If a button should be shown instead of a menu item
	 *
	 * @return \Boolean
	 *
	 */
	public static function ForceButton() {
		return true;
	}
	
	/**
	 * Returns the precedence (order. Low = first, high = later)
	 *
	 * @return \Float
	 *
	 */
	public static function GetPrecedence() {
		return 100;
	}
	
	/**
	 * Gets the HTML target. Uusally '_blank' or '_self'
	 *
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTarget() {
		return '_blank';
	}
	
	/**
	 * Title of the menu item or button
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 * 
	 * @return \String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTitle(DBObjectSet $oSet_Objects, $sView) {
		return '';
	}
	
	/**
	 * URL Parameters. Often 'template' or additional parameters for extended iReportTool implementations.
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 * 
	 * @return \Array
	 */
	public static function GetURLParameters(DBObjectSet $oSet_Objects, $sView) {
		return [];
	}
	
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View: 'details' or 'list'
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView) {
		return false;
	}
	
}


/**
 * Class PopupMenuExtension_ReportGenerator.
 * Adds items to popup menu of to 'Details' view, to generate reports.
 */
class PopupMenuExtensionReportGenerator implements iPopupMenuExtension {
	
	
	/**
	 * @var \URLPopupMenuItem[] Array of \URLPopupMenuItem
	 */
	private static $menu_items = [];
	
	/**
	 * @var \String Comma separated list of shortcut actions
	 */
	private static $shortcut_actions = '';
	
	/**
	 * @var \Integer $iMenuId Menu ID
	 * @var \Object $param Parameter provided by iTop.
	 */
	public static function EnumItems($iMenuId, $param) {
	
		if($iMenuId == self::MENU_OBJDETAILS_ACTIONS) {
		  
			/** @var \DBObject $oObject iTop object of which details are being displayed */
			$oObject = $param;
			
			// Process templates
			self::GetReports(DBObjectSet::FromObject($oObject), 'details');
			
			return self::$menu_items;
		
		}
		elseif($iMenuId == self::MENU_OBJLIST_ACTIONS) {
			
			/** @var \DBObjectSet $oObjectSet Set of iTop objects which are being displayed in a list */
			$oObjectSet = $param;
			
			// There should be items in the set.
			if($oObjectSet->Count() > 0) {
				
				// Process templates
				self::GetReports($oObjectSet, 'list');
				
				return self::$menu_items;
				  
			} 
		} 
		
		// Always expects an array as result.
		return [];
		  
	}
	 
	/**
	 * Gets data from the templates, such as title and whether or not to use a separate button.
	 *
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView The view ('details' or 'list')
	 *
	 * @return void
	 * 
	 * @uses \PopupMenuExtension_ReportGenerator::menu_items
	 * @uses \PopupMenuExtension_ReportGenerator::shortcut_actions
	 */
	public static function GetReports(DBObjectSet $oSet_Objects, $sView) {
		
		// Menu items
		self::$menu_items = [];
		self::$shortcut_actions = MetaModel::GetConfig()->Get('shortcut_actions');
		
		// Process all policies
		$aReports = [];
		foreach(get_declared_classes() as $sClassName) {
		
			if(in_array('jb_itop_extensions\report_generator\iReport', class_implements($sClassName))) {
				$aReports[] = $sClassName;
			}
			
		}
		
		// Reports must be executed in a certain order (for instance: to change button order).
		// The order in which these actions are executed, is important.
		usort($aReports, function($sClassNameA, $sClassNameB) {
			return $sClassNameA::GetPrecedence() <=> $sClassNameB::GetPrecedence();
		});
		
		foreach($aReports as $sReport) {
			
			if($sReport::IsApplicable($oSet_Objects, $sView) == true) {
		
				// UID must simply be unique.Keep alphanumerical version of filename.
				$sUID = utils::GetCurrentModuleName().'_'.preg_replace('/[^\dA-Za-z_-]/i', '', $sReport).'_'.rand(0, 10000);
				
				// Add shortcut (button) or keep menu item?
				self::$shortcut_actions .= ($sReport::ForceButton() == true ? ','.$sUID : '');
				
				// Parameters
				$aParameters = [];
				if(isset($aReportSettings['parameters']) == true) {
					$aParameters = $aReportSettings['parameters'];
				}
				
				$oReflector = new ReflectionClass($sReport);
				
				// URL should pass location of the report (folder/report) and the OQL query for the object(s)
				$sURL = utils::GetAbsoluteUrlExecPage().'?'.
					'&exec_module='.utils::GetCurrentModuleName().
					'&exec_page=reporting.php'.
					'&exec_env='.utils::GetCurrentEnvironment().
					'&type='.$sView.
					'&class='.$oSet_Objects->GetClass().
					'&report='.$oReflector->getShortName().
					(count($sReport::GetURLParameters($oSet_Objects, $sView)) > 0 ? '&'.http_build_query($sReport::GetURLParameters($oSet_Objects, $sView)) : '').
					'&filter='.htmlentities($oSet_Objects->GetFilter()->Serialize(), ENT_QUOTES, 'UTF-8')
				;
					
				self::$menu_items[] = new URLPopupMenuItem($sUID, $sReport::GetTitle($oSet_Objects, $sView), $sURL, $sReport::GetTarget());
				
			}
			
		}
		
		// Update shortcut_actions
		MetaModel::GetConfig()->Set('shortcut_actions', ltrim(self::$shortcut_actions, ','));
		 
	}
	 
}
