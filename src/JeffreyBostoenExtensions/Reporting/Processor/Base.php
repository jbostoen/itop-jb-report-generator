<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor;

/**
 * Interface iBase.
 * Implement this interface to enrich data or perform other actions, e.g. to show a HTML report or generate a PDF file.
 */
interface iBase {
	
	/**
	 * Whether or not this report processor is applicable. By default, a processor is NOT applicable!
	 *
	 * @return bool
	 */
	public static function IsApplicable() : bool;

	/**
	 * This method is executed before the data is fetched (queried).  
	 * A common use case is to optimize the columns that need to be fetched.
	 *
	 * @return void
	 */
	public static function BeforeFetch() : void;
	
	/**
	 * After the data is enriched; the report processor is executed.  
	 * The last report processor should have output. 
	 * 
	 * This should return 'false' if any other following processors should be skipped.
	 *
	 * @return bool
	 *
	 */
	public static function DoExec() : bool;

	/**
	 * After querying, the data can be enriched.  
	 * 
	 * For example: calculations can be done, extra info can be added, ...
	 * 
	 * This method is only meant for data manipulation; NOT for output.
	 * 
	 * @return void
	 *
	 */
	public static function EnrichData() : void;
	

	/**
	 * Returns the rank (lower = executed first).
	 *
	 * @return int
	 */
	public static function GetRank(): int;

}


/**
 * Class Base. A class that implements the iBase interface. This base class can used as a parent for any other processors.
 */
abstract class Base implements iBase {
	
	/**
	 * @var int $iRank Rank. Lower number = goes first.
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
	public static function BeforeFetch() : void {

		
	}

	/**
	 * @inheritDoc
	 */
	public static function IsApplicable() : bool {
		
		return false;
		
	}

	/**
	 * @inheritDoc
	 */
	public static function EnrichData() : void {
		
		
	}
	
	/**
	 * @inheritDoc
	 */
	public static function DoExec() : bool {
		
		return true;
		
	}

	/**
	 * @inheritDoc
	 */
	public static function GetRank() : int {

		return static::$iRank;

	}
	
}
