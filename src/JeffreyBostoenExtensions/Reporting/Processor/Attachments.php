<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

use JeffreyBostoenExtensions\Reporting\Helper;

// iTop internals
use CMDBObjectSet;
use DBObjectSearch;
use DBObjectSet;
use ObjectResult;
use utils;


/**
 * Class Attachments. Only enriches the dataset with related attachments when needed.
 */
abstract class Attachments extends Base {
	
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
	public static function IsApplicable() : bool {
		
		// Always applicable when no action is specified.
		$sAction = utils::ReadParam('action', '', false, 'string');
		
		if(in_array($sAction, ['', 'download_pdf', 'show_pdf', 'attach_pdf'])) {
				
			// Does the file contain an indication of '.attachments' and the use of 'fields.contents' (.data, .mimetype, .filename)?
			$sFileName = Twig::GetReportFileName();
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
	public static function EnrichData(&$aReportData) : void {
		
		/** @var DBObjectSet|null $oSet_Objects iTop objects. */
		$oSet_Objects = Helper::GetObjectSet();

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
		$aObjResAttachments = Helper::ConvertDBObjectSetToObjectResultArray($oSet_Attachments);
		
		// In case of 'list':
		if(isset($aReportData['items']) == true) {

			/** @var ObjectResult $oObjRes */
			foreach($aReportData['items'] as $oObjRes) {
				
				// Attachments are linked to one object only.
				// So it's okay to just convert it here when needed.
				$oObjRes->attachments = array_filter($aObjResAttachments, function(ObjectResult $oObjResAtt) use ($oObjRes) {
					return $oObjResAtt->fields['item_id'] == $oObjRes->key;
				});
				
			}
		}

		// In case of 'details':
		elseif(isset($aReportData['item']) == true) {

			/** @var ObjectResult $oObjRes */
			$oObjRes = $aReportData['item'];

			// Attachments are linked to one object only.
			// So it's okay to just convert it here when needed.
			$oObjRes->attachments = array_filter($aObjResAttachments, function(ObjectResult $oObjResAtt) use ($oObjRes) {
				return $oObjResAtt->fields['item_id'] == $oObjRes->key;
			});

		}
	
	}
	
}
