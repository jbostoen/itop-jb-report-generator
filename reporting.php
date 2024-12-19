<?php
/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240425
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

namespace JeffreyBostoenExtensions\ReportGenerator;

// iTop internals.
use Dict;
use LoginWebPage;
use SecurityException;
use utils;
use Combodo\iTop\Application\WebPage\NiceWebPage;

// Generic.
use Exception;
		
	if (!defined('APPROOT')) require_once(__DIR__.'/../../approot.inc.php');
	require_once(APPROOT.'/application/application.inc.php');
	require_once(APPROOT.'/application/displayblock.class.inc.php');
	
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

		// Okay if this is emmpty.
		ReportGeneratorHelper::SetView(utils::ReadParam('view', '', false, 'string'));
		
		// Sets the object set.
		ReportGeneratorHelper::SetObjectSetFromFilter();

		// Execute the report.
		ReportGeneratorHelper::DoExec();
		
		// If needed (most likely exited by now):
		set_time_limit($iOriginalTimeLimit);
		

	}
	catch(Exception $e) {
		
		$oP = new NiceWebPage(Dict::S('UI:PageTitle:FatalError'));
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>");	
		$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
		$oP->output();
		
	}

	
