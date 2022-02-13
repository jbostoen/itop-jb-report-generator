<?php

/**
 * @copyright   Copyright (c) 2019-2022 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.220131
 *
 * Definition of class RTParent. Parent Report Tool (RT) to expand upon.
 */

namespace jb_itop_extensions\report_generator;

use \Exception;

// Generic
use \ReflectionClass;

// iTop internals
use \ApplicationException;
use \CMDBObjectSet;
use \DBObject;
use \DBObjectSet;
use \Dict;
use \iTopStandardURLMaker;
use \MetaModel;
use \NiceWebPage;
use \ormDocument;
use \RestResultWithObjects;
use \UserRights;
use \utils;

// Spatie BrowserShot
use \Spatie\Browsershot\Browsershot;

/**
 * Abstract class ReportGeneratorHelper. Helper functions.
 */
abstract class ReportGeneratorHelper {

	/**
	 * Checks whether iTop is 2.7 (LTS) = true or 3.0 or higher = false.
	 *
	 * @return \Boolean
	 */
	public static function IsLegacy() {

		if(defined('ITOP_VERSION') == true && version_compare(ITOP_VERSION, '3.0', '>=')) {
			return false;
		}	
		
		return true;
		
	}
	
	/**
	 * Returns array (similar to REST/JSON) from object set
	 *
	 * @param \CMDBObjectSet $oObjectSet iTop object set
	 *
	 * @return Array
	 */
	public static function ObjectSetToArray(CMDBObjectSet $oObjectSet) {
		
		$aResult = [];
		while($oObject = $oObjectSet->Fetch()) {
			$aResult[] = self::ObjectToArray($oObject);
		}
		
		return $aResult;
		
	}

	/**
	 * Returns array (similar to REST/JSON) from object
	 *
	 * @param \DBObject $oObject iTop object
	 *
	 * @return Array
	 *
	 * @details 
	 * Strangely enough ObjectSetToArray takes a CMDBObjectSet, for instance of Attachments.
	 * However on processing them, it turns into a DBObject?
	 *
	 */
	public static function ObjectToArray(DBObject $oObject) {
		
		$oResult = new RestResultWithObjects();
		$aShowFields = [];
		$sClass = get_class($oObject);
		
		foreach(MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
			$aShowFields[$sClass][] = $sAttCode;
		}
		
		$oResult->AddObject(0, '', $oObject, $aShowFields);
		
		if(is_null($oResult->objects) == true) {
			return [];
		}
		else {
			
			$sJSON = json_encode($oResult->objects);
			
			// Fix #1897 AttributeText (HTML): GetForJSON() -> GetEditValue() -> escaping of '&'
			$sJSON = str_replace('&amp;', '&', $sJSON);
			
			return current(json_decode($sJSON, true));
		}
		
	}
	
}

/**
 * Interface iReportTool.
 * Implement this interface to enrich data or perform other actions, for example generating a PDF.
 */
interface iReportTool {
	
	/**
	 * Whether or not this extension is applicable
	 * 
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 * @param \String $sView View. 'details', 'list'
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView);
	
	/**
	 * Rendering hook
	 *
	 * @param \Array $aReportData Twig data
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function EnrichData(&$aReportData, DBObjectSet $oSet_Objects);
	
	/**
	 * Action hook
	 *
	 * @param \Array $aReportData Report data
	 * @param \DBObjectSet $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function DoExec($aReportData, DBObjectSet $oSet_Objects);

}

/**
 * Interface iReport.
 * Implement this interface to hook into the UI of iTop's console (backend).
 */
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
 * Class RTParent. Main class (report tool) which can be used as a parent, so some properties are automatically inherited
 */
abstract class RTParent implements iReportTool {
	
	/**
	 * @var \Integer $iRank Rank. Lower number = goes first.
	 */
	public static $iRank = 50;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
	}
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @param \DBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 * @param \String $sView View. 'details', 'list'
	 * *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView) {
		
		// This parent class should not be applicable.
		return false;
		
	}
	
	/**
	 * Rendering hook. Can enrich report data (fetching additional info).
	 *
	 * @param \Array $aReportData Report data
	 * @param \DBObjectSet[] $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 * @return void
	 */
	public static function EnrichData(&$aReportData, DBObjectSet $oSet_Objects) {
		
		// Enrich data
		
	}
	
	/**
	 * Action hook
	 *
	 * @param \Array $aReportData Report data
	 * @param \DBObjectSet[] $oSet_Objects DBObjectSet of iTop objects which are being processed
	 *
	 * @return void
	 */
	public static function DoExec($aReportData, DBObjectSet $oSet_Objects) {
		
		// Do stuff
		
	}
	
	/**
	 * Outputs error (from Exception)
	 *
	 * @param \Exception $e Exception
	 *
	 * @return void
	 */
	public static function OutputError(Exception $e) {
		
		if(ReportGeneratorHelper::IsLegacy() == true) {
			
			require_once(APPROOT.'/application/nicewebpage.class.inc.php');
			$oP = new NiceWebPage(Dict::S('UI:PageTitle:FatalError'));
			$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
			$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
			$oP->output();
			die();
			
		}
		else {
			
			// Leads to bad things in iTop 3.0
			die($e->getMessage());
			
		}
			
	}
	
}


