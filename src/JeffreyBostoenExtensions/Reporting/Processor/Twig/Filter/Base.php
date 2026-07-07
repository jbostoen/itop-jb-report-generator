<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;

use ReflectionClass;

/**
 * Class Base. This abstract base class can used as a parent for any Twig filters.
 */
abstract class Base {

	/**
	 * Returns the function of the filter.
	 *
	 * @return callable
	 */
    public function GetFunction(): callable {

        return function(){};

    }

	/**
	 * Returns the name of the filter.
	 *
	 * @return string
	 */
    public function GetName(): string {

        $sName = (new ReflectionClass(get_called_class()))->getShortName();

        // Camel case to snake.
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $sName));

    }


	/**
	 * Whether this filter is applicable. By default, filters will be available!
	 *
	 * @return boolean
	 */
    public function IsApplicable() : bool {

        return true;

    }

}
