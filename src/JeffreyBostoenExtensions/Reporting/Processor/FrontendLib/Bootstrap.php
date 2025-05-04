<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\FrontendLib;

use JeffreyBostoenExtensions\Reporting\Helper;

/**
 * Class Bootstrap. Used to add Twitter Bootstrap as a front-end library.
 */
abstract class Bootstrap extends Base {

    /**
     * @inheritDoc
     */
    public static function GetCSSFiles(): array {

        return [
            Helper::MODULE_CODE.'/vendor/twbs/bootstrap/dist/css/bootstrap.min.css'
        ];

    }

    /**
     * @inheritDoc
     */
    public static function GetJSFiles(): array {
        
        return [
            Helper::MODULE_CODE.'/vendor/twbs/bootstrap/dist/js/bootstrap.min.js'
        ];

    }

}
