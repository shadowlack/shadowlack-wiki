<?php
/**
 * XenForo for MediaWiki.
 *
 * Integrates XenForo authentication into MediaWiki.
 */

if (!defined('MEDIAWIKI')) {
    // trying to be smart?
    die('Nothing here, move on');
}

$wgExtensionCredits['other'][] = array(
   'path' => __FILE__,
   'name' => 'Xenforo Bridge',
   'author' => array( 'xfrocks, Zelkova', ),
   'version' => '1.0',
   'url' => 'https://xenforo.com/community/resources/mediawiki-bridge.4535/',
   'description' => 'Bridges Xenforo accounts and user groups with Mediawiki
',
);

require(dirname(__FILE__) . '/setup.php');
mwxf_setup();