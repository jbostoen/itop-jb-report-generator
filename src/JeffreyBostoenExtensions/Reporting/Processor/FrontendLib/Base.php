<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\FrontendLib;

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

}
/**
 * Class Base. A class that implements the iBase interface. This base class can used as a parent for any other front-end libraries. 
 * 
 * Hint: most useful for the Twig filters that can generate the "script" and "link" tags.
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

}
