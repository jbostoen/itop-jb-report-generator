<?php
/**
 * @copyright   Copyright (c) 2019-2023 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.230624
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
use \DBObjectSearch;
use \Dict;
use \LoginWebPage;
// use \MetaModel;
use \NiceWebPage;
use \SecurityException;
use \utils;


		
	if (!defined('APPROOT')) require_once(__DIR__.'/../../approot.inc.php');
	require_once(APPROOT.'/application/application.inc.php');
	require_once(APPROOT.'/application/displayblock.class.inc.php');
	require_once(APPROOT.'/application/itopwebpage.class.inc.php');
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	require_once(APPROOT.'/application/startup.inc.php');
	require_once(APPROOT.'/application/wizardhelper.class.inc.php');
	require_once(APPROOT.'/core/restservices.class.inc.php');
	
	// Autoloader (Twig, chillerlan\QRCode, ...)
	require_once('vendor/autoload.php');
	
	// Get iTop's Dict::S('string') so it can be exposed to Twig as well 
	// require_once( APPROOT . '/application/utils.inc.php' );
	// require_once( APPROOT . '/core/coreexception.class.inc.php' );
	// require_once( APPROOT . '/core/dict.class.inc.php' );
	
	try {
		
		$iOriginalTimeLimit = ini_get('max_execution_time');
		set_time_limit(0);

		
		// Logging in exposed :current_contact_id in OQL
		if(LoginWebPage::EXIT_CODE_OK != LoginWebPage::DoLoginEx(null /* any portal */, false, LoginWebPage::EXIT_RETURN)) {
			throw new SecurityException('You must be logged in');
		}
		
		$sView = utils::ReadParam('view', '', false, 'string');
		$sFilter = utils::ReadParam('filter', '', false, 'raw_data');
		
		// Validation
		// --
		
		// Check if right parameters have been given
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
		ReportGeneratorHelper::DoExec($oFilter, $sView);
		
		// If needed (most likely exited by now):
		set_time_limit($iOriginalTimeLimit);
		

	}
	catch(Exception $e) {
		require_once(APPROOT.'/application/nicewebpage.class.inc.php');
		$oP = new NiceWebPage(Dict::S('UI:PageTitle:FatalError'));
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>");	
		$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
		$oP->output();
	}

	
