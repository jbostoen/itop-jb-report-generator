<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;

// iTop internals.
use Dict;

/**
 * Class MakeObjectUrl. Adds a Twig filter named dict_s that returns an iTop translation ( see iTop's Dict::S() )
 */
abstract class DictS extends Base {

    /**
     * @inheritDoc
     */
    public static function GetFunction() : callable {

        $callable = function($sStringCode, $sDefault = null, $bUserLanguageOnly = false) {
            return Dict::S($sStringCode, $sDefault, $bUserLanguageOnly);
        };

        return $callable;

    }

}
