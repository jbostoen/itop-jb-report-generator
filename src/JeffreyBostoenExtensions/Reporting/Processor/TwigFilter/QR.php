<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250213
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\TwigFilter;

// chillerlan.
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\{QRCode, QROptions};

abstract class QR extends Base {
    
    public static function GetFilterFunction(): callable {

        return function ($sString) {
            
            // Suppress empty attributes.
            if($sString == '') {
                return '';
            }

            $aOptions = new QROptions([
                'version'    => 5,
                'eccLevel'   => EccLevel::L,
                'outputType' => QROutputInterface::GDIMAGE_PNG,
                'scale'		 => 3 // Note: scale is for SVG, IMAGE_*. output. Irrelevant for HTML output; use CSS
            ]);

            // Invoke a fresh QRCode instance.
            $oQRCode = new QRCode($aOptions);

            // Dump the output .
            return '<img class="qr" src="'.$oQRCode->render($sString).'">';
    
        };
        
    }

}
