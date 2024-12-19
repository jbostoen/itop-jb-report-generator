<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240425
 *
 * Definition of class ReportProcessorParent. Parent Report Tool (ReportProcessor) to expand upon.
 */

 namespace JeffreyBostoenExtensions\ReportGenerator;

// Generic
use \Exception;

// iTop internals
use ApplicationContext;
use ApplicationException;
use CMDBObjectSet;
use DBObject;
use DBObjectSearch;
use DBObjectSet;
use Dict;
use iTopStandardURLMaker;
use MetaModel;
use ormDocument;
use RestResultWithObjects;
use UserRights;
use utils;

// Spatie BrowserShot
use Spatie\Browsershot\Browsershot;

// chillerlan/php-qrccode
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Abstract class ReportGeneratorHelper.  
 * Helper functions.
 */
abstract class ReportGeneratorHelper {
	
	/** @const \String MODULE_CODE Module code */
	const MODULE_CODE = 'jb-report-generator';
	
	
	/** @var Boolean $bStopProcessing Boolean which can force further processing to stop. Once set to true, all other processing that is supposed to follow, will be skipped. */
	private static $bStopProcessing = false;
	
	/** @var Boolean $bSuppressOutput Boolean which indicates whether output will be suppressed (and stored internally in the below $aHeaders and $sOutput properties. */
	private static $bSuppressOutput = false;
	
	/** @var Array $aHeaders in which PHP headers will be stored if output is suppressed. */
	private static $aHeaders = [];
	
	/** @var String $sOutput in which output will be stored if output is suppressed. */
	private static $sOutput = '';

	/** @var String Trace ID. */
	private static $sTraceId = '';

	/** @var String View: 'details' or 'list'. */
	private static $sView = '';

	/**
	 * @var DBObjectSet|null $oSet_Objects;
	 */
	private static $oSet_Objects = null;
	
	/**
	 * Returns an array (similar to REST/JSON) from an iTop object set.
	 *
	 * @param DBObjectSet $oObjectSet iTop object set.
	 *
	 * @return Array Each key is 'Class::ID' (the class being the common ancestor of the object set), with the value being an array (REST/JSON API structure).
	 */
	public static function ObjectSetToArray(DBObjectSet $oObjectSet) {
		
		$aResult = [];
		
		$aShowFields = [];
		$sClass = $oObjectSet->GetClass();

		foreach(MetaModel::ListAttributeDefs($oObjectSet->GetClass()) as $sAttCode => $oAttDef) {
			$aShowFields[$sClass][] = $sAttCode;
		}
		
		while($oObject = $oObjectSet->Fetch()) {
			$aResult[$sClass.'::'.$oObject->GetKey()] = static::ObjectToArray($oObject, $aShowFields);
		}
		
		return $aResult;
		
	}

