<?php
/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
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

namespace JeffreyBostoenExtensions\Reporting;

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
	
	
	try {
		
		$iOriginalTimeLimit = ini_get('max_execution_time');
		set_time_limit(0);

		
		// Logging in exposed :current_contact_id in OQL
			LoginWebPage::DoLoginEx();

		// Okay if this is empty.
			Helper::SetView(utils::ReadParam('view', '', false, 'string'));
		
		// Sets the object set.
			Helper::SetObjectSetFromFilter();

		// Execute the report.
			Helper::DoExec();
		
		// If needed (most likely exited by now):
		set_time_limit($iOriginalTimeLimit);
		

	}
	catch(Exception $e) {
		
		if($e instanceof SecurityException) {
			http_response_code(403);
		}
		else {
			http_response_code(500);
		}

		$oP = new NiceWebPage(Dict::S('UI:PageTitle:FatalError'));
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>");	
		$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
		$oP->output();
		
	}

	
