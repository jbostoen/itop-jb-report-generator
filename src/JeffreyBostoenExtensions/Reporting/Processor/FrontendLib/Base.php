<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\FrontendLib;

use Override;

/**
 * Interface iBase. An interface that can be used to register new **locally hosted** front-end libraries for the Report Generator.
 */
interface iBase {

	/**
	 * Returns one or more CSS files that should be included. The paths are relative to the env-* folder.
	 *
	 * @return string[]
	 */
	public static function GetCSSFiles() : array;


	/**
	 * Returns one or more JavaScript files that should be included. The paths are relative to the env-* folder.
	 *
	 * @return string[]
	 */
	public static function GetJSFiles() : array;

    
	/**
	 * Returns one or more JavaScript module files that should be included. The paths are relative to the env-* folder.
	 *
	 * @return string[]
	 */
	public static function GetJSModuleFiles() : array;


    /**
     * Returns one or more JavaScript (module) files that should be included. The paths are relative to the env-* folder.  
     * 
     * - Key: The alias name, e.g. ReportGeneratorPro.
     * - Value: The link.
     *
     * @return array
     */
    public static function GetJSImportMapFiles() : array;

}


/**
 * Class Base. Use this base class as a parent for any other front-end libraries. 
 * 
 * Hint: This class is mostly meant for the Twig filters that can generate the "script" and "link" tags.
 */
abstract class Base implements iBase {

    /**
     * @inheritDoc
     */
    public static function GetCSSFiles(): array {

        return [];
    }


    /**
     * @inheritDoc
     */
    public static function GetJSFiles(): array {

        return [];
    }


    /**
     * @inheritDoc
     */
    public static function GetJSModuleFiles(): array {

        return [];
    }


    /**
     * @inheritDoc
     */
    public static function GetJSImportMapFiles(): array {

        return [];

    }


}