	/**
	 * Returns array (similar to iTop REST/JSON) from object.
	 *
	 * @param DBObject $oObject iTop object.
	 * @param String[] $aShowFields List of attribute codes to return. If not specified, all values of each attribute code will returned.
	 *
	 * @return Array REST/JSON API structure.
	 *
	 *
	 */
	public static function ObjectToArray(DBObject $oObject, $aShowFields = null) {
		
		$oResult = new RestResultWithObjects();
		
		if($aShowFields === null) {
				
			$aShowFields = [];
			$sClass = get_class($oObject);
			
			foreach(MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$aShowFields[$sClass][] = $sAttCode;
			}
			
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
	
	/**
	 * Executes the reporting.
	 *
	 * @param String $sFilter OQL filter
	 *
	 * @return void
	 */
	public static function DoExec() {
		
		static::ClearHeaders();
		static::ClearOutput();

	
		// $aAllArgs = \MetaModel::PrepareQueryArguments($oFilter->GetInternalParams());
		// $oFilter->ApplyParameters($aAllArgs); // Thought this was necessary for :current_contact_id. Guess not?

		
		if(static::$oSet_Objects !== null) {
			
			$aSet_Objects = static::ObjectSetToArray(static::$oSet_Objects);
			
			if(static::GetView() == 'details') {
				$aReportData['item'] = array_values($aSet_Objects)[0];
			}
			else {
				$aReportData['items'] = $aSet_Objects;
			}

		}

		
		// Expose some variables so they can be used in reports
		$aReportData['current_contact'] = static::ObjectToArray(UserRights::GetUserObject());
		$aReportData['request'] = $_REQUEST;
		
		// When running this as a regular user, this is not an issue.
		// When starting to work with background tasks, this becomes an issue as the path looks like http://C:\xampp64\htdocs\iTop3\web\webservices\cron.php
		try {
			$aReportData['application']['url'] = utils::GetDefaultUrlAppRoot();
		}
		catch(Exception $e) {
			$aReportData['application']['url'] = MetaModel::GetConfig()->Get('app_root_url');
		}
		
		// Get all classes implementing iReportProcessor
		$aReportProcessors = [];
		foreach(get_declared_classes() as $sClassName) {
			if(in_array('jb_itop_extensions\report_generator\iReportProcessor', class_implements($sClassName))) {
				$aReportProcessors[] = $sClassName;
			}
		}
		
		// Enrich first
		foreach($aReportProcessors as $sClassName) {

			if($sClassName::IsApplicable() == true) {
				
				$sClassName::EnrichData($aReportData);

			}

		}
		
		// Sort based on 'rank' of each class
		// Use case: block further processing
		usort($aReportProcessors, function($a, $b) {
			return $a::$iRank <=> $b::$iRank;
		});

		// Execute each ReportProcessor
		foreach($aReportProcessors as $sClassName) {
			
			if($sClassName::IsApplicable() == true) {

				$sClassName::DoExec($aReportData);

			}
			
			// A processor might indicate that further processing should be abandoned.
			if(static::GetStopProcessing() == true) {
				break;
			}
			
		}
		
	}
	
	/**
	 * Sets iTop objects set (currently being processed).
	 *
	 * @param DBObjectSet $oSet_Objects A set of iTop objects.
	 *
	 * @return void
	 */
	public static function SetObjectSet($oSet_Objects) {

		static::$oSet_Objects = $oSet_Objects;
		
	}
	
	/**
	 * Sets iTop objects set (currently being processed).  
	 * This will use the URL parameter "filter" to fetch the object set.
	 *
	 * @return void
	 */
	public static function SetObjectSetFromFilter() {

		$sFilter = utils::ReadParam('filter', '', false, 'raw_data');
		
		if($sFilter !== '') {
				
			$oFilter = DBObjectSearch::unserialize($sFilter);
			static::$oSet_Objects = new DBObjectSet($oFilter);

		}
		
	}

	/**
	 * Gets iTop object set (currently being processed).
	 *
	 * @return DBObjectSet
	 */
	public static function GetObjectSet() {
		
		// Rewind if there is a DBObjectSet.
		if(static::$oSet_Objects !== null) {
			static::$oSet_Objects->Rewind();
		}

		return static::$oSet_Objects;
		
	}
	
	
	/**
	 * Trace function used for debugging.
	 *
	 * @return void
	 */
	public static function Trace($sMessage) {
		
		// Store somewhere?		
		if(MetaModel::GetModuleSetting(static::MODULE_CODE, 'trace_log', false) == true) {
			
			$sTraceFileName = APPROOT.'/log/trace_report_generator.log';
			
			try {
				
				if(static::$sTraceId == null) {
				
					static::$sTraceId = bin2hex(random_bytes(10));
					
				}
				
				// Not looking to create an error here 
				file_put_contents($sTraceFileName, date('Y-m-d H:i:s').' | '.static::$sTraceId.' | '.$sMessage.PHP_EOL , FILE_APPEND | LOCK_EX);
				
			}
			catch(Exception $e) {
				// Don't do anything
			}
			
		}
		
	}
	
	
	/**
	 * Clears the headers.
	 *
	 * @return void
	 */
	public static function ClearHeaders() {
		
		static::$aHeaders = [];
		
	}
	
	/**
	 * Sets a header.
	 *
	 * @param String $sHeaderName Header name.
	 * @param String $sHeaderValue Value of the header.
	 *
	 * @return void
	 */
	public static function SetHeader($sHeaderName, $sHeaderValue) {
		
		if(static::$bSuppressOutput == false && headers_sent() == false) {
			
			header($sHeaderName.': '.$sHeaderValue);
			
		}
		else {
			
			static::$aHeaders[$sHeaderName] = $sHeaderValue;
	
		}
	
	}
	
	/**
	 * Returns the headers.
	 *
	 * @return String
	 */
	public static function GetHeaders() {
		
		return static::$aHeaders;
		
	}
	
	/**
	 * Clears the output.
	 *
	 * @return void
	 */
	public static function ClearOutput() {
		
		static::$sOutput = '';
		
	}
	
	/**
	 * Adds to the output.
	 *
	 * @param String $sOutput Output.
	 *
	 * @return void
	 */
	public static function AddOutput($sOutput) {
		
		if(static::$bSuppressOutput == false) {
		
			echo $sOutput;
			
		}
		else {
			
			static::$sOutput .= $sOutput;
	
		}
		
	}
	
	/**
	 * Returns the output.
	 *
	 * @return String
	 */
	public static function GetOutput() {
		
		return static::$sOutput;
		
	}
	
	/**
	 * Sets whether or not the report processor should stop.
	 *
	 * @param Boolean $bValue True/false.
	 *
	 * @return void
	 */
	public static function SetStopProcessing($bValue) {
		
		static::$bStopProcessing = $bValue;
		
	}
	
	/**
	 * Returns whether or not the report processor should stop.
	 *
	 * @return Boolean
	 */
	public static function GetStopProcessing() {
		
		return static::$bStopProcessing;
		
	}
	
	/**
	 * Sets whether or not the output should be suppressed (in which case it will be stored internally).
	 *
	 * @param Boolean $bValue True/false.
	 *
	 * @param Boolean
	 */
	public static function SetSuppressOutput($bValue) {
		
		static::$bSuppressOutput = $bValue;
		
	}
	
	/**
	 * Returns whether or not the output should be suppressed (in which case it will be stored internally).
	 *
	 * @return Boolean
	 */
	public static function GetSuppressOutput() {
		
		return static::$bSuppressOutput;
		
	}
	
	/**
	 * Set view.
	 *
	 * @param string $sView The view. 'details' or 'list'. Other values can be set, but don't have any meaning.
	 * 
	 * @return void
	 */
	public static function SetView($sView) {

		static::$sView = $sView;

	}
	
	/**
	 * Get view.  
	 * Returns the value of the "view".
	 * 
	 * Usually, when viewing a report, this is derived from the URL parameter ('list', 'details').
	 * For other purposes (e.g. when viewing iTop menus and adding menus/buttons), it is set explicitly.
	 *
	 * @return string
	 */
	public static function GetView() : string {
		
		return static::$sView;

	}


}

/**
 * Interface iReportProcessor.
 * Implement this interface to enrich data or perform other actions, e.g. to show a HTML report or generate a PDF file.
 */
interface iReportProcessor {
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @return Boolean
	 */
	public static function IsApplicable();
	
	/**
	 * Hook to enrich the report data.
	 *
	 * @param Array $aReportData Report data.
	 * 
	 * @return void
	 *
	 */
	public static function EnrichData(&$aReportData);
	
	/**
	 * Action hook.
	 *
	 * @param Array $aReportData Report data.
	 *
	 */
	public static function DoExec($aReportData);

}

/**
 * Interface iReportUIElement.
 * Implement this interface to hook into the UI of iTop's console (backend) to show a button or add a menu action.
 */
interface iReportUIElement {
	
	/**
	 * If a button should be shown instead of a menu item
	 *
	 * @return Boolean
	 *
	 */
	public static function ForceButton();
	
	/**
	 * Returns the precedence (order. Low = first, high = later)
	 *
	 * @return Float
	 *
	 */
	public static function GetPrecedence();
	
	/**
	 * Gets the HTML target. Uusally '_blank' or '_self'
	 *
	 * @return String
	 *
	 */
	public static function GetTarget();
	
	/**
	 * Title of the menu item or button.
	 * 
	 * @return String
	 *
	 * @details Hint: you can use Dict::S('...')
	 *
	 */
	public static function GetTitle();
	
	/**
	 * URL Parameters.
	 * 
	 * @return Array
	 */
	public static function GetURLParameters();
	
	
	/**
	 * Whether or not this extension is applicable.
	 *
	 * @return Boolean
	 *
	 */
	public static function IsApplicable();
	
}

/**
 * Class AbstractReportUIElement. Extend this class (and make it applicable) to add actions (buttons or menus) in iTop.
 */
abstract class AbstractReportUIElement implements iReportUIElement {
	
	/**
	 * If a button should be shown instead of a menu item
	 *
	 * @return Boolean
	 *
	 */
	public static function ForceButton() {
		return true;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetPrecedence() {
		return 100;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetTarget() {
		return '_blank';
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetTitle() {
		return '';
	}
	
	/**
	 * @inheritDoc
	 */
	public static function GetURLParameters() {
		return [];
	}
	
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() {
		return false;
	}
	
}

/**
 * Class ReportProcessorParent. Main class (report tool) which can be used as a parent, so some properties are automatically inherited.
 */
abstract class ReportProcessorParent implements iReportProcessor {
	
	/**
	 * @var Integer $iRank Rank. Lower number = goes first.
	 */
	public static $iRank = 50;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() {
		
		// This parent class should not be applicable.
		return false;
		
	}

	/**
	 * @inheritDoc
	 */
	public static function EnrichData(&$aReportData) {
		
		// Enrich data.
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec($aReportData) {
		
		// Do stuff.
		
	}
	
	/**
	 * Outputs error (from Exception).
	 *
	 * @param Exception $e Exception
	 *
	 * @return void
	 */
	public static function OutputError(Exception $e) {
		
		ReportGeneratorHelper::Trace('Exception occurred: '.$e->GetMessage()); 
		
		// Leads to bad things in iTop 3.0
		die($e->getMessage());
			
	}
	
}

/**
 * Class ReportProcessorAttachments. Only enriches the dataset with related attachments when needed.
 */
abstract class ReportProcessorAttachments extends ReportProcessorParent  {
	
	/**
	 * @var Integer $iRank Rank. Lower number = goes first. Should run before ReportProcessorTwig and ReportProcessorTwigToPDF.
	 */
	public static $iRank = 1;
		
	/**
	 * @inheritDoc
	 *
	 * This is an attempt for backward compatibility as of 21st of December, 2023.
	 *
	 */
	public static function IsApplicable() {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');
		
		// Same conditions as for ReportProcessorTwig, ReportProcessorTwigToPDF.
		$bIsDesiredAction = in_array($sAction, ['', 'download_pdf', 'show_pdf', 'attach_pdf']);
		
		if($bIsDesiredAction == true) {
				
			// Does the file contain an indication of '.attachments' and the use of 'fields.contents' (.data, .mimetype, .filename)?
			$sFileName = ReportProcessorTwig::GetReportFileName();
			
			$sContent = file_get_contents(APPROOT.'env-'.utils::GetCurrentEnvironment().'/'.$sFileName);
			
			if(preg_match('/\.attachments/', $sContent) && preg_match('/fields\.contents\.(data|mimetype|filename)/', $sContent)) {
			
				return true;
			}
			
				
		}
			
		
		return false;
		
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function EnrichData(&$aReportData) {
		
		/** @var DBObjectSet|null $oSet_Objects iTop objects. */
		$oSet_Objects = ReportGeneratorHelper::GetObjectSet();

		// Get keys to build one OQL Query
		$aKeys = [ -1];
		
		while($oObj = $oSet_Objects->Fetch()) {
			$aKeys[] = $oObj->GetKey();
		}
		
		// Retrieve attachments.
		$oFilter_Attachments = new DBObjectSearch('Attachment');
		$oFilter_Attachments->AddCondition('item_id', $aKeys, 'IN');
		$oFilter_Attachments->AddCondition('item_class', $oSet_Objects->GetClass());
		$oSet_Attachments = new CMDBObjectSet($oFilter_Attachments);
		
		// In case of 'list':
		if(isset($aReportData['items']) == true) {
			foreach($aReportData['items'] as &$aObject) {
				
				// Attachments are linked to one object only.
				// So it's okay to just convert it here when needed.
				$oSet_Attachments->Rewind();
				
				while($oAttachment = $oSet_Attachments->Fetch()) {
					$aObject['attachments'][] = ReportGeneratorHelper::ObjectToArray($oAttachment);
				}
				
			}
		}

		// In case of 'details':
		if(isset($aReportData['item']) == true) {

			// Attachments are linked to one object only.
			// So it's okay to just convert it here when needed.
			$oSet_Attachments->Rewind();
			
			while($oAttachment = $oSet_Attachments->Fetch()) {
				$aReportData['item']['attachments'][] = ReportGeneratorHelper::ObjectToArray($oAttachment);
			}

		}
	
	}
	
}


/**
 * Class ReportProcessorTwig.  
 * Renders a report with basic object details using Twig.
 */
abstract class ReportProcessorTwig extends ReportProcessorParent {
		
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');
		return ($sAction == '');
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function EnrichData(&$aReportData) {
		
		// @todo This extension was created for iTop 2.7. In the meanwhile, some methods are exposed natively in iTop 3.0
		
		// Enrich data with iTop setting (remove trailing /)
		$aReportData['itop']['root_url'] = trim(utils::GetAbsoluteUrlAppRoot(), '/');
		$aReportData['itop']['env'] = utils::GetCurrentEnvironment();
		$aReportData['itop']['report_url'] = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?'.
			'&exec_module='.ReportGeneratorHelper::MODULE_CODE.
			'&exec_page=reporting.php'.
			'&exec_env='.utils::GetCurrentEnvironment();
		
		// Enrich with common libraries
		$sModuleUrl = utils::GetCurrentModuleUrl();
		
		// JavaScript
		$aReportData['lib']['jquery']['js'] = $sModuleUrl.'/vendor/components/jquery/jquery.min.js';
		$aReportData['lib']['bootstrap']['js'] = $sModuleUrl.'/vendor/twbs/bootstrap/dist/js/bootstrap.min.js';
		
		// CSS
		$aReportData['lib']['bootstrap']['css'] = $sModuleUrl.'/vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
		$aReportData['lib']['fontawesome']['css'] = $sModuleUrl.'/vendor/components/font-awesome/css/all.min.css';
		
		// Request
		$aReportData['request'] = $_REQUEST;
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec($aReportData) {
		
		try {
		
			$sHTML = static::GetReportFromTwigTemplate($aReportData);
			$sReportFile = static::GetReportFileName();
			
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
				ReportGeneratorHelper::SetHeader('Content-Type', $aExtensionsToContentTypes[$sReportFileExtension]);
			}
			
			ReportGeneratorHelper::AddOutput($sHTML);
		
		}
		catch(Exception $e) {
			static::OutputError($e);
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
	 *
	 * @return String Filename
	 */
	public static function GetReportFileName() {
		
		$sView = ReportGeneratorHelper::GetView();

		$sTemplateName = utils::ReadParam('template', '', false, 'string');
		
		if(empty($sTemplateName) == true) {
			throw new ApplicationException(Dict::Format('UI:Error:1ParametersMissing', 'template'));
		}
		
		// 2.7: Don't use utils::GetCurrentModuleDir(0).
		// When new reports are added with a different extension/module, it should return that path instead.
		$sReportModuleDir = utils::GetAbsoluteModulePath(utils::ReadParam('reportdir', '', 'string'));

		// Modern: Actually use the template name.
		$sReportFile = $sReportModuleDir.$sTemplateName;


		if(file_exists($sReportFile) == false) {
			
			/** @var DBObjectSet|null $oSet_Objects */
			$oSet_Objects = ReportGeneratorHelper::GetObjectSet();

			if($oSet_Objects !== null) {

				$sClassName = $oSet_Objects->GetClass();
			
				// Legacy behavior: Automatically build the report from the class name and provided 'view'.
				$sReportFileAlternative = $sReportModuleDir.'reports/templates/'.$sClassName.'/'.$sView.'/'.$sTemplateName;
				
				if(file_exists($sReportFileAlternative) == true) {
					ReportGeneratorHelper::Trace('Deprecated: Legacy mode for file name: '.$sReportFileAlternative);
					$sReportFile = $sReportFileAlternative;
				}
				else {
					ReportGeneratorHelper::Trace('Template does not exist: '.$sReportFile.' / Alternative: '.$sReportFileAlternative);
					throw new ApplicationException('Template does not exist.');
				}

			}

		}
		
		// Prevent local file inclusion
		// Mind: needs extra escaping!
		if(!preg_match('/^[A-Za-z0-9\-_\\\\\/\:]{1,}\.[A-Za-z0-9]{1,}$/', $sTemplateName)) {
			ReportGeneratorHelper::Trace('Potential local file inclusion: '.$sReportFile);
			throw new ApplicationException('Potential local file inclusion detected (LFI). This path is not allowed: "'.$sReportFile.'"');
		}
		
		$sReportFile = str_replace(APPROOT.'env-'.utils::GetCurrentEnvironment().'/', '', $sReportFile);
		
		return $sReportFile;
		
	}
	
	/**
	 * Returns content (HTML, XML, ...) of report
	 *
	 * @param Array $aReportData Hashtable
	 *
	 * @return String Content
	 */
	public static function GetReportFromTwigTemplate($aReportData = []) {
		
		// If class doesn't exist, fail silently
		if(class_exists('\Twig\Loader\FilesystemLoader') == false) {
			throw new ApplicationException('The correct version of Twig does not seem to be configured or installed properly.');
		}
		
		$sReportFile = static::GetReportFileName();
		
		// Twig Loader
		// $loader = new \Twig\Loader\FilesystemLoader(dirname($sReportFile));
		// Expose entire 'extensions' (env-xxx) directory so it's possible to include Twig templates
		$loader = new \Twig\Loader\FilesystemLoader(APPROOT.'env-'.utils::GetCurrentEnvironment());
		
		// Twig environment options
		$oTwigEnv = new \Twig\Environment($loader, [
			'autoescape' => false,
			'cache' => false // No cache is default; but enforce!
		]);

		$oTwigEnv->addFilter(new \Twig\TwigFilter('make_object_url', function ($sObjClass, $sObjKey) {
				return ApplicationContext::MakeObjectUrl($sObjClass, $sObjKey, null, false);
			})
		);

		// Combodo uses this filter, so let's use it the same way for our report generator
		$oTwigEnv->addFilter(new \Twig\TwigFilter('dict_s', function ($sStringCode, $sDefault = null, $bUserLanguageOnly = false) {
				return Dict::S($sStringCode, $sDefault, $bUserLanguageOnly);
			})
		);
		
		// Relies on chillerlan/php-qrcode; optionally.
		if(class_exists('chillerlan\QRCode\QRCode') == true) {
			
			$oTwigEnv->addFilter(new \Twig\TwigFilter('qr', function ($sString) {
				
					// Suppress empty attributes.
					if($sString == '') {
						return '';
					}

					$aOptions = new \chillerlan\QRCode\QROptions([
						'version'    => 5,
						'eccLevel'   => \chillerlan\QRCode\Common\EccLevel::L,
						'outputType' => QROutputInterface::GDIMAGE_PNG,
						'scale'		 => 3 // Note: scale is for SVG, IMAGE_*. output. Irrelevant for HTML output; use CSS
					]);

					// Invoke a fresh QRCode instance.
					$oQRCode = new \chillerlan\QRCode\QRCode($aOptions);

					// Dump the output .
					return '<img class="qr" src="'.$oQRCode->render($sString).'">';
			
				})
			);
				
		}
		else {
			
			$oTwigEnv->addFilter(new \Twig\TwigFilter('qr', function ($sString) {
				return $sString.' (PHP Library chillerlan\QRCode\QRCode missing)';
			}));
				
		}
		
		$sHTML = $oTwigEnv->render($sReportFile, $aReportData);
		
		// When using different environments (usually stored in $_SESSION but it can be called with switch_env), a more complete URL is needed for some renderers (e.g. ReportProcessorTwigToPDF)
		// Example of inline image: https://127.0.0.1:8182/iTop/web/pages/ajax.document.php?operation=download_inlineimage&id=12&s=8fb03e"
		$sNeedle = '/web/pages/ajax.document.php?operation=download_inlineimage';
		$sHTML = str_replace($sNeedle, $sNeedle.'&switch_env='.utils::GetCurrentEnvironment(), $sHTML);
		
		return $sHTML;
		
	}
	
}


/**
 * Class ReportProcessorTwigToPDF. Generate PDF from Twig reports.
 */
abstract class ReportProcessorTwigToPDF extends ReportProcessorTwig {
	
	/**
	 * @inheritDoc
	 *
	 * @param DBObjectSet $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 * @param String $sView View. 'details', 'list'
	 * *
	 * @return Boolean
	 *
	 */
	public static function IsApplicable() {
		
		$sAction = utils::ReadParam('action', '', false, 'string');
		return (in_array($sAction, ['download_pdf', 'show_pdf', 'attach_pdf']) == true);
		
	}
	
	/**
	 * @inheritDoc
	 * 
	 * @return void
	 *
	 */
	public static function DoExec($aReportData) {
		
		try {
			
			/** @var DBObjectSet|null $oSet_Objects iTop objects. */
			$oSet_Objects = ReportGeneratorHelper::GetObjectSet();
			
			/** @var Spatie\Browsershot\Browsershot $oPDF PDF Object */
			$sBase64 = static::GetPDFObject($aReportData);
			$sPDF = base64_decode($sBase64);
			
			$sAction = utils::ReadParam('action', '', false, 'string');
			
			/** @var DBObject $oObject iTop object */
			$oObject = $oSet_Objects->Fetch();
		
			switch($sAction) {
				case 'show_pdf':
				case 'download_pdf':
					
					ReportGeneratorHelper::SetHeader('Content-Type', 'application/pdf');
					ReportGeneratorHelper::SetHeader('Content-Disposition', ($sAction == 'show_pdf' ? 'inline' : 'attachment').';filename='.date('Ymd_His').'_'.get_class($oObject).'_'.$oObject->GetKey().'.pdf');
					
					ReportGeneratorHelper::AddOutput($sPDF);
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
					
					ReportGeneratorHelper::SetHeader('Location', $sUrl);
					
					ReportGeneratorHelper::SetStopProcessing(true);
					
					
					break;
					
					
				default:
					// Unexpected
			}
			
				
		}
		catch(Exception $e) {
			static::OutputError($e);
		}
		
	}
	
	/**
	 * Get PDF object based on report data.
	 *
	 * @param Array $aReportData Hashtable
	 *
	 * @return String
	 */
	public static function GetPDFObject($aReportData) {
		
		$sMode = MetaModel::GetModuleSetting(ReportGeneratorHelper::MODULE_CODE, 'pdf_renderer', 'browsershot');
		
		if($sMode == 'browsershot') {
			
			try {		
				
				// This default implementation now relies on Spatie's BrowserShot.
				// It doesn't rely on tcpdf and also no longer on MikeHeartl's WkHtmlToPdf since they still didn't support a lot of modern web standards, e.g. the Twitter BootStrap package.
				
				// If class doesn't exist, this should fail
				if(class_exists('\Spatie\Browsershot\Browsershot') == false) {
					throw new ApplicationException('PHP Library \Spatie\BrowserShot\BrowserShot seems not to be configured or installed properly.');
				}
			
				// Get HTML for this report
				$sHTML = static::GetReportFromTwigTemplate($aReportData);
				
				$aBrowserShotSettings = MetaModel::GetModuleSetting(ReportGeneratorHelper::MODULE_CODE, 'browsershot', [
					'node_binary' => 'node.exe', // Directory with node binary is in an environmental variable
					'npm_binary' => 'npm.cmd', // Directory with NPM cmd file is in an environmental variable
					'chrome_path' => 'C:/progra~1/Google/Chrome/Application/chrome.exe', // Directory with a Chrome browser executable
					'ignore_https_errors' => false, // Set to "true" if using invalid or self signed certificates
				]);
				
				$oBrowsershot = new Browsershot();
				
				$iTimeout = utils::ReadParam('timeout', 60, 'integer');
				$sPageFormat = utils::ReadParam('page_format', 'A4', 'raw');
				
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
					
					// Deliberately using double quotes here and inner quotes within
					// ->waitForFunction("function() { if(typeof window.ReportComplete != 'function') { return true; } else { return window.ReportComplete() } }", null, ($iTimeout * 1000) -1) // function, polling, timeout. Mind that the timeout should be less than the default timeout
					->timeout($iTimeout) // seconds

					// With Pass GenerateDocumentOutline through new headless (in Chrome 126.0.6450.0 and later) 
					// things like chrome --headless=new --print-to-pdf --no-pdf-header-footer --generate-pdf-document-outline=true now work and emit a document outline. 
					// Passing generateDocumentOutline: true through the Chrome Devtools Protocol also works (here in puppeteer exposed as outline: true).
					->setOption('outline', true)
					// For the above, the new headless mode is required.
					->newHeadless()
				;
				
				if($aBrowserShotSettings['ignore_https_errors'] == true) {
					$oBrowsershot->ignoreHttpsErrors(); // Necessary on quickly configured local hosts with self signed certificates, otherwise linked scripts and stylesheets are ignored
				}
					
				$sBase64 = $oBrowsershot->base64pdf();

				return $sBase64;
					
			}
			catch(Exception $e) {
				static::OutputError($e);
			}

		}
		elseif($sMode == 'external') {
		
			
			try {
				
				$aExternalRendererSettings = MetaModel::GetModuleSetting(ReportGeneratorHelper::MODULE_CODE, 'pdf_external_renderer', []);
				$aExternalRendererSettings = array_merge([
					'url' => '',
					'skip_certificate_check' => false
				], $aExternalRendererSettings);
			
				$sProxyUrl = $aExternalRendererSettings['url'];
				
				if($sProxyUrl == '') {
					throw new Exception('No URL specified (pdf_external_renderer_url section).');
				}
				
				// Get HTML for this report
				$sHTML = static::GetReportFromTwigTemplate($aReportData);
				
				// Post data as JSON
				$ch = curl_init($sProxyUrl);
				
				// Create payload
				$sPayload = json_encode(['data' => $sHTML]);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $sPayload);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Accept:application/json']);
				curl_setopt($ch, CURLOPT_TIMEOUT, 60 * 5);

				// Return response instead of printing.
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				
				// Possibility to allow self-signed certificates etc
				if($aExternalRendererSettings['skip_certificate_check'] == true) {
					
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

				}
				
				// Send request
				$sResponse = curl_exec($ch);
				$iHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				
				if($iHttpStatus != 200) {
					throw new Exception('Invalid HTTP response code: '.$iHttpStatus.' - '.curl_error($ch));
				}
				
				curl_close($ch);
				
				// Process response
				$oData = json_decode($sResponse);
				
				if(json_last_error() !== JSON_ERROR_NONE) {
					throw new Exception('Invalid JSON structure: '.$sResponse);
				}
				
				if($oData->error != 0) {
					throw new Exception('Failed to render PDF. Error code: '.$oData->error.' - message: '.$oData->message);
				}
				
				return $oData->pdf;
				
			}
			catch(Exception $e) {
				static::OutputError($e);
			}
			
		}
		else {
			static::OutputError(new Exception('Unsupported PDF renderer mode: "'.$sMode.'"'));
		}
		
	}

	
}