/**
 * Class RTTwig. Renders a report with basic object details using Twig.
 */
abstract class RTTwig extends RTParent implements iReportTool {
		
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView) {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');		
		return ($sAction == '');
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function EnrichData(&$aReportData, DBObjectSet $oSet_Objects) {
		
		// @todo This extension was created for iTop 2.7. In the meanwhile, some methods are exposed natively in iTop 3.0
		
		// Enrich data with iTop setting (remove trailing /)
		$aReportData['itop']['root_url'] = substr(utils::GetAbsoluteUrlAppRoot(), 0, -1);
		$aReportData['itop']['env'] = utils::GetCurrentEnvironment();
		
		// Enrich with common libraries
		$sModuleUrl = utils::GetCurrentModuleUrl();
		
		// JavaScript
		$aReportData['lib']['jquery']['js'] = $sModuleUrl.'/vendor/components/jquery/jquery.min.js';
		$aReportData['lib']['bootstrap']['js'] = $sModuleUrl.'/vendor/twbs/bootstrap/dist/js/bootstrap.min.js';
		
		// CSS
		$aReportData['lib']['bootstrap']['css'] = $sModuleUrl.'/vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
		
		// Request
		$aReportData['request'] = $_REQUEST;
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec($aReportData, DBObjectSet $oSet_Objects) {
		
		try {
		
			$sHTML = self::GetReportFromTwigTemplate($aReportData);
			$sReportFile = self::GetReportFileName();
			
			// Set Content-Type header for these extensions
			$aExtensionsToContentTypes = [
				'csv' => 'text/csv',
				'html' => 'text/html',
				'json' => 'application/json',
				'twig' => 'text/html',
				'txt' => 'text/plain',
				'xml' => 'text/xml'
			];
			
			// Check if known extension, set MIME Type
			$sReportFileExtension = strtolower(pathinfo($sReportFile, PATHINFO_EXTENSION));
			if(isset($aExtensionsToContentTypes[$sReportFileExtension]) == true) {
				header('Content-Type: '.$aExtensionsToContentTypes[$sReportFileExtension]);
			}
			
			echo $sHTML;
		
		}
		catch(Exception $e) {
			self::OutputError($e);
		}
		
	}
	
	/**
	 * Returns default filename of report.
	 * The current implementation expects the reports to be in the module's directory as 'reports/templates/className/type/templateName.ext'; 
	 * where 
	 *  "className" is an iTop class and 
	 *  "type" is usually "details" or "list"
	 *  "templateName.ext" is free to choose
	 *
	 * @return \String Filename
	 */
	public static function GetReportFileName() {
		

		$sClassName = utils::ReadParam('class', '', false, 'class');
		$sType = utils::ReadParam('type', '', false, 'string');
		$sTemplateName = utils::ReadParam('template', '', false, 'string');
		$sReport = 'jb_itop_extensions\\report_generator\\'.utils::ReadParam('report', '', false, 'string');
		
		// Values for 'class' and 'type' were already validated		
		if(empty($sTemplateName) == true) {
			throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'template'));
		}
		
		// 2.7: Don't use utils::GetCurrentModuleDir(0).
		// When new reports are added with a different extension/module, it should return that path instead.		
		$sCurrentModuleDir = utils::GetAbsoluteModulePath($sReport::sModuleDir);
		$sReportDir = $sCurrentModuleDir.'reports/templates/'.$sClassName.'/'.$sType;
		$sReportFile = $sReportDir.'/'.$sTemplateName;
		
		// Prevent local file inclusion
		// Mind: needs extra escaping!
		if(!preg_match('/^[A-Za-z0-9\-_\\/\\\\:]{1,}\.[A-Za-z0-9]{1,}$/', $sReportFile)) {
			throw new ApplicationException('Potential disallowed local file inclusion: "'.$sReportFile.'"');
		}
		elseif(file_exists($sReportFile) == false) {
			throw new ApplicationException('Template does not exist: '.$sReportFile);
		}
		
		$sReportFile = str_replace(APPROOT.'env-'.utils::GetCurrentEnvironment().'/', '', $sReportFile);
		
		return $sReportFile;
		
	}
	
	/**
	 * Returns content (HTML, XML, ...) of report
	 *
	 * @param \Array $aReportData Hashtable
	 *
	 * @return \String Content
	 */
	public static function GetReportFromTwigTemplate($aReportData = []) {
		
		// If class doesn't exist, fail silently
		if(class_exists('\Twig\Loader\FilesystemLoader') == false) {
			throw new ApplicationException('The correct version of Twig does not seem to be configured or installed properly.');
		}
		
		$sReportFile = self::GetReportFileName();
		
		// Twig Loader
		// $loader = new \Twig\Loader\FilesystemLoader(dirname($sReportFile));
		// Expose entire 'extensions' (env-xxx) directory so it's possible to include Twig templates
		$loader = new \Twig\Loader\FilesystemLoader(APPROOT.'env-'.utils::GetCurrentEnvironment());
		
		// Twig environment options
		$oTwigEnv = new \Twig\Environment($loader, [
			'autoescape' => false,
			'cache' => false // No cache is default; but enforce!
		]); 

		// Combodo uses this filter, so let's use it the same way for our report generator
		$oTwigEnv->addFilter(new \Twig\TwigFilter('dict_s', function ($sStringCode, $sDefault = null, $bUserLanguageOnly = false) {
				return Dict::S($sStringCode, $sDefault, $bUserLanguageOnly);
			})
		);
		
		// Relies on chillerlan/php-qrcode; optionally.
		if(class_exists('chillerlan\QRCode\QRCode') == true) {
			
			$oTwigEnv->addFilter(new \Twig\TwigFilter('qr', function ($sString) {
				
					// Suppress empty attributes
					if($sString == '') {
						return '';
					}

					$aOptions = new \chillerlan\QRCode\QROptions([
						'version'    => 5,
						// 'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
						'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG, // SVG is not rendered with wkhtmltopdf 0.12.5 (with patched qt) 
						'eccLevel'   => \chillerlan\QRCode\QRCode::ECC_L,
						'scale'		 => 3 // Note: scale is for SVG, IMAGE_*. output. Irrelevant for HTML output; use CSS
					]);

					// invoke a fresh QRCode instance
					$oQRCode = new \chillerlan\QRCode\QRCode($aOptions);

					// and dump the output 
					return '<img src="'.$oQRCode->render($sString).'">';		
			
				})
			);
				
		}
		else {
			
			$oTwigEnv->addFilter(new \Twig\TwigFilter('qr', function ($sString) {
				return $sString.' (QR library missing)';
			}));
				
		}
		
		return $oTwigEnv->render($sReportFile, $aReportData);
		
	}
	
}


