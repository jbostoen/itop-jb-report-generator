<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250213
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

use JeffreyBostoenExtensions\Reporting\Helper;

// Generic
use Exception;

// iTop internals
use ApplicationContext;
use ApplicationException;
use DBObjectSet;
use Dict;
use stdClass;
use utils;

// chillerlan
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;

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
	public static function EnrichData(&$aReportData) : void {
		
		// @todo This extension was originally created for iTop 2.7. 
		// Since then, some methods are exposed natively in iTop 3.0
		
		// Enrich with common libraries.
		$sModuleUrl = utils::GetCurrentModuleUrl();

		$aReportData = array_merge_recursive($aReportData, [

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

			// Included common libraries.
			'lib' => [
				'bootstrap' => [
					'js' => $sModuleUrl.'/vendor/components/jquery/jquery.min.js',
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
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec($aReportData) : bool {
		
		try {
		
			$oReport = static::GetReportFromTwigTemplate($aReportData);
			
			Helper::SetHeader('Content-Type', $oReport->mimeType);
			Helper::AddOutput($oReport->content);
		
		}
		catch(Exception $e) {
			
			Helper::Trace('Twig DoExec() failed: %1$s', $e->getMessage());
			return false;

		}

		return true;
		
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
	 * @return string Filename
	 */
	public static function GetReportFileName() : string {
		
		$sView = Helper::GetView();

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
			$oSet_Objects = Helper::GetObjectSet();

			if($oSet_Objects !== null) {

				$sClassName = $oSet_Objects->GetClass();
			
				// Legacy behavior: Automatically build the report from the class name and provided 'view'.
				$sReportFileAlternative = sprintf('%1$s/reports/templates/%2$s/%3$s/%4$s',
					$sReportModuleDir,
					$sClassName,
					$sView,
					$sTemplateName
			)	;
				
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
	 * By design, that's all it does; as the content may be displayed immediately or used for other purposes (e.g. to convert to PDF).
	 *
	 * @param Array $aReportData Hashtable
	 *
	 * @return stdClass Properties: 'content' and 'mimeType' (suggested based on file extension).
	 */
	public static function GetReportFromTwigTemplate($aReportData = []) : stdClass {
		
		// If class doesn't exist, fail silently
		if(class_exists('\Twig\Loader\FilesystemLoader') == false) {
			throw new ApplicationException('The correct version of Twig does not seem to be configured or installed properly.');
		}
		
		$sReportFile = static::GetReportFileName();

		// - Generate report.
			
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

						$aOptions = new QROptions([
							'version'    => 5,
							'eccLevel'   => EccLevel::L,
							'outputType' => QROutputInterface::GDIMAGE_PNG,
							'scale'		 => 3 // Note: scale is for SVG, IMAGE_*. output. Irrelevant for HTML output; use CSS
						]);

						// Invoke a fresh QRCode instance.
						$oQRCode = new QRCode($aOptions);

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
		
		// - Mime type.
		
			// Set Content-Type header for these extensions.
			$aExtensionsToContentTypes = [
				'csv' => 'text/csv',
				'html' => 'text/html',
				'json' => 'application/json',
				'twig' => 'text/html',
				'txt' => 'text/plain',
				'xml' => 'text/xml'
			];
			
			// Check if known extension, set MIME Type.
			$sReportFileExtension = strtolower(pathinfo($sReportFile, PATHINFO_EXTENSION));
			if(isset($aExtensionsToContentTypes[$sReportFileExtension]) == true) {
				Helper::SetHeader('Content-Type', $aExtensionsToContentTypes[$sReportFileExtension]);
			}
			
		// - Build object.
		
			$oObject = new stdClass;
			$oObject->content = $sHTML;
			$oObject->mimeType = $aExtensionsToContentTypes[$sReportFileExtension] ?? '';

		// - Return.

		return $oObject;

		
	}
	
}
