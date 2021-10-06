<?php
/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-04-09 16:58:14
 *
 * Shows report.
 *
 * @todo Translate some errors which should never be seen in the first place
 */
 

/**
 * $_REQUEST should contain: 
 * class:               String. Class name
 * filter:              String. OQL Query
 * view: 				String. 'details' or 'list'
 *
 * Optional:
 * template: 			String. Report name. For convenience, use detail/<filename>.twig and list/<filename>.twig . Default report (HTML - Twig)
 * action:				String. Name of custom action ('show_pdf')
 * 
 * other custom defined parameters may be specified.
*/

namespace jb_itop_extensions\report_generator;

use \ApplicationException;
use \DBObject;
use \CMDBObjectSet;
use \DBObjectSearch;
use \Dict;
use \LoginWebPage;
// use \MetaModel;
use \NiceWebPage;
use \SecurityException;
use \UserRights;
use \utils;


		
	if (!defined('APPROOT')) require_once(__DIR__.'/../../approot.inc.php');
	require_once(APPROOT.'/application/application.inc.php');
	require_once(APPROOT.'/application/displayblock.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(APPROOT.'/application/wizardhelper.class.inc.php');
	require_once(APPROOT.'/core/restservices.class.inc.php');
	
	// Autoloader (Twig, chillerlan\QRCode, ...
	require_once('vendor/autoload.php');
	
	// Get iTop's Dict::S('string') so it can be exposed to Twig as well 
	// require_once( APPROOT . '/application/utils.inc.php' );
	// require_once( APPROOT . '/core/coreexception.class.inc.php' );
	// require_once( APPROOT . '/core/dict.class.inc.php' );
	
	try {
			
		// Logging in exposed :current_contact_id in OQL
		if(LoginWebPage::EXIT_CODE_OK != LoginWebPage::DoLoginEx(null /* any portal */, false, LoginWebPage::EXIT_RETURN)) {
			throw new SecurityException('You must be logged in');
		}
		
		// utils::ReadParam( $sName, $defaultValue = "", $bAllowCLI = false, $sSanitizationFilter = 'parameter' )
		$sClassName = utils::ReadParam('class', '', false, 'class');
		$sView = utils::ReadParam('view', '', false, 'string');
		$sFilter = utils::ReadParam('filter', '', false, 'raw_data');
		
		// Load ReportGeneratorExtensions (implementations of iReportGeneratorExtension)
		$sModuleName = utils::GetCurrentModuleName();
		$sModuleDir = APPROOT . '/env-' . utils::GetCurrentEnvironment() . '/' . utils::GetCurrentModuleDir(0);
		
		// Validation
		// --
		
		// Check if right parameters have been given
		if(empty($sClassName) == true) {
			throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'class'));
		}
		
		if(empty($sFilter) == true) {
			throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'filter'));
		}
		
		if(empty($sView) == true) {
			throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'view'));
		}
		
		// Valid type?
		if(in_array($sView, ['details', 'list']) == false) {
			throw new ApplicationException('Valid values for view are: details, list');
		}
		
		$oFilter = DBObjectSearch::unserialize($sFilter);
		// $aAllArgs = \MetaModel::PrepareQueryArguments($oFilter->GetInternalParams());
		// $oFilter->ApplyParameters($aAllArgs); // Thought this was necessary for :current_contact_id. Guess not?
		$oSet_Objects = new CMDBObjectSet($oFilter);		
		
		// Valid object(s)?
		// 20200115-0849: This check seems pointless if there's more automation and a query returns no results
		/*
			if($oSet_Objects->Count() == 0) {
				throw new \ApplicationException('Invalid OQL filter: no object(s) found');
			}
		*/
		
		$aSet_Objects = ReportGeneratorHelper::ObjectSetToArray($oSet_Objects);
		
		// Get keys to build one OQL Query
		$aKeys = [ -1];
		foreach($aSet_Objects as $aObject) {
			$aKeys[] = $aObject['key'];
		}
		
		$oFilter_Attachments = new DBObjectSearch('Attachment');
		$oFilter_Attachments->AddCondition('item_id', $aKeys, 'IN');
		$oFilter_Attachments->AddCondition('item_class', $sClassName);
		$oSet_Attachments = new CMDBObjectSet($oFilter_Attachments);
		$aSet_Attachments = ReportGeneratorHelper::ObjectSetToArray($oSet_Attachments);
		
		foreach($aSet_Objects as &$aObject) {
			
			$aObject['attachments'] = array_filter($aSet_Attachments, function($aAttachment) use ($aObject) {
				return ($aAttachment['fields']['item_id'] = $aObject['key']);
			});
			
			$aObject['attachments'] = array_values($aObject['attachments']);
			
		}
		
		if($sView == 'details') {
			$aReportData['item'] = array_values($aSet_Objects)[0];
		}
		else {
			$aReportData['items'] = $aSet_Objects;
		}
		
		// Expose some variables so they can be used in reports
		$aReportData['current_contact'] = ReportGeneratorHelper::ObjectToArray(UserRights::GetUserObject());
		$aReportData['request'] = $_REQUEST;
		$aReportData['application']['url'] = utils::GetDefaultUrlAppRoot();
		
		// Get all classes implementing iReportTool
		$aReportTools = [];
		foreach(get_declared_classes() as $sClassName) {
			if(in_array('jb_itop_extensions\report_generator\iReportTool', class_implements($sClassName))) {
				$aReportTools[] = $sClassName;
			}
		}
		
		// Enrich first
		foreach($aReportTools as $sClassName) {
			$oSet_Objects->Rewind();
			if($sClassName::IsApplicable($oSet_Objects, $sView) == true) {
				$sClassName::EnrichData($aReportData, $oSet_Objects);
			}
		}
		
		// Sort based on 'rank' of each class
		// Use case: block further processing
		usort($aReportTools, function($a, $b) {
			return $a::$iRank <=> $b::$iRank;
		});
		
		// Execute each ReportExtension
		foreach($aReportTools as $sClassName) {
			$oSet_Objects->Rewind();
			if($sClassName::IsApplicable($oSet_Objects, $sView) == true) {
				$sClassName::DoExec($aReportData, $oSet_Objects);
			}
		}

	}
	catch(Exception $e) {
		require_once(APPROOT.'/application/nicewebpage.class.inc.php');
		$oP = new NiceWebPage(Dict::S('UI:PageTitle:FatalError'));
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>");	
		$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
		$oP->output();
	}

	