/**
 * Class RTTwigToPDF. Generate PDF from Twig reports.
 */
abstract class RTTwigToPDF extends RTTwig implements iReportTool {
	
	/**
	 * @inheritDoc
	 *
	 * @param \DBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 * @param \String $sView View. 'details', 'list'
	 * *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable(DBObjectSet $oSet_Objects, $sView) {
		
		$sAction = utils::ReadParam('action', '', false, 'string');
		return (in_array($sAction, ['download_pdf', 'show_pdf', 'attach_pdf']) == true);
		
	}
	
	/**
	 * @inheritDoc
	 * 
	 * @return void
	 *
	 */
	public static function DoExec($aReportData, DBObjectSet $oSet_Objects) {
		
		// If class doesn't exist, fail silently
		if(class_exists('\Spatie\Browsershot\Browsershot') == false) {
			throw new ApplicationException('BrowserShot seems not to be configured or installed properly.');
		}
		
		try {
			
			/** @var \Spatie\Browsershot\Browsershot $oPDF PDF Object */
			$sBase64 = self::GetPDFObject($aReportData);
			$sPDF = base64_decode($sBase64);
			
			$sAction = utils::ReadParam('action', '', false, 'string');
			
			/** @var \DBObject $oObject iTop object */
			$oObject = $oSet_Objects->Fetch();
		
			switch($sAction) {
				case 'show_pdf':
					header('Content-Type: application/pdf');
					header('Content-Disposition:inline;filename='.date('Ymd_His').'_'.get_class($oObject).'_'.$oObject->GetKey().'.pdf');
					echo $sPDF;
					break;
				
				case 'download_pdf':
					header('Content-Type: application/pdf');
					header('Content-Disposition:attachment;filename='.date('Ymd_His').'_'.get_class($oObject).'_'.$oObject->GetKey().'.pdf');
					echo $sPDF;
					break;
					
				case 'attach_pdf':
				
					$sObjClass = get_class($oObject);
					$sObjKey = $oObject->GetKey();
				
					// Create attachment
					$oAttachment = MetaModel::NewObject('Attachment', [
						'user_id' => UserRights::GetUserId(),
						'item_class' => $sObjClass,
						'item_id' => $sObjKey,
						'creation_date' => date('Y-m-d H:i:s'),
						'contents' => new ormDocument($sPDF, 'application/pdf', date('Ymd_His').'_'.get_class($oObject).'_'.$oObject->GetKey().'.pdf')
					]);
					$oAttachment->DBInsert();
					
					// Go back
					$oUrlMaker = new iTopStandardURLMaker();
					$sUrl = $oUrlMaker->MakeObjectURL($sObjClass, $sObjKey);
					header('Location: '.$sUrl);
					exit();
					break;
					
					
				default:
					// Unexpected
			}
			
				
		}
		catch(Exception $e) {
			self::OutputError($e);
		}
		
	}
	
