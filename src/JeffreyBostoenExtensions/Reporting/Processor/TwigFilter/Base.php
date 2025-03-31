<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250213
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\TwigFilter;

use ReflectionClass;

/**
 * Interface iBase. An interface that can be used to register new Twig filters for the Report Generator.
 */
interface iBase {

	/**
	 * Whether or not this filter is applicable. By default, filters will be available!
	 *
	 * @return boolean
	 */
	public static function IsApplicable() : bool;

	/**
	 * Returns the name of the filter.
	 *
	 * @return string
	 */
	public static function GetFilterName() : string;


	/**
	 * Returns the function of the filter.
	 *
	 * @return callable
	 */
	public static function GetFilterFunction() : callable;

}
/**
 * Class Base. A class that implements the iBase interface. This base class can used as a parent for any other Twig filters.
 */
abstract class Base implements iBase {

    /**
     * @inheritDoc
     */
    public static function GetFilterFunction(): callable {

        return function(){};

    }

    /**
     * @inheritDoc
     */
    public static function GetFilterName(): string {

        $sName = (new ReflectionClass(get_called_class()))->getShortName();

        // Camel case to snake.
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $sName));

    }


    /**
     * @inheritDoc
     */
    public static function IsApplicable() : bool {

        return !empty(class_parents(get_called_class()));

    }

}
