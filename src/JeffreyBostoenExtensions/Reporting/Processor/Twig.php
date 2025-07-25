<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

use JeffreyBostoenExtensions\Reporting\Helper;

// Generic.
use Exception;
use stdClass;

// iTop internals.
use ApplicationException;
use DBObjectSet;
use Dict;
use UserRights;
use utils;

// Twig.
use Twig\{Environment, TwigFilter, TwigFunction};
use Twig\Loader\FilesystemLoader;

/**
 * Class Twig.  
 * Renders a report with basic object details using Twig.
 */
abstract class Twig extends Base {
		
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() : bool {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');
		return ($sAction == '');
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec() : bool {
		
		try {
		
			$oReport = static::GetReportFromTwigTemplate();
			
			Helper::SetHeader('Content-Type', $oReport->sMimeType);
			Helper::AddOutput($oReport->sContent);
		
		}
		catch(Exception $e) {
			
			Helper::Trace('%1$s failed: %2$s', __METHOD__, $e->getMessage());
			return false;

		}

		// Reason to continue?
		// Save output somewhere?
		Helper::Trace('Rendered report.');
		return false;
		
	}
	
	/**
	 * Returns default filename of report.
	 * 
	 * Behavior:
	 * - A "template" parameter should specify the relative path to the module's directory ("reportdir" parameter).
	 * 
	 * Legacy behavior (deprecated):
	 * This expects the reports to be in the module's directory as '<moduleDir>/reports/templates/<className>/<viewType>/templateName.ext'; 
	 * where 
	 *  "className" is an iTop class and 
	 *  "viewType" is usually "details" or "list"
	 *  "templateName.ext" is free to choose
	 *
	 *
	 * @return string Filename
	 */
	public static function GetReportFileName() : string {
		

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
			$oSet_Objects = Helper::GetObjectSet(false);

			if($oSet_Objects !== null) {

				$sClassName = $oSet_Objects->GetClass();
			
				// Legacy behavior: Automatically build the report from the class name and provided 'view'.
				$sReportFileAlternative = sprintf('%1$s/reports/templates/%2$s/%3$s/%4$s',
					$sReportModuleDir,
					$sClassName,
					Helper::GetView(),
					$sTemplateName
				);
				
				if(file_exists($sReportFileAlternative) == true) {
					Helper::Trace('Deprecated: Legacy mode for file name: %1$s', $sReportFileAlternative);
					$sReportFile = $sReportFileAlternative;
				}
				else {
					Helper::Trace('Template does not exist: %1$s / Alternative: %2$s', $sReportFile, $sReportFileAlternative);
					throw new ApplicationException('Template does not exist.');
				}

			}

		}
		
		// Prevent local file inclusion
		// Mind: needs extra escaping!
		if(!preg_match('/^[A-Za-z0-9\-_\\\\\/\:]{1,}\.[A-Za-z0-9]{1,}$/', $sTemplateName)) {
			Helper::Trace('Potential local file inclusion: '.$sReportFile);
			throw new ApplicationException('Potential local file inclusion detected (LFI). This path is not allowed: "'.$sReportFile.'"');
		}
		
		$sReportFile = str_replace(APPROOT.'env-'.utils::GetCurrentEnvironment().'/', '', $sReportFile);
		
		return $sReportFile;
		
	}
	
	/**
	 * Returns content (HTML, XML, ...) of report.
	 * 
	 * By design, that's all it does; as the content may be displayed immediately or used for other purposes (e.g. to convert to PDF later).
	 *
	 * @return Report
	 */
	public static function GetReportFromTwigTemplate() : Report {
		
		$sBaseDir = APPROOT.'env-'.utils::GetCurrentEnvironment();
		$sReportFile = static::GetReportFileName();
		
		// - Generate report.
			
			// - Twig Loader.
			// Expose entire 'extensions' (env-xxx) directory so it's possible to include Twig templates
			// introduced by other iTop extensions (e.g. extra reports).
			
				$oLoader = new FilesystemLoader($sBaseDir);
			
			// - Twig environment options.
				
				$oTwigEnv = new Environment($oLoader, [
					'autoescape' => false,
					'cache' => false // No cache is default; but enforce!
				]);
								
			// - Add Twig filters & functions.
				
				Helper::Trace('Build list of Twig filters & functions.');

				foreach(['Filter', 'Function'] as $sType) {

					$sTwigClass = 'Twig\\Twig'.$sType;
					$sTwigMethod = 'add'.$sType;

					foreach(get_declared_classes() as $sClassName) {
						if(in_array('JeffreyBostoenExtensions\\Reporting\\Processor\Twig\\'.$sType.'\\iBase', class_implements($sClassName))) {
	
							$bApplicable = $sClassName::IsApplicable();
	
							Helper::Trace('Twig %1$s: %2$s , applicable = %3$s', $sType, $sClassName, $bApplicable ? 'yes' : 'no');
	
							if($bApplicable) {
	
								$oTwigEnv->$sTwigMethod(new $sTwigClass($sClassName::GetName(), $sClassName::GetFunction()));
								
							}
	
						}
					}

				}

			$oReportData = Helper::GetData();
			$sHTML = $oTwigEnv->render($sReportFile,  json_decode(json_encode($oReportData), true));
			
			// When using different environments (usually stored in $_SESSION but it can be called with switch_env), 
			// a more complete URL is needed for some renderers (e.g. ReportProcessorTwigToPDF)
			// Example of inline image: https://localhost:8182/iTop/web/pages/ajax.document.php?operation=download_inlineimage&id=12&s=8fb03e"
			$sNeedle = '/web/pages/ajax.document.php?operation=download_inlineimage';
			$sHTML = str_replace($sNeedle, $sNeedle.'&switch_env='.utils::GetCurrentEnvironment(), $sHTML);
		
		// - Mime type.
		
			// Set Content-Type header for these extensions if the MIME type is known for the file extension.
			$sReportFileExtension = strtolower(pathinfo($sReportFile, PATHINFO_EXTENSION));
			$sMimeType = match($sReportFileExtension) {
				'csv' => 'text/csv',
				'html' => 'text/html',
				'json' => 'application/json',
				'twig' => 'text/html',
				'txt' => 'text/plain',
				'xml' => 'text/xml',
				default => '',
			};
			
		// - Build object.
		
			$oReport = new Report($sHTML, $sMimeType);

		// - Return.

		return $oReport;

		
	}
	
}
