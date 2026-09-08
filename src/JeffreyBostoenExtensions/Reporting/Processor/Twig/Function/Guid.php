<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Function;

/**
 * Class Guid. Provides a Twig filter to return a 32 character GUID.
 */
class Guid extends Base {
    
    /**
     * @inheritDoc
     */
    public function GetFunction(): callable {

        return function () : string {
            
            return bin2hex(random_bytes(16));
    
        };
        
    }

}
