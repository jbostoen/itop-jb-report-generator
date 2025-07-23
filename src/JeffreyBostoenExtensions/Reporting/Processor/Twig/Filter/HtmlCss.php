<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;

// iTop internals.
use utils;

/**
 * Class HtmlCss. Adds a Twig filter named html_css that returns a HTML "link" tag.
 * 
 * @deprecated Do not use yet.
 */
abstract class HtmlCss extends Base {

    /**
     * @inheritDoc
     */
    public static function GetFunction() : callable {

        $callable = function($sLibName) {

            $sFQCN = 'JeffreyBostoenExtensions\\Reporting\\Processor\\FrontendLib\\'.$sLibName;

            // Is the front-end library known?
            if(!class_exists($sFQCN)) {
                return '<!-- Unknown front-end library: '.$sLibName.' -->';
            }

            // Get all the files.
            $sOutput = '';

            foreach($sFQCN::GetCSSFiles() as $sRelativeFileName) {

                $sFileName = APPROOT.'env-'.utils::GetCurrentEnvironment().'/'.$sRelativeFileName;

                if(!file_exists($sFileName)) {
                    $sOutput .= '<!-- File does not exist: '.$sRelativeFileName.' -->';
                    continue;
                }
                
                $sOutput .= sprintf('<link rel="stylesheet" href="%1$s">'.PHP_EOL, 
                    utils::GetAbsoluteUrlModulesRoot().'/'.$sRelativeFileName
                );

            }

            return $sOutput;
    
        };

        return $callable;

    }

}
