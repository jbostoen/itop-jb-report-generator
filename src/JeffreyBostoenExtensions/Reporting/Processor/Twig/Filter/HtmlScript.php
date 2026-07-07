<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;

use JeffreyBostoenExtensions\Reporting\Helper;

// iTop.
use utils;

/**
 * Class HtmlScript. Adds a Twig filter named html_script that returns a HTML "script" tag, including a SHA-256 value for a specified file. (Subresource Integrity - SRI).
 */
class HtmlScript extends Base {

    /**
     * @inheritDoc
     */
    public function GetFunction() : callable {

        $callable = function($sLibName) {

            $sFQCN = 'JeffreyBostoenExtensions\\Reporting\\Processor\\FrontendLib\\'.$sLibName;

            Helper::Trace('Processing html_script "%1$s"', $sFQCN);

            // - Is the front-end library known?
            if(!class_exists($sFQCN)) {
                return sprintf('<!-- Unknown front-end library: %1$s -->', $sLibName);
            }

            // Get all the files.
            $sOutput = '';

            // JavaScript import map files.
                
                foreach($sFQCN::GetJSImportMapFiles() as $sAlias => $sRelativeFileName) {

                    $sFileName = APPROOT.'env-'.utils::GetCurrentEnvironment().'/'.$sRelativeFileName;

                    if(!file_exists($sFileName)) {
                        $sOutput .= sprintf('<!-- JS import map file does not exist: %1$s -->', $sRelativeFileName);
                        continue;
                    }
                    
                    $sTemplate = <<<JS
                        <script type="importmap"> 
                            {
                                "imports": {
                                    "%1\$s": "%2\$s"
                                },
                                "integrity": {
                                    "%2\$s": "sha256-%3\$s"
                                }
                            }
                        </script>
                    JS;
                    $sOutput .= sprintf($sTemplate,
                        $sAlias,
                        utils::GetAbsoluteUrlModulesRoot().'/'.$sRelativeFileName,
                        base64_encode(hash_file('sha256', $sFileName, true))
                    );

                }
                
            // JavaScript Module files.
            
                foreach($sFQCN::GetJSModuleFiles() as $sRelativeFileName) {

                    $sFileName = APPROOT.'env-'.utils::GetCurrentEnvironment().'/'.$sRelativeFileName;

                    if(!file_exists($sFileName)) {
                        $sOutput .= sprintf('<!-- JS module file does not exist: %1$s -->', $sRelativeFileName);
                        continue;
                    }
                    
                    $sHash = hash_file('sha256', $sFileName, true);
                    $sOutput .= sprintf('<script type="module" src="%1$s" integrity="sha256-%2$s"></script>'.PHP_EOL, 
                        utils::GetAbsoluteUrlModulesRoot().'/'.$sRelativeFileName,
                        base64_encode($sHash)
                    );

                }
                
            // - Classic JavaScript files.

                foreach($sFQCN::GetJSFiles() as $sRelativeFileName) {

                    $sFileName = APPROOT.'env-'.utils::GetCurrentEnvironment().'/'.$sRelativeFileName;

                    if(!file_exists($sFileName)) {
                        $sOutput .= sprintf('<!-- JS File does not exist: %1$s -->', $sRelativeFileName);
                        continue;
                    }
                    
                    $sHash = hash_file('sha256', $sFileName, true);
                    $sOutput .= sprintf('<script src="%1$s" integrity="sha256-%2$s"></script>'.PHP_EOL, 
                        utils::GetAbsoluteUrlModulesRoot().'/'.$sRelativeFileName,
                        base64_encode($sHash)
                    );

                }


            return $sOutput;
    
        };

        return $callable;

    }

}
