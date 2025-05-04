<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

 namespace JeffreyBostoenExtensions\Reporting;

// Generic
use Exception;

// iTop internals
use DBObject;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use ObjectResult;
use UserRights;
use utils;

/**
 * Abstract class Helper. 
 */
abstract class Helper {
	
	/** @const string MODULE_CODE Module code */
	const MODULE_CODE = 'jb-report-generator';

	/** @var array $aOptimizedAttCodes The key should be the class; the value is an array of attribute codes. */
	private static $aOptimizedAttCodes = [];
	
	/** @var bool $bSuppressOutput Boolean which indicates whether output will be suppressed (and stored internally in the below $aHeaders and $sOutput properties. */
	private static $bSuppressOutput = false;
	
	/** @var array $aHeaders in which PHP headers will be stored if output is suppressed. */
	private static $aHeaders = [];
	
	/** @var string $sOutput in which output will be stored if output is suppressed. */
	private static $sOutput = '';

	/** @var string Trace ID. */
	private static $sTraceId = '';

	/** @var string View: 'details' or 'list'. */
	private static $sView = '';

	/**
	 * @var DBObjectSet|null $oSet_Objects;
	 */
	private static $oSet_Objects = null;

	/**
	 * Returns an optimized set of attribute codes for the current OQL filter.
	 * 
	 * @param string $sClassName Class name (Can be different from the main set!).
	 *
	 * @return array
	 */
	public static function GetAttributesToOutputForFilter(string $sClassName) : array {

		// Already specified by a processor?
		// If during enriching another class is called; this may need different optimization.
		if(count(static::$aOptimizedAttCodes) == 0 || !isset(static::$aOptimizedAttCodes[$sClassName])) {

			foreach(array_keys(MetaModel::ListAttributeDefs($sClassName)) as $sAttCode) {
				static::$aOptimizedAttCodes[$sClassName][] = $sAttCode;
			}

		}
		
		return static::$aOptimizedAttCodes;

	}
	
	/**
	 * Returns an array (similar to REST/JSON) from an iTop object set.
	 *
	 * @param DBObjectSet $oObjectSet iTop object set.
	 *
	 * @return array Each key is 'Class::ID' (the class being the common ancestor of the object set), with the value being an array (REST/JSON API structure).
	 * 
	 * @deprecated
	 */
	public static function ObjectSetToArray(DBObjectSet $oObjectSet) : array {
		
		$aResult = [];
		
		Helper::Trace('Convert object set.');

		$oObjectSet->Rewind();
		while($oObject = $oObjectSet->Fetch()) {

			$sKey = $oObject::class.'::'.$oObject->GetKey();
			$aResult[$sKey] = static::ObjectToArray($oObject);
			
		}
		
		return $aResult;
		
	}

	/**
	 * Converts an iTop object set (DBObjectSet) to an array of iTop REST/JSON API-like objects (ObjectResult).
	 *
	 * @param DBObjectSet $oSet
	 * 
	 * @return ObjectResult[]
	 */
	public static function ConvertDBObjectSetToObjectResultArray(DBObjectSet $oSet) : array {

		$aObjectResults = [];

		static::Trace('Convert object set to array of ObjectResult objects.');

		$oSet->Rewind();
		while($oObj = $oSet->Fetch()) {

			$aObjectResults[$oObj::class.'::'.$oObj->GetKey()] = static::ConvertDBObjectToObjectResult($oObj);

		}

		return $aObjectResults;

	}

	/**
	 * Converts an iTop object to an iTop REST/JSON API-like object (ObjectResult).
	 *
	 * @param DBObject $oObject iTop object.
	 *
	 * @return ObjectResult
	 * 
	 * @details Hint: Use static::SetOptimizedAttCodes() to limit the outputted fields / tweak performance.
	 */
	public static function ConvertDBObjectToObjectResult(DBObject $oObject) : ObjectResult {

		$sClass = $oObject::class;

		static::Trace('Convert %1$s to API RestResult.', $sClass.'::'.$oObject->GetKey());

		$aOptimizedAttCodes = static::GetAttributesToOutputForFilter($sClass);

		static::Trace('Optimized attribute codes: %1$s', json_encode($aOptimizedAttCodes, JSON_PRETTY_PRINT));

		$oObjRes = new ObjectResult($sClass, $oObject->GetKey());
		$oObjRes->code = 0;
		$oObjRes->message = '';

		$aFields = null;
		if(!is_null($aOptimizedAttCodes)) {

			// Enum all classes in the hierarchy, starting with the current one
			foreach(MetaModel::EnumParentClasses($sClass, ENUM_PARENT_CLASSES_ALL, false) as $sRefClass) {

				if (array_key_exists($sRefClass, $aOptimizedAttCodes)) {
					$aFields = $aOptimizedAttCodes[$sRefClass];
					break;
				}
			}
		}

		if (is_null($aFields)) {
			// No fieldspec given, or not found...
			$aFields = array('id', 'friendlyname');
		}

		foreach ($aFields as $sAttCode) {
			$oObjRes->AddField($oObject, $sAttCode, false);
		}

		return $oObjRes;
		
	}


