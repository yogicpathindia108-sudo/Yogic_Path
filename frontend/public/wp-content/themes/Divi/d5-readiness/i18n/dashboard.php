<?php
/**
 * D5 Readiness Translations for Divi Theme
 *
 * This file contains the translation strings for the Dashboard section
 * of the Divi theme. Each key-value pair consists of a string identifier
 * and its corresponding translated string.
 *
 * @package    Divi
 * @subpackage D5 Readiness / I18n
 * @since      ??
 */

// Translation strings for the Hero section.
return [
	'Dashboard'                                                => __( 'Dashboard', 'Divi' ),
	'Download Compatibility Report'                            => __( 'Download Compatibility Report', 'Divi' ),
	'Frequently Asked Questions'                               => __( 'Frequently Asked Questions', 'Divi' ),
	'What happens when I run the Divi 5 migrator?'             => __( 'What happens when I run the Divi 5 migrator?', 'Divi' ),
	'Migrator FAQ'                                             => __( 'The Divi 5 Migrator will parse all your website content and look for Divi 4 shortcodes. It will convert those shortcodes to the new Divi 5 format and update each post. Modules that do not yet support Divi 5, such as modules from the Divi Marketplace, will not be converted and will continue to function using the legacy Divi 4 framework. Pages that use such modules will not benefit from Divi 5\'s performance improvements.', 'Divi' ),
	'What if something breaks after I run the migrator?'       => __( 'What if something breaks after I run the migrator?', 'Divi' ),
	'Important backup'                                         => __( 'As with any major update, it\'s important that you back up your website and test the migration on a staging site. If something does go wrong and you forgot to back up, the Divi 5 Migrator automatically backs up your post content, and you can restore that content and roll back to Divi 4 if you choose.', 'Divi' ),
	'How do I report a bug?'                                   => __( 'How do I report a bug?', 'Divi' ),
	'We will handle bug'                                       => __( 'You can visit Elegant Themes and contact our support team using the pink chat icon. Give them details about the bug, and we\'ll handle it from there!', 'Divi' ),
	'Will Divi 5 work with modules from the Divi Marketplace?' => __( 'Will Divi 5 work with modules from the Divi Marketplace?', 'Divi' ),
	'Will not be ready'                                        => __( 'Creators from the marketplace are hard at work updating their modules for Divi 5. Most third-party modules won\'t be ready for Divi 5 right away. You can still use these modules with Divi 5. However, pages using legacy modules will not benefit from Divi 5\'s performance improvements and will be more prone to quirks. You can always return to the migrator and convert them later, once they are ready.', 'Divi' ),
	'Why am I getting an orange warning in the admin bar?'     => __( 'Why am I getting an orange warning in the admin bar?', 'Divi' ),
	'Warning explanation'                                      => __( 'You will see this warning if a page contains an un-converted Divi 4 module, of if one of your plugins uses the legacy Divi 4 framework. We built Divi 5 to be backwards compatible with Divi 4 plugins, such as modules from the Divi Marketplace. Until creators update their plugins for Divi 5, legacy plugins will continue to function using the legacy Divi 4 framework. However, loading the Divi 4 framework comes at a performance cost. The orange warning informs you that a plugin you are using is tapping into the legacy framework, and the page is not benefiting from Divi 5\'s performance improvements.', 'Divi' ),
	'Cancel'                                                   => __( 'Cancel', 'Divi' ),
	'Confirm'                                                  => __( 'Confirm', 'Divi' ),

	// Dot(s) in keys will look for nested values. Example: 'Divi 5.0 Dash' will look for 'Divi 5[0 Dash]'.
	'Divi 5 Migrator'                                          => __( 'Divi 5 Migrator', 'Divi' ),
];
