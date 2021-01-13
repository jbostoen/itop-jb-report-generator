<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-04-09 16:58:14
 *
 * Definition of class RTParent. Parent Report Tool (RT) to expand upon.
 */

namespace jb_itop_extensions\report_generator;

use \Exception;

// Generic
use \ReflectionClass;

// iTop internals
use \ApplicationException;
use \Dict;
use \NiceWebPage;
use \utils;

/**
 * Main class which can be used as a parent, so some properties are automatically inherited
 */
abstract class RTParent {
	
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
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable() {
		
		// This parent class should not be applicable.
		return false;
		
	}
	
	/**
	 * Rendering hook. Can enrich report data (fetching additional info).
	 *
	 * @var \Array $aReportData Report data
	 * @var \CMDBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 *
	 * @return void
	 */
	public static function EnrichData(&$aReportData, $oSet_Objects) {
		
		// Enrich data
		
	}
	
	/**
	 * Action hook
	 *
	 * @var \Array $aReportData Report data
	 * @var \CMDBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 *
	 * @return void
	 */
	public static function DoExec($aReportData, $oSet_objects) {
		
		// Do stuff
		
	}
	
	/**
	 * Outputs error (from Exception)
	 *
	 * @var \Exception $e Exception
	 *
	 * @return void
	 */
	public static function OutputError(Exception $e) {
		
		require_once(APPROOT.'/application/nicewebpage.class.inc.php');
		$oP = new NiceWebPage(\Dict::S('UI:PageTitle:FatalError'));
		$oP->add("<h1>".Dict::S('UI:FatalErrorMessage')."</h1>\n");	
		$oP->add(Dict::Format('UI:Error_Details', $e->getMessage()));	
		$oP->output();
		die();
		
	}
	
}


/**
 * Class ReportToolTwig. Renders a report wit hbasic object details using Twig.
 */
abstract class RTTwig extends RTParent implements iReportTool {
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable() {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');		
		return ($sAction == '');
		
	}
	
	/**
	 * Rendering hook
	 *
	 * @var \Array $aReportData Report data
	 * @var \CMDBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function EnrichData(&$aReportData, $oSet_Objects) {
		
		// Enrich data with iTop setting (remove trailing /)
		$aReportData['itop']['root_url'] = substr(utils::GetAbsoluteUrlAppRoot(), 0, -1);
		
	}
	
	/**
	 * Action hook
	 *
	 * @var \Array $aReportData Report data
	 * @var \CMDBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function DoExec($aReportData, $oSet_Objects) {
		
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
	 * Returns default filename of report
	 *
	 * @return \String Filename
	 */
	public static function GetReportFileName() {
		

		$sClassName = utils::ReadParam('class', '', false, 'class');
		$sType = utils::ReadParam('type', '', false, 'string');
		$sTemplateName = utils::ReadParam('template', '', false, 'string');
		$sReport = 'jb_itop_extensions\\report_generator\\'.utils::ReadParam('report', '', false, 'string');
		
		// 'class' and 'type' were already checked		
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
		
		return $sReportFile;
		
	}
	
	/**
	 * Returns content (HTML, XML, ...) of report
	 *
	 * @var \Array $aReportData Hashtable
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
		$loader = new \Twig\Loader\FilesystemLoader(dirname($sReportFile));
		
		// Twig environment options
		$oTwigEnv = new \Twig\Environment($loader, [
			'autoescape' => false
		]); 

		// Combodo uses this filter, so let's use it the same way for our report generator
		$oTwigEnv->addFilter(new \Twig\TwigFilter('dict_s', function ($sStringCode, $sDefault = null, $bUserLanguageOnly = false) {
				return Dict::S($sStringCode, $sDefault, $bUserLanguageOnly);
			})
		);
		
		// Relies on chillerlan/php-qrcode; optionally.
		if(class_exists('chillerlan\QRCode\QRCode') == true) {
			
			$oTwigEnv->addFilter(new \Twig\TwigFilter('qr', function ($sString) {

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
		
		return $oTwigEnv->render(basename($sReportFile), $aReportData);
		
	}
	
}

/**
 * Class ReportToolPDF. Parent class for iReportTool which creates PDF.
 */
abstract class RTPDF extends RTTwig implements iReportTool {
	
	/**
	 * Whether or not this extension is applicable
	 *
	 * @return \Boolean
	 *
	 */
	public static function IsApplicable() {
		
		// Generic, so no.
		$sAction = utils::ReadParam('action', '', false, 'string');
		return (in_array($sAction, ['download_pdf', 'show_pdf']) == true);
		
	}
	
	/**
	 * Action hook on rendering the entire template
	 *
	 * @var \Array $aReportData Report data
	 * @var \CMDBObjectSet[] $oSet_Objects CMDBObjectSet of iTop objects which are being processed
	 *
	 */
	public static function DoExec($aReportData, $oSet_Objects) {
		
		// If class doesn't exist, fail silently
		if(class_exists('\mikehaertl\wkhtmlto\Pdf') == false) {
			throw new ApplicationException('wkhtml seems not to be configured or installed properly.');
		}
		
		try {
		
			/** @var \mikeheartl\wkhtmlto\Pdf $oPDF PDF Object */
			$oPDF = self::GetPDFObject($aReportData);			
			
			// Simply output
			

			// It will be called downloaded.pdf and offered as a download with this header
			// header("Content-Disposition:attachment;filename=downloaded.pdf");
			/*
				if(!$oPDF->saveAs('test.pdf')) {
					echo $oPDF->getError();
				}
			*/
			
			$sAction = utils::ReadParam('action', '', false, 'string');
		
			switch($sAction) {
				case 'show_pdf':
					header('Content-type:application/pdf');
					break;
				
				case 'download_pdf':
					header('Content-type:application/pdf');
					header("Content-Disposition:attachment;filename=downloaded.pdf");
					break;
					
				default:
					// Unexpected
			}
			
			if(!$oPDF->send()) {
				echo $oPDF->getError();
			}			
				
		}
		catch(Exception $e) {
			self::OutputError($e);
		}
		
	}
	
	/**
	 * Get PDF object based on report data.
	 *
	 * @var \Array $aReportData Hashtable
	 *
	 * @return \mikehaertl\wkhtmlto\Pdf PDF Object
	 */
	public static function GetPDFObject($aReportData) {
		
		// If class doesn't exist, fail silently
		if(class_exists('\mikehaertl\wkhtmlto\Pdf') == false) {
			throw new ApplicationException('mikehaertl/phpwkhtmltopdf library seems to be missing.');
		}
		
		try {
		
			// Get HTML for this report
			$sHTML = self::GetReportFromTwigTemplate($aReportData);
			
			// TCPPDF was expected to change in iTop 2.7; wkhtml offers more options.
			// However, wkhtmltopdf (stable = 0.12.5) does NOT support flex (uses older webkit version) 
			// Limited changes required: .row -> display: -webkit-box;
			$oPDF = new \mikehaertl\wkhtmlto\Pdf();
			
			// For cross instances, allow settings to be defined in config-itop.php
			$aOptions = utils::GetCurrentModuleSetting('extra_wkhtml', []);
			
			// Some options can also be set as: $oPDF->binary = 'C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe';
			$oPDF->setOptions($aOptions);
			$oPDF->addPage($sHTML);

			return $oPDF;
				
		}
		catch(Exception $e) {
			self::OutputError($e);
		}
		
	}
	
}
