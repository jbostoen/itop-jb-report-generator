<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240425
 *
 * Definitions of interfaces for the report generator
 */

namespace jb_itop_extensions\report_generator;

use \jb_itop_extensions\report_generator\ReportGeneratorHelper;

// iTop internals
use \DBOBject;
use \DBObjectSet;
use \iPopupMenuExtension;
use \MetaModel;
use \URLPopupMenuItem;
use \utils;

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
	
		if($iMenuId == static::MENU_OBJDETAILS_ACTIONS) {
		  
			/** @var \DBObject $param iTop object of which details are being displayed */
			ReportGeneratorHelper::SetObjectSet(DBObjectSet::FromObject($param));

			// Process templates.
			ReportGeneratorHelper::SetView('details');
			static::GetReports();
			
		
		}
		elseif($iMenuId == static::MENU_OBJLIST_ACTIONS) {
			
			/** @var \DBObjectSet $param Set of iTop objects which are being displayed in a list */
			ReportGeneratorHelper::SetObjectSet($param);

			// Process templates.
			ReportGeneratorHelper::SetView('list');
			static::GetReports();
			

		} 
		
		// Always expects an array as result.
		return static::$menu_items;
		  
	}
	 
	/**
	 * Gets data from the templates, such as title and whether or not to use a separate button.
	 *
	 * @return void
	 * 
	 * @uses \PopupMenuExtension_ReportGenerator::menu_items
	 * @uses \PopupMenuExtension_ReportGenerator::shortcut_actions
	 */
	public static function GetReports() {

		$sView = ReportGeneratorHelper::GetView();
		
		// Menu items
		static::$menu_items = [];
		static::$shortcut_actions = MetaModel::GetConfig()->Get('shortcut_actions');
		
		// Process all policies
		$aReports = [];
		foreach(get_declared_classes() as $sClassName) {
		
			if(in_array('jb_itop_extensions\report_generator\iReportUIElement', class_implements($sClassName))) {
				$aReports[] = $sClassName;
			}
			
		}
		
		// Reports must be executed in a certain order (for instance: to change button order).
		// The order in which these actions are executed, is important.
		usort($aReports, function($sClassNameA, $sClassNameB) {
			return $sClassNameA::GetPrecedence() <=> $sClassNameB::GetPrecedence();
		});

		/** @var \DBObjectSet|null $oSet_Objects Object set. */
		$oSet_Objects = ReportGeneratorHelper::GetObjectSet();

		foreach($aReports as $sReport) {
			
			if($sReport::IsApplicable() == true) {
		
				// UID must simply be unique.Keep alphanumerical version of filename.
				$sUID = ReportGeneratorHelper::MODULE_CODE.'_'.preg_replace('/[^\dA-Za-z_-]/i', '', $sReport).'_'.rand(0, 10000);
				
				// Add shortcut (button) or keep menu item?
				static::$shortcut_actions .= ($sReport::ForceButton() == true ? ','.$sUID : '');
				
				// URL should pass location of the report (folder/report) and the OQL query for the object(s).
				$sURL = utils::GetAbsoluteUrlExecPage().'?'.
					'&exec_module='.ReportGeneratorHelper::MODULE_CODE.
					'&exec_page=reporting.php'.
					'&exec_env='.utils::GetCurrentEnvironment().
					'&view='.$sView.
					(count($sReport::GetURLParameters()) > 0 ? '&'.http_build_query($sReport::GetURLParameters()) : '')
				;

				// Only if a filter was set:
				if($oSet_Objects !== null) {

					$sURL .= '&filter='.urlencode(htmlentities($oSet_Objects->GetFilter()->Serialize(), ENT_QUOTES, 'UTF-8'));

				}
					
				static::$menu_items[] = new URLPopupMenuItem($sUID, $sReport::GetTitle(), $sURL, $sReport::GetTarget());
				
			}
			
		}
		
		// Update shortcut_actions
		MetaModel::GetConfig()->Set('shortcut_actions', ltrim(static::$shortcut_actions, ','));
		 
	}
	 
}
