<?php
/**
 * Plugin Name: Penguin LDAP Authentication
 * Description:  Authenticate WordPress against active directory. Versioning uses major.minor.revision
 * All penguins will be authenticated.
 * Version: 1.1.2
 * Authors: Brandon Leigh-Bennett & Michael Corkum
 * Author URI: http://www.carleton.ca/webservices
 */

/**
 * @todo work on description
 * Explanation of plugin:
 * ---------------------
 *
 * The purpose of this plugin is to allow users to sign into their WordPress
 * website's backend using LDAP (Lightweight Directory Access Protocol) with 
 * active directory. A probable use for this plugin would be to provide a "single sign on"
 * functionality. This means that users can use the same singular username and password
 * combination that use for other services.
 *
 *
 * How the plugin works (High level explanation):
 * ---------------------------------------------
 *
 * This plugin will:
 * 1. Use the username and password fields when logging on to authenticate /
 * 		bind the user with ldap.
 * 2. If the authentication is successful, the plugin will log the user in
 */

/**
 * Note:
 * ----
 * $penguin_settings and $penguin_menu are different classes for organizational
 * purposes. $penguin_menu takes in $penguin_settings as a constructor in order to
 * call a singular method for penguin settings, load_all_options(), which loads all of the
 * saved settings from the wordpress database and assigns them to a variable in
 * $penguin_settings. This is done so that settings are only loaded in when the actual
 * rendering of the page takes place, and not when the user is browsing other pages
 * in the backend.
 *
 * $penguin_menu:
 * -------------
 * $penguin_menu implicitly calls wordpress rendering functions in $penguin_settings
 * that display out all the settings depending on what tab you are on.
 * These wordpress rendering functions (e.g. do_settings_sections()) look up the
 * associated functions that were actually set up in $penguin_settings.
 *
 * $penguin_settings:
 * -----------------
 * Basically, all of the setting up of settings and sections with
 * wordpress is done in $penguin_settings, while $penguin_menu handles the tabs and
 * the displaying of these settings.
 */
 
/**
 * Would be nice:
 * -------------
 * @todo separation of loading tabs ( i.e. not loading all options when on one tab)
 *
 * Other:
 * -------
 * @todo make sure environment works in multiple places
 * @todo 'isldap' meta data?
 * @todo get arround using suppress errors (@)? what to do when error happens?
 * @todo replace dies with throws?
 * @todo roles is used often enough in penguin_settings to warrant it being an object
 * property.
 *
 */

/* Entry point */
define ( 'DEBUG', FALSE );
define ( 'LOG_OUTPUT_FILE', 'directory/goes/here' );

require_once( 'class-penguin-settings.php' );
require_once( 'class-penguin-menu.php' );
require_once( 'class-penguin-login.php' );

/**
 * Handles the setting up, transfer, and display of wordpress settings.
 */
$penguin_settings = new Penguin_Settings();

/**
 * $penguin_menu handles the tabs and uses wordpress settings output functionality to
 * display the settings that were set up in $penguin_settings. Uses a
 * $penguin_settings method (load_all_options()) to prepare the $penguin_settings methods
 * for use. Uses wordpress functions like do_settings_sections() to invoke the
 * callback functions set up in $penguin_settings.
 */
$penguin_menu = new Penguin_Menu($penguin_settings);

/**
 * Handles AD-LDAP login functionality
 */
$penguin_login = new Penguin_Login($penguin_settings);

// True for now, but might want to make a setting?
$penguin_authentication = true;

if ( $penguin_authentication ) {
	add_filter( 'authenticate', 'penguin_authenticate_callback', 999, 3 );
}

function penguin_authenticate_callback ( $user, $username, $password ) {
	global $penguin_login;
	return $penguin_login->authenticate ( $user, $username, $password );
}

// When logged into the backend, this is the first hook to run.
add_action('admin_menu', 'penguin_menu_callback' );
function penguin_menu_callback () {
	//die('menu hook called first');
	global $penguin_menu;
	$penguin_menu->add_menu_hook();
}

// When logged into the backend, this is the second hook to run.
add_action( 'admin_init', 'penguin_init_callback' );
function penguin_init_callback () {
	//die('init hook calledfirst');
	global $penguin_settings, $penguin_menu;
	$penguin_menu->add_ajax_script();

	/**
	 * A valid question you may have might be: "Why is add_settings the
	 * *second* function to run? Wouldn't you want to add the settings *before* you use
	 * $penguin_menu?"
	 *
	 * Answer: penguin_menu_callback's only functionality is to add_menu_hook(), which
	 * creates another callback when the user clicks on the menu item. When the user is
	 * able to click on a menu item, add_settings will have already been run. To rephrase,
	 * add_settings() *does* occur before the relevant methods in $penguin_menu occur.
	 */
	$penguin_settings->add_settings();
}
?>