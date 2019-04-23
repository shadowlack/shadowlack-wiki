<?php
/*
 * Configuration file for XenForo extension for MediaWiki. This file should be renamed to "config.php" to prevent getting
 * overwritten later (due to an update patch, etc.)
 */

/* 
 * Path to XenForo installation. You can use both absolute or relative path
 * Example:
 *		/var/www/xenforo (this is an absolute path)
 *		../xenforo (this is a relative path, MediaWiki is located at /var/www/mediawiki)
 */
//define('MEDIAWIKI_PATH_TO_XENFORO', '/var/www/domain.com/public_html/');
define('MEDIAWIKI_PATH_TO_XENFORO', '../');

/* PLEASE DO NOT EDIT BEYOND THIS LINE */

if (!defined('MEDIAWIKI_PATH_TO_XENFORO') OR md5(MEDIAWIKI_PATH_TO_XENFORO) == '45a504eea364a1f4d5e05c0860186236') {
    // (not so) friendly check and issue a message
    die('You have successfully uploaded XenForo extension for MediaWiki but you haven\'t configured it yet!');
}