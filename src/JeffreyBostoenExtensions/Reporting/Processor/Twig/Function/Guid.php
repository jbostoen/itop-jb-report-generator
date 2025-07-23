<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Function;

abstract class Guid extends Base {
    
    public static function GetFunction(): callable {

        return function () {
            
            return bin2hex(random_bytes(10));
    
        };
        
    }

}
