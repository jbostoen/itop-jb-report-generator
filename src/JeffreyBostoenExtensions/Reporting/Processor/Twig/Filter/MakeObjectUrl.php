<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;


// iTop.
use ApplicationContext;

// Generic.
use stdClass;

/**
 * Class MakeObjectUrl. 
 * 
 * Adds a Twig filter named make_object_url . 
 * 
 * - An iTop object (stdClass).
 * - A string (referring to an iTop class) and an iTop object ID.
 * 
 * 
 */
class MakeObjectUrl extends Base {

    /**
     * @inheritDoc
     */
    public function GetFunction() : callable {

        $callable = function(stdClass|string $mValue, string $sId = '') : string {

            if(is_object($mValue)) {
            
                return ApplicationContext::MakeObjectUrl($mValue->class, $mValue->key, null, false);

            }
            else {

                // This is the classic mode, and still present to allow creating URLs for e.g. external keys.
                // E.g. user request > caller_id :
                // 'Person'|make_object_url(item.fields.caller_id)
                return ApplicationContext::MakeObjectUrl($mValue, $sId, null, false);
        
            }

        };

        return $callable;

    }

}
