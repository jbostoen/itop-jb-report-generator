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
	public static function EnrichData() : void {
		
		$oReportData = Helper::GetData();

		/** @var DBObjectSet|null $oSet_Objects iTop objects. */
		$oSet_Objects = Helper::GetObjectSet(true);

		// Get keys to build one OQL Query
		$aKeys = [ -1];
		
		while($oObj = $oSet_Objects->Fetch()) {
			$aKeys[] = $oObj->GetKey();
		}
		
		// In case of 'list':
		if(property_exists($oReportData, 'items') && count($oReportData->items) > 0) {

			// First get a list of all the IDs.


			/** @var stdClass $oObjRes */
			
			$aObjIds = array_map(function($oObjRes) {
				return $oObjRes->key;
			}, $oReportData->items);

			$sObjClass = $oReportData->items[0]->class;
			$aObjResAttachments = static::GetAttachments($sObjClass, $aObjIds);

			/** @var stdClass $oObjRes */
			foreach($oReportData->items as $oObjRes) {
				
				// Attachments are linked to one object only.
				// So it's okay to just convert it here when needed.
				$oObjRes->attachments = array_filter($aObjResAttachments, function(ObjectResult $oObjResAtt) use ($oObjRes) {
					return $oObjResAtt->fields['item_id'] == $oObjRes->key;
				});
				
			}
		}

		// In case of 'details':
		elseif(property_exists($oReportData, 'item')) {

			/** @var stdClass $oObjRes */
			$oObjRes = $oReportData->item;

			// Attachments are linked to one object only.
			// So it's okay to just convert it here when needed.
			$oObjRes->attachments = static::GetAttachments($oObj->class, [ $oObj->key ]);

		}
	
	}


	/**
	 * Returns attachments for the given object IDs.
	 *
	 * @param string $sClass iTop class name.
	 * @param string[] $aObjIds
	 * @return ObjectResult[]
	 */
	private static function GetAttachments(string $sObjClass, array $aObjIds) : array {

		// Retrieve attachments.
		$oFilter_Attachments = new DBObjectSearch('Attachment');
		$oFilter_Attachments->AddCondition('item_id', $aObjIds, 'IN');
		$oFilter_Attachments->AddCondition('item_class', $sObjClass);
		$oSet_Attachments = new CMDBObjectSet($oFilter_Attachments);
		
		return Helper::ConvertDBObjectSetToObjectResultArray($oSet_Attachments);
		

	}
	
}
