<?php

/**
 * @copyright   Copyright (c) 2019-2024 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2.7.240425
 *
 * iTop module definition file
 */
 
SetupWebPage::AddModule(
        __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
        'jb-report-generator/2.7.240425',
        array(
                // Identification
                //
                'label' => 'Feature: report generator',
                'category' => 'business',

                // Setup
                //
                'dependencies' => array( 
                ),
                'mandatory' => false,
                'visible' => true,

                // Components
                //
                'datamodel' => array(
			// 'model.jb-report-generator.php',
			// 'vendor/autoload.php', Note: this Twig version is not compatible with iTop 3.0? So don't add this here, as it will crash the setup.
			'app/ReportHelper.php',
			'app/ApplicationExtension.php',
                ),
                'webservice' => array(

                ),
                'data.struct' => array(
                        // add your 'structure' definition XML files here,
                ),
                'data.sample' => array(
                        // add your sample data XML files here,
                ),

                // Documentation
                //
                'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
                'doc.more_information' => '', // hyperlink to more information, if any

                // Default settings
                //
                'settings' => array(
					// Module specific settings go here, if any
					// This is a demo configuration for a Windows system
					'browsershot' => array(
						'node_binary' => 'node.exe', // Directory with node binary is in an environmental variable
						'npm_binary' => 'npm.cmd', // Directory with NPM cmd file is in an environmental variable
						'chrome_path' => 'C:/progra~1/Google/Chrome/Application/chrome.exe', // Path including a Chrome browser executable
						'ignore_https_errors' => false, // Set to "true" if using invalid or self signed certificates
					)
                ),
        )
);

