<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240425
 */

 namespace JeffreyBostoenExtensions\Reporting;

// Generic
use Exception;

// iTop internals
use DBObject;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use RestResultWithObjects;
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
	 * Returns an optimized set of attribute codes for the current filter.
	 *
	 * @return array
	 */
	public static function GetAttributesToOutputForFilter() : array {

		$aOptimizedAttCodes = static::GetOptimizedAttCodes();
		
		// Already specified by a processor?
		if(count($aOptimizedAttCodes) == 0) {

			$sClass = static::GetObjectSet()->GetClass();

			foreach(array_keys(MetaModel::ListAttributeDefs($sClass)) as $sAttCode) {
				$aOptimizedAttCodes[$sClass][] = $sAttCode;
			}

			static::$aOptimizedAttCodes = $aOptimizedAttCodes;

		}
		
		return $aOptimizedAttCodes;

	}
	
	/**
	 * Returns an array (similar to REST/JSON) from an iTop object set.
	 *
	 * @param DBObjectSet $oObjectSet iTop object set.
	 *
	 * @return array Each key is 'Class::ID' (the class being the common ancestor of the object set), with the value being an array (REST/JSON API structure).
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
	 * Returns array (similar to iTop REST/JSON) from object.
	 *
	 * @param DBObject $oObject iTop object.
	 *
	 * @return array REST/JSON API structure.
	 */
	public static function ObjectToArray(DBObject $oObject) : array {

		static::Trace('Convert %1$s to API RestResult.', $oObject::class.'::'.$oObject->GetKey());
		
		$aOptimizedAttCodes = static::GetAttributesToOutputForFilter();

		static::Trace('Optimized attribute codes: %1$s', json_encode($aOptimizedAttCodes, JSON_PRETTY_PRINT));

		$oResult = new RestResultWithObjects();
		$oResult->AddObject(0, '', $oObject, $aOptimizedAttCodes);
		
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
			usort($aReportProcessors, function($a, $b) {
				return $a::$iRank <=> $b::$iRank;
			});

			static::Trace('Processors: %1$s', implode(', ', $aReportProcessors));

		// - Before fetch allows optimizations.

			static::Trace('Processors: BeforeFetch().');

			foreach($aReportProcessors as $sClassName) {

				$sClassName::BeforeFetch();

			}

		// - Convert DBObjectSet to REST/JSON API array structure to use in templates.
		// Note: This performs the first fetch!
			
			static::Trace('Convert object(s) to REST/JSON structure.');

			if(static::$oSet_Objects !== null) {

				static::Trace('There are %1$s objects in the set.', static::$oSet_Objects->Count());

				if(count(static::$aOptimizedAttCodes) > 0) {
					static::Trace('Query optimization: %1$s', json_encode(static::$aOptimizedAttCodes));
				}
				
				$aSet_Objects = static::ObjectSetToArray(static::$oSet_Objects);
				
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

			// Expose some variables so they can be used in reports
			$aReportData = array_merge($aReportData, [
				'current_contact' => static::ObjectToArray(UserRights::GetUserObject()),
				'request' => $_REQUEST,
				'application' => [
					'url' => MetaModel::GetConfig()->Get('app_root_url'),
				]
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
	 * @return DBObjectSet
	 */
	public static function GetObjectSet() : DBObjectSet{
		
		// Rewind if there is a DBObjectSet.
		if(static::$oSet_Objects !== null) {
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