	/**
	 * Converts a DBObject to an associative array (iTop REST/JSON API-like structure).
	 *
	 * @return array
	 * 
	 * @deprecated
	 */
	public static function ObjectToArray(DBObject $oDBObject) : array {

		$oObjRes = static::ConvertDBObjectToObjectResult($oDBObject);
		$sJSON = json_encode($oObjRes);
		
		// Fix #1897 AttributeText (HTML): GetForJSON() -> GetEditValue() -> escaping of '&'
		$sJSON = str_replace('&amp;', '&', $sJSON);
		
		return json_decode($sJSON, true);

	}
	
	/**
	 * Executes the reporting.
	 *
	 * @param String $sFilter OQL filter
	 *
	 * @return void
	 */
	public static function DoExec() : void {
		
		static::ClearHeaders();
		static::ClearOutput();

		// - Get and sort all classes implementing iBase.
			
			static::Trace('Build list of processors.');

			$aReportProcessors = [];
			foreach(get_declared_classes() as $sClassName) {
				if(in_array('JeffreyBostoenExtensions\Reporting\Processor\iBase', class_implements($sClassName))) {

					if($sClassName::IsApplicable()) {
						$aReportProcessors[] = $sClassName;
					}

				}
			}
			
			// Sort based on 'rank' of each class
			// Use case: block further processing
			usort($aReportProcessors, function(string $a, string $b) {

				return $a::GetRank() <=> $b::GetRank();

			});

			static::Trace('Processors: %1$s', implode(', ', $aReportProcessors));

		// - Before fetch allows optimizations.

			static::Trace('Processors: BeforeFetch().');

			foreach($aReportProcessors as $sClassName) {

				$sClassName::BeforeFetch();

			}

		// - Convert DBObjectSet to REST/JSON API array structure to use in templates.
		// Note: This performs the first fetch!
			
			$aReportData = [];

			if(static::$oSet_Objects !== null) {

				static::Trace('Convert object(s) to REST/JSON structure.');

				static::Trace('There are %1$s objects in the set.', static::$oSet_Objects->Count());

				if(count(static::$aOptimizedAttCodes) > 0) {
					static::Trace('Query optimization: %1$s', json_encode(static::$aOptimizedAttCodes));
				}
				
				$aSet_Objects = static::ConvertDBObjectSetToObjectResultArray(static::$oSet_Objects);
				
				if(static::GetView() == 'details') {
					$aReportData['item'] = array_values($aSet_Objects)[0];
				}
				else {
					$aReportData['items'] = $aSet_Objects;
				}

			}
			else {
				
				static::Trace('There is no OQL filter, so there is no object set.');

			}

		// - Enrich with some common variables.
			
			static::Trace('Common enrichment.');

			// Enrich with common libraries.
			$sModuleUrl = utils::GetCurrentModuleUrl();
			
			// Expose some variables so they can be used in reports
			$aReportData = array_merge_recursive($aReportData, [
				'current_contact' => static::ConvertDBObjectToObjectResult(UserRights::GetUserObject()),
				'request' => $_REQUEST,
				'application' => [
					'url' => MetaModel::GetConfig()->Get('app_root_url'),
				],
				
				'itop' => [
					// Enrich data with iTop setting (remove trailing /)
					'root_url' => rtrim(utils::GetAbsoluteUrlAppRoot(), '/'),
					'env' => utils::GetCurrentEnvironment(),
					// This one may need better documentation:
					'report_url' => utils::GetAbsoluteUrlAppRoot().'pages/exec.php?'.
						'&exec_module='.Helper::MODULE_CODE.
						'&exec_page=reporting.php'.
						'&exec_env='.utils::GetCurrentEnvironment()
				],

				// Included common libraries (deprecated).
				'lib' => [
					'bootstrap' => [
						'js' => $sModuleUrl.'/vendor/twbs/bootstrap/dist/js/bootstrap.min.js',
						'css' => $sModuleUrl.'/vendor/twbs/bootstrap/dist/css/bootstrap.min.css',
					],
					'jquery' => [
						'js' => $sModuleUrl.'/vendor/components/jquery/jquery.min.js',
					],
					'fontawesome' => [
						'css' => $sModuleUrl.'/vendor/components/font-awesome/css/all.min.css',
					],
				],

				// Expose the $_REQUEST parameters (expected: GET).
				'request' => $_REQUEST,
			]);
			
		
		// - Enrich using processors.
			
		static::Trace('Processors: Enrich.');

			foreach($aReportProcessors as $sClassName) {

				$sClassName::EnrichData($aReportData);

			}
			
		// - Execute using processors.
		
			static::Trace('Processors: Execute.');
			
			foreach($aReportProcessors as $sClassName) {
				
				if(!$sClassName::DoExec($aReportData)) {
					break;
				}
				
			}

		static::Trace('Finished.');
		
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
			static::Trace('Filter: %1$s', $sFilter);
			static::$oSet_Objects = new DBObjectSet($oFilter);

		}
		
	}

	/**
	 * Gets iTop object set (currently being processed).
	 *
	 * @return DBObjectSet|null
	 */
	public static function GetObjectSet() : DBObjectSet|null {

		if(static::$oSet_Objects !== null) {

			// In most cases, rewinding is advised anyway.
			static::$oSet_Objects->Rewind();

		}
		
		return static::$oSet_Objects;
		
	}
	

	/**
	 * Trace function used for debugging.
	 *
	 * @param string $sMessage The message.
	 * @param mixed ...$args
	 *
	 * @return void
	 */
	public static function Trace($sMessage, ...$args) : void {
		
		// Store somewhere?		
		if(MetaModel::GetModuleSetting(static::MODULE_CODE, 'trace_log', false) == true) {
			
			$sTraceFileName = sprintf(APPROOT.'/log/trace_reporting_%1$s.log', date('Ymd'));

			try {
				
				$sMessage = call_user_func_array('sprintf', func_get_args());
				
				// Not looking to create an error here 
				file_put_contents($sTraceFileName, sprintf('%1$s | %2$s | %3$s'.PHP_EOL,
					date('Y-m-d H:i:s'),
					static::GetTraceId(),
					$sMessage,
				), FILE_APPEND | LOCK_EX);

			}
			catch(Exception $e) {
				// Don't do anything
			}
			
		}
		
	}
	
	
	/**
	 * Returns the trace ID.
	 *
	 * @return string
	 */
	public static function GetTraceId() : string {

		if(static::$sTraceId == null) {
				
			static::$sTraceId = bin2hex(random_bytes(10));
			
		}

		return static::$sTraceId;

	}

	
	/**
	 * Clears the headers.
	 *
	 * @return void
	 */
	public static function ClearHeaders() : void {
		
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
	public static function SetHeader($sHeaderName, $sHeaderValue) : void {
		
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
	 * @return string[]
	 */
	public static function GetHeaders() : array {
		
		return static::$aHeaders;
		
	}
	
	/**
	 * Clears the output.
	 *
	 * @return void
	 */
	public static function ClearOutput() : void {
		
		static::Trace('Clearing output.');
		static::$sOutput = '';
		
	}
	
	/**
	 * Adds to the output.
	 *
	 * @param String $sOutput Output.
	 *
	 * @return void
	 */
	public static function AddOutput($sOutput) : void {
		
		static::Trace('Adding output. Suppressed = %1$s', static::$bSuppressOutput ? 'yes' : 'no');

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
	 * @return string
	 */
	public static function GetOutput() : string {
		
		return static::$sOutput;
		
	}
	
	/**
	 * Sets whether or not the output should be suppressed (in this case it will be stored internally).
	 *
	 * @param bool $bValue True/false.
	 *
	 * @return void
	 */
	public static function SetSuppressOutput(bool $bValue) : void {
		
		static::Trace('Suppress output: ', $bValue ? 'yes' : 'no');
		static::$bSuppressOutput = $bValue;
		
	}
	
	/**
	 * Returns whether or not the output should be suppressed (in which case it will be stored internally).
	 *
	 * @return bool
	 */
	public static function GetSuppressOutput() : bool {
		
		return static::$bSuppressOutput;
		
	}
	
	/**
	 * Set view.
	 *
	 * @param string $sView The view. 'details' or 'list'. Other values can be set, but don't have any meaning.
	 * 
	 * @return void
	 */
	public static function SetView(string $sView) : void {

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

	/**
	 * Sets the optimized attribute codes.
	 * 
	 * Specifying a limited list of attribute codes is a major performance tweak for larger data sets when relying on an OQL filter that is passed from iTop.
	 *
	 * @param array $aOptimizedAttCodes The key should refer to a class (alias) in an OQL query; the value is an array of attribute codes.
	 * @return void
	 */
	public static function SetOptimizedAttCodes(array $aOptimizedAttCodes) : void {

		static::$aOptimizedAttCodes = $aOptimizedAttCodes;

	}

	/**
	 * Returns the optimized attribute codes.
	 *
	 * @return array
	 */
	public static function GetOptimizedAttCodes() : array {

		return static::$aOptimizedAttCodes;

	}


}

