<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;


// iTop internals.
use ApplicationContext;

/**
 * Class MakeObjectUrl. Adds a Twig filter named make_object_url that returns an object URL from a class and object ID.
 */
abstract class MakeObjectUrl extends Base {

    /**
     * @inheritDoc
     */
    public static function GetFunction() : callable {

        $callable = function($sObjClass, $sObjKey) {

            return ApplicationContext::MakeObjectUrl($sObjClass, $sObjKey, null, false);
        };

        return $callable;

    }

}
