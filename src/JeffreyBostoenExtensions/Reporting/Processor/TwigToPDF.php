<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

use JeffreyBostoenExtensions\Reporting\Helper;

// Generic
use Exception;

// iTop internals
use ApplicationException;
use Attachment;
use DBObject;
use DBObjectSet;
use iTopStandardURLMaker;
use MetaModel;
use ormDocument;
use UserRights;
use utils;

// spatie
use Spatie\Browsershot\Browsershot;

/**
 * Class TwigToPDF. Generate PDF documents from Twig reports.
 */
abstract class TwigToPDF extends Twig {
	
	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() : bool {
		
		$sAction = utils::ReadParam('action', '', false, 'string');
		return (in_array($sAction, ['download_pdf', 'show_pdf', 'attach_pdf']) == true);
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec() : bool {
		
		try {

			$oReportData = Helper::GetData();

			if(property_exists($oReportData, 'item')) {

				$oItem = $oReportData->item;
				$sFileName = date('Ymd_His').'_'.$oItem->class.'_'.$oItem->key.'.pdf';

			}
			elseif(property_exists($oReportData, 'items')) {

				$oItem = $oReportData->items[0];
				$sFileName = date('Ymd_His').'_'.$oItem->class.'_list.pdf';

			}
			
			/** @var Spatie\Browsershot\Browsershot $oPDF PDF Object */
			$sBase64 = static::GetPDFObject();
			$sPDF = base64_decode($sBase64);
			
			$sAction = utils::ReadParam('action', '', false, 'string');

		
			switch($sAction) {
				case 'show_pdf':
				case 'download_pdf':
					
					Helper::SetHeader('Content-Type', 'application/pdf');
					Helper::SetHeader('Content-Disposition', ($sAction == 'show_pdf' ? 'inline' : 'attachment').';filename='.$sFileName);
					Helper::AddOutput($sPDF);
					break;
					
				case 'attach_pdf':
				
					static::AttachToHostObject($sPDF, $sFileName, $oItem->class, $oItem->key);
					
					// Go back.
					$oUrlMaker = new iTopStandardURLMaker();
					$sUrl = $oUrlMaker->MakeObjectURL($oItem->class, $oItem->key);
					
					Helper::SetHeader('Location', $sUrl);
					break;
					
					
				default:
					// Unexpected
			}
			
				
		}
		catch(Exception $e) {

			Helper::Trace('%1$s failed: %2$s', __METHOD__, $e->getMessage());
			return false;

		}

		Helper::Trace('Rendered PDF report.');
		return false;
		
	}

	/**
	 * Attaches the resulting PDF to the given host object.
	 *
	 * @param string $sPDF
	 * @param string $sFileName
	 * @param string $sObjClass
	 * @param int $iObjId
	 * 
	 * @return Attachment
	 * 
	 * @details
	 * This is split into a standalone function, so it's easy to extend or override.
	 */
	public static function AttachToHostObject(string $sPDF, string $sFileName, string $sObjClass, int $iObjId) : Attachment {

		// Create attachment.
		/** @var Attachment $oAttachment */
		$oAttachment = MetaModel::NewObject('Attachment', [
			'user_id' => UserRights::GetUserId(),
			'item_class' => $sObjClass,
			'item_id' => $iObjId,
			'creation_date' => date('Y-m-d H:i:s'),
			'contents' => new ormDocument($sPDF, 'application/pdf', $sFileName)
		]);
		$oAttachment->DBInsert();

		return $oAttachment;

	}
	
	/**
	 * Get PDF object based on report data.
	 *
	 * @param array $aReportData Hashtable
	 *
	 * @return string
	 */
	public static function GetPDFObject() : string {
		
		// 
		try {
			
			// The default mode is 'browsershot'.
			$sMode = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'pdf_renderer', 'browsershot');
			Helper::Trace('Mode = %1$s', $sMode);

			// Get HTML for this report.
			$sHTML = static::GetReportFromTwigTemplate()->sContent;

			if($sMode == 'browsershot') {
			
				// If class doesn't exist, this should fail.
				if(class_exists('\Spatie\Browsershot\Browsershot') == false) {
					throw new ApplicationException('PHP Library \Spatie\BrowserShot\BrowserShot seems not to be configured or installed properly.');
				}
			
				
				$aBrowserShotSettings = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'browsershot', [
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
					
				$sData = $oBrowsershot->base64pdf();
					
			}
			elseif($sMode == 'external') {
				
				$aExternalRendererSettings = MetaModel::GetModuleSetting(Helper::MODULE_CODE, 'pdf_external_renderer', []);
				$aExternalRendererSettings = array_merge([
					'url' => '',
					'skip_certificate_check' => false
				], $aExternalRendererSettings);
			
				$sProxyUrl = $aExternalRendererSettings['url'];
				
				if($sProxyUrl == '') {
					throw new Exception('No URL specified (pdf_external_renderer_url section).');
				}
				
				// Post data as JSON.
				$ch = curl_init($sProxyUrl);
				
				// Create payload.
				$sPayload = json_encode(['data' => $sHTML]);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $sPayload);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', 'Accept:application/json']);
				curl_setopt($ch, CURLOPT_TIMEOUT, 60 * 5);

				// Return response instead of printing.
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				
				// Possibility to allow self-signed certificates etc.
				if($aExternalRendererSettings['skip_certificate_check'] == true) {
					
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

				}
				
				// Send request.
				$response = curl_exec($ch);
				
				$iError = curl_errno($ch);
				$sErrorMsg = curl_error($ch);

				// Attempt to catch errors. E.g. timeout, or port blocked, ...
				if($iError) {

					$sErrorMsg = match ($iError) {
						CURLE_OPERATION_TIMEOUTED => 'The request timed out.',
						CURLE_COULDNT_CONNECT => 'Could not connect. Connection possibly blocked or DNS issue.',
						CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve host. Most likely a DNS issue.',
						default => $sErrorMsg // Return original message.
					};
					
					// For all other cases, including the above:
					Helper::Trace('cURL error, code: %1$s, error message: %2$s', $iError, $sErrorMsg);
					throw new Exception(sprintf('cURL error, code: %1$s, error message: %2$s', $iError, $sErrorMsg));
					
				}
				
				// Attempt to catch HTTP status codes.
				$iHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				
				if($iHttpStatus != 200) {
					Helper::Trace('Invalid HTTP response code: %1$s, error: %2$s', $iHttpStatus, curl_error($ch));
					throw new Exception('Invalid HTTP response code: '.$iHttpStatus.', '.curl_error($ch));
				}
				
				curl_close($ch);
				
				// Process response.
				$oData = json_decode($response);
				
				if(json_last_error() !== JSON_ERROR_NONE) {
					throw new Exception('Invalid JSON structure: '.$response);
				}
				
				if($oData->error != 0) {
					throw new Exception('Failed to render PDF. Error code: '.$oData->error.', message: '.$oData->message);
				}
				
				$sData = $oData->pdf;
				
			}
			else {

				throw new Exception('Unknown mode: '.$sMode);

			}

			return $sData;

		}
		catch(Exception $e) {

			Helper::Trace('TwigToPDF GetPDFObject() failed: %1$s', $e->getMessage());
			throw new Exception('Unable to generate PDF.');

		}
		
	}
	
}
