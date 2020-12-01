<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-04-09 16:58:14
 *
 * iTop module definition file
 */
 
SetupWebPage::AddModule(
        __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
        'jb-report-generator/2.6.200409',
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
					'app/core/applicationextension.class.inc.php',
					'app/common/reporthelper.class.inc.php',
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
					// This is a demo configuration for a Windows system, usingn wkhtmltopdf 0.12
					'extra_wkhtml' => array(
						// On some systems you may have to set the path to the wkhtmltopdf executable
						'binary' => 'C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe',
						'ignoreWarnings' => false,
						// 'tmpDir' => 'B:/temp',
						'commandOptions' => array(
							'useExec' => true, // Can help on Windows systems
							'procEnv' => array(
								// Check the output of 'locale -a' on your system to find supported languages
								'LANG' => 'en_US.utf-8',
							),
						),
						
						// 'no-outline', // Make Chrome not complain
						'margin-top'    => 10,
						'margin-right'  => 10,
						'margin-bottom' => 10,
						'margin-left'   => 10,
						
						// HTTP credentials
						// 'username' => 'user',
						// 'password' => 'password',
						
					)
                ),
        )
);

