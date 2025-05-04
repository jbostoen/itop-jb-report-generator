<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\FrontendLib;

use JeffreyBostoenExtensions\Reporting\Helper;

/**
 * Class FontAwesome. Used to add FontAwesome as a front-end library.
 */
abstract class FontAwesome extends Base {

    /**
     * @inheritDoc
     */
    public static function GetCSSFiles(): array {

        return [
            Helper::MODULE_CODE.'/vendor/components/font-awesome/css/all.min.css'
        ];

    }

}
