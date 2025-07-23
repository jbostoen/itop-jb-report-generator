<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting;

use JeffreyBostoenExtensions\Reporting\Processor\Twig as TwigProcessor;

// Generic.
use Exception;
use stdClass;

// iTop internals.
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
	
	/**
	 * @var array $aCache An internal cache. The cache is meant to cache data in some custom processing flows.
	 * 
	 * - Key: Should be a clear indication of what is being cached.
	 * - Value: Could be anything.
	 */
	private static $aCache = [];
	
	/** @var bool $bSuppressOutput Boolean which indicates whether output will be suppressed (and stored internally in the below $aHeaders and $sOutput properties. */
	private static $bSuppressOutput = false;
	
	/** @var array $aHeaders in which PHP headers will be stored if output is suppressed. */
	private static $aHeaders = [];

	/** @var stdClass $oReportData. The report data, built up in steps. */
	private static $oReportData = null;
	
	/** @var string $sOutput in which output will be stored if output is suppressed. */
	private static $sOutput = '';

	/** @var string Trace ID. */
	private static $sTraceId = '';

	/** @var string View: 'details' or 'list'. */
	private static $sView = '';

	/**
	 * @var DBObjectSet|null $oSet Main object set linked to this report. Some reports don't have this.
	 */
	private static $oSet = null;

	/**
	 * Returns an optimized set of attribute codes for the current OQL filter.
	 * 
	 * @param string $sClassName Class name (Can be different from the main set!).
	 *
	 * @return array
	 */
	public static function GetAttributesToOutputForFilter(string $sClass) : array {

		// Already specified by a processor?
		// If during enriching another class is called; this may need different optimization.
		if(!array_key_exists($sClass, static::$aOptimizedAttCodes)) {

			static::Trace('Reverting to default attribute codes for class %1$s.', $sClass);
			static::Trace('Only these classes were available: %1$s', implode(', ', array_keys(static::$aOptimizedAttCodes)));

			static::$aOptimizedAttCodes[$sClass] = array_keys(MetaModel::ListAttributeDefs($sClass));


		}

		static::Trace('Optimized attribute codes for class "%1$s": %2$s', $sClass, json_encode(static::$aOptimizedAttCodes[$sClass], JSON_PRETTY_PRINT));
		
		return static::$aOptimizedAttCodes[$sClass];

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
		
		static::Trace('Convert object set.');

		$oObjectSet->Rewind();
		while($oObject = $oObjectSet->Fetch()) {

			$sKey = $oObject::class.'::'.$oObject->GetKey();
			$aResult[$sKey] = static::ObjectToArray($oObject);
			
		}
		
		return $aResult;
		
	}

	/**
	 * Converts an iTop object set (DBObjectSet) to an array of iTop REST/JSON API-like objects (based on ObjectResult, but stdClass for full extensibility).
	 *
	 * @param DBObjectSet $oSet
	 * 
	 * @return stdClass[]
	 */
	public static function ConvertDBObjectSetToObjectResultArray(DBObjectSet $oSet) : array {

		$aObjectResults = [];

		static::Trace('Convert object set to array of ObjectResult objects.');

		$aOutputAttCodes = static::GetAttributesToOutputForFilter($oSet->GetClass());

		// Determine once and pass through.

		$oSet->Rewind();
		while($oObj = $oSet->Fetch()) {

			$sKey = $oObj::class.'::'.$oObj->GetKey();
			$aObjectResults[$sKey] = static::ConvertDBObjectToObjectResult($oObj, $aOutputAttCodes);

		}

		return $aObjectResults;

	}

	/**
	 * Converts an iTop object to an iTop REST/JSON API-like object (ObjectResult).
	 *
	 * @param DBObject $oObject iTop object.
	 * @param array $aOutputAttCodes An array of attribute codes that should be returned.
	 *
	 * @return stdClass{
	 *     key: int,
	 *     class: string,
	 *     fields: array<string, mixed>
	 * } Object containing the object's class, key, and selected fields. stdClass for full extensibility.
	 * 
	 * 
	 * @details Hint: Use static::SetOptimizedAttCodes() to limit the outputted fields / tweak performance.
	 */
	public static function ConvertDBObjectToObjectResult(DBObject $oObject, ?array $aOutputAttCodes = null) : stdClass {

		$sClass = $oObject::class;
		$sKey = $sClass.'::'.$oObject->GetKey();

		static::Trace('Convert %1$s to API RestResult.', $sKey);

		// In case a single object is converted, this has not been determined yet.
		// In case of an object set, this should be passed on already.
		$aOutputAttCodes = $aOutputAttCodes ?? static::GetAttributesToOutputForFilter($sClass);
		

		$oObjRes = new ObjectResult($sClass, $oObject->GetKey());
		$oObjRes->code = 0;
		$oObjRes->message = '';

		foreach($aOutputAttCodes as $sAttCode) {
			$oObjRes->AddField($oObject, $sAttCode, false);
		}

		// Returning a lighter and extensible class as "code", "message" are often not needed and ObjectResult can't be extended.
		$oRet = new stdClass();
		$oRet->key = $oObjRes->key;
		$oRet->class = $oObjRes->class;
		$oRet->fields = $oObjRes->fields;

		return $oRet;
		
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
			
			static::$oReportData = new stdClass();
			$oReportData = static::$oReportData;

			if(static::$oSet !== null) {

				static::Trace('Convert object(s) to REST/JSON structure.');

				static::Trace('There are %1$s objects in the set.', static::$oSet->Count());

				if(count(static::$aOptimizedAttCodes) > 0) {
					static::Trace('Query optimization: %1$s', json_encode(static::$aOptimizedAttCodes));
				}
				
				$aSet_Objects = static::ConvertDBObjectSetToObjectResultArray(static::$oSet);
				
				if(static::GetView() == 'details') {
					$oReportData->item = array_values($aSet_Objects)[0];
				}
				else {
					$oReportData->items = $aSet_Objects;
				}

			}
			else {
				
				static::Trace('There is no OQL filter, so there is no object set.');

			}
			

		// - Add common variables.

			// Note: This was originally done here, before any enrichment happens further on.
			// It is still here as it allows processors to use or alter this default data.
	
			static::Trace('Add common variables.');
				
				$sBaseDir = APPROOT.'env-'.utils::GetCurrentEnvironment();
				$sReportFile = TwigProcessor::GetReportFileName();
				$sReportContent = file_get_contents($sBaseDir.'/'.$sReportFile);
			
				// Expose some variables so they can be used in reports.
				// Note: Anything that is not very common, should only be added when needed in the report.
				
				// - Contact / user.

					// Quick check as this is a costly operation.
					// This can be slow when there are many items (e.g. tickets) linked to the current contact.
					// "current_contact" is returned without hardcoded optimization here!
					if(strpos($sReportContent, 'current_contact') !== false) {
				
						$oContact = UserRights::GetContactObject();
						if($oContact) {
							$oReportData->current_contact = static::ConvertDBObjectToObjectResult($oContact);
						}

					}
					
					if(strpos($sReportContent, 'current_user') !== false) {

						$oUser = UserRights::GetUserObject();
						if($oUser) {
							$oReportData->current_user = static::ConvertDBObjectToObjectResult($oUser);
						}

					}

				// - $_REQUEST data.
					
					$oReportData->request = $_REQUEST;

				// - iTop.

					$oReportData->itop = new stdClass();
					// Enrich data with iTop setting (remove trailing /)
					$oReportData->itop->root_url = rtrim(utils::GetAbsoluteUrlAppRoot(), '/');
					$oReportData->itop->env = utils::GetCurrentEnvironment();

					// This one may need better documentation:
					$oReportData->itop->report_url = utils::GetAbsoluteUrlAppRoot().'pages/exec.php?'.
								'&exec_module='.static::MODULE_CODE.
								'&exec_page=reporting.php'.
								'&exec_env='.utils::GetCurrentEnvironment();
		
		// - Enrich using processors.
			
		static::Trace('Processors: Enrich.');

			foreach($aReportProcessors as $sClassName) {

				$sClassName::EnrichData();

			}
			
		// - Execute using processors.
		
			static::Trace('Processors: Execute.');
			
			foreach($aReportProcessors as $sClassName) {
				
				if(!$sClassName::DoExec()) {
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

		static::$oSet = $oSet_Objects;
		
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
			static::$oSet = new DBObjectSet($oFilter);

			// Test
			$oNewSet = DBObjectSet::FromScratch(static::$oSet->GetClass());

			// Process only once from DB.
			while($oObj = static::$oSet->Fetch()) {
				$oNewSet->AddObject($oObj);
			}

			static::$oSet = $oNewSet;



		}
		
	}


	/**
	 * Gets the iTop object set (currently being processed).
	 *
	 * @param bool Whether to rewind the dataset. Beware when there is iteration!
	 * 
	 * @return DBObjectSet|null
	 */
	public static function GetObjectSet(bool $bRewind) : DBObjectSet|null {

		if(static::$oSet !== null && $bRewind) {

			// In most cases, rewinding is advised anyway.
			static::$oSet->Rewind();

		}
		
		return static::$oSet;
		
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
	 * By default, it prints the output.  
	 * Some processors may disable printing the output, and keep it in memory.
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
	 * Sets whether the output should be suppressed (in this case it will be stored in the memory).  
	 * 
	 * A use case is that sometimes the report is not directly outputted on a web page, but for example sent by e-mail.
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
	 * Returns whether the output is being suppressed.
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

		static::Trace('Set optimized attribute codes: %1$s', json_encode($aOptimizedAttCodes, JSON_PRETTY_PRINT));

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


	/**
	 * Sets the value for a cache key.
	 *
	 * @param string $sKey
	 * @param mixed $value
	 * @return void
	 */
	public static function SetCache(string $sKey, $value) : void {

		static::$aCache[$sKey] = $value;

	}


	/**
	 * Returns the cached data.  
	 * If the key doesn't exist, it will return null.
	 *
	 * @param string $sKey
	 * @return mixed
	 */
	public static function GetCache(string $sKey) : mixed {

		return static::$aCache[$sKey] ?? null;

	}


	/**
	 * Returns the report data.
	 *
	 * @return object
	 */
	public static function GetData() : object|null {

		return static::$oReportData;

	}

}