	/**
	 * Get PDF object based on report data.
	 *
	 * @param \Array $aReportData Hashtable
	 *
	 * @return \String
	 */
	public static function GetPDFObject($aReportData) {
		
		try {
		
			// Get HTML for this report
			$sHTML = self::GetReportFromTwigTemplate($aReportData);
				
			// Example of inline image: https://127.0.0.1:8182/iTop/web/pages/ajax.document.php?operation=download_inlineimage&id=12&s=8fb03e"
			// When using different environments (usually stored in $_SESSION but it can be called with switch_env), a more complete URL is needed.
			$sNeedle = '/web/pages/ajax.document.php?operation=download_inlineimage';
			$sHTML = str_replace($sNeedle, $sNeedle.'&switch_env='.utils::GetCurrentEnvironment(), $sHTML);
			
			$aBrowserShotSettings = utils::GetCurrentModuleSetting('browsershot', [
				'node_binary' => 'node.exe', // Directory with node binary is in an environmental variable
				'npm_binary' => 'npm.cmd', // Directory with NPM cmd file is in an environmental variable
				'chrome_path' => 'C:/progra~1/Google/Chrome/Application/chrome.exe', // Directory with a Chrome browser executable
				'ignore_https_errors' => false, // Set to "true" if using invalid or self signed certificates
				'bug_default' => '1'
			]);
			
			$oBrowsershot = new Browsershot();
			
			$iTimeout = utils::ReadParam('timeout', 60, 'integer');
			$sPageFormat = utils::ReadParam('page_format', 60, 'raw');
			
			$oBrowsershot
				// ->setURL('https://google.be')
				->setHTML($sHTML)
				// ->setNodeModulePath('/C:/xampp/htdocs/puppeteer/node_modules/')
				->setNodeBinary($aBrowserShotSettings['node_binary']) // Directory with node binary is in an environmental variable
				->setNpmBinary($aBrowserShotSettings['npm_binary']) // Directory with NPM cmd file is in an environmental variable
				->setChromePath($aBrowserShotSettings['chrome_path']) // Full path to the chrome.exe file (including executable name such as chrome.exe)
				// ->userDataDir('C:/test')
				
				->noSandbox() // Prevent E_CONNRESET error in %temp%\sf_proc_00.err (Windows/Xampp)
				->showBackground() // Necessary to display backgrounds of elements
				
				->fullPage()
				->format($sPageFormat)
				->margins(0, 0, 0, 0)
				
				// Till here it seems fine
				// ->save('c:/tools/test4.pdf');
				
				// Tried these options for localhost images, but it's not working anyway:
				// ->addChromiumArguments(['allow-insecure-localhost '])
				
				// ->waitUntilNetworkIdle()
				// ->setDelay(10 * 1000) // In milliseconds
				// ->waitForFunction('() => { window.chartsRendered >= 3 }', 1000, 660 * 1000) // function, polling, timeout.
				->waitForFunction('if(typeof window.bPageFullyRendered === "undefined") { return true; } else { return window.bPageFullyRendered }', 1000, 90 * 1000) // function, polling, timeout. Mind that the timeout should be less than the default timeout (->timout(60))
				->timeout($iTimeout) // seconds
				
			;
			
			if($aBrowserShotSettings['ignore_https_errors'] == true) {
				$oBrowsershot->ignoreHttpsErrors(); // Necessary on quickly configured local hosts with self signed certificates, otherwise linked scripts and stylesheets are ignored
			}
				
			$sBase64 = $oBrowsershot->base64pdf();

			return $sBase64;
				
		}
		catch(Exception $e) {
			self::OutputError($e);
		}
		
	}
	
	
}
