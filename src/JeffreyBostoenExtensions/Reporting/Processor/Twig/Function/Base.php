<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Function;

use ReflectionClass;


/**
 * Class Base. An abstract class to extend. Leaf subclasses should be non-abstract. It can be used to introduce new Twig functions.
 */
abstract class Base {


	/**
	 * Returns the function.
	 *
	 * @return callable
	 */
    public function GetFunction(): callable {

        return function(){};

    }


	/**
	 * Returns the name.
	 *
	 * @return string
	 */
    public function GetName(): string {

        $sName = (new ReflectionClass(get_called_class()))->getShortName();

        // Camel case to snake.
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $sName));

    }

	/**
	 * Whether this function is applicable. By default, functions will be available!
	 *
	 * @return boolean
	 */
    public function IsApplicable() : bool {

        return true;
    }

}
