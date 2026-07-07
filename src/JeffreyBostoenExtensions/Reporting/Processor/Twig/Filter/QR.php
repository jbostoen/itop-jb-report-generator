<?php

/**
 * @copyright   Copyright (c) 2019-2025 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     3.2.250504
 */

namespace JeffreyBostoenExtensions\Reporting\Processor\Twig\Filter;

// Generic.
use Exception;

// chillerlan.
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Common\{MaskPattern, Version};
use chillerlan\QRCode\Output\QRGdImagePNG;

/**
 * Class QR. Adds a Twig filter to create QR codes.
 */
class QR extends Base {
    
    /**
     * @inheritDoc
     * 
     * The function takes a string as primary argument. 
     * The secondary argument is an array of options to override the default QR code options. See chillerlan/php-qrcode for this.
     * 
     */
    public function GetFunction(): callable {

        // Note: The function may need to start exposing options. Most probably, the version.
        // Alternative considerations: rely on a URL parameter, on iTop configuration, ...

        $oOptions = new QROptions([
            // QR code versions run from version 1 up to 40.
            // Each version adds 4 modules (the little black and white squares) per side.
            // E.g. version 5 can only hold roughly up to 106 alphanumeric characters with error correction level L.
            // Auto version is required for versionMin.
            'version' => 6,
            // The scale setting controls the pixel size of a single module (square) in the generated output image.
            // E.g. if version = 5 and EccLevel is L, it means a 37x37 module QR code will become a 111 x 111 pixel image.
            // Note: The library automatically adds a default quiet zone (margin) of 4 modules around the code.
            'scale' => 3,
            'eccLevel' => EccLevel::L,
            // 'maskPattern' => MaskPattern::AUTO,
            'outputInterface' => QRGdImagePNG::class,
        ]);

        
        return function (string $sString, array $aOptions = []) use ($oOptions) {
            
            // Suppress empty attributes.
            if($sString === '') {
                return '';
            }

            foreach($aOptions as $sOption => $mValue) {
                $oOptions->__set($sOption, $mValue);
            }

            // The QR code can only be initialized here with all the properties known.
            $oQRCode = new QRCode($oOptions);

            try {
                return sprintf('<!-- %2$s --><img class="qr" src="%1$s">', $oQRCode->render($sString), $sString);
            }
            catch(Exception $e) {
                return sprintf('<!-- Failed to create QR code for: %1$s -->', $sString);
            }
    
        };
        
    }

}
