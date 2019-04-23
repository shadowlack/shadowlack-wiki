<?php

class MWXF_Setup
{
    public static function bootstrap()
    {
        global $wgAuth;
        global $wgXenForoAuthPlugin;
        global $wgVersion;

        $oldVersion = version_compare($wgVersion, '1.21', '<=');
        if ($oldVersion) {
            die('XenForo extension requires MediaWiki v1.21+.');
        }

        if (file_exists(dirname(__FILE__) . '/config.php')) {
            require(dirname(__FILE__) . '/config.php');
        } else {
            require(dirname(__FILE__) . '/config.default.php');
        }
        require(dirname(__FILE__) . '/authplugin.php');
        require(dirname(__FILE__) . '/hooks.php');

        $wgXenForoAuthPlugin = new MWXF_AuthPlugin();

        $wgXenForoAuthPlugin->setOldWgAuth($wgAuth);
        $wgAuth = $wgXenForoAuthPlugin;

        return true;
    }

    public static function initXenForo()
    {
        if (!defined('MEDIAWIKI_PATH_TO_XENFORO')) {
            // no path to XenForo, abort mission!
            return;
        }

        $paths = array(MEDIAWIKI_PATH_TO_XENFORO, $GLOBALS['IP'] . '/' . MEDIAWIKI_PATH_TO_XENFORO);
        $xfPath = false;
        foreach ($paths as $pathCandidate) {
            if (is_dir($pathCandidate) AND file_exists($pathCandidate . '/library/XenForo/Application.php')) {
                $xfPath = realpath($pathCandidate);
                break;
            }
        }

        if ($xfPath === false) {
            // could not confirm path to XenForo, bye
            return;
        }

        if (class_exists('XenForo_Application')) {
            // for some reason, XenForo has been init'd already, this should not happen...
            return;
        }

        if (function_exists('date_default_timezone_set')) {
            $originalTimezone = date('e');
        }
        $originalErrorReporting = ini_get('error_reporting');

        require($xfPath . '/library/XenForo/Autoloader.php');
        $xfAutoloader = XenForo_Autoloader::getInstance();
        $xfAutoloader->setupAutoloader($xfPath . '/library');

        // avoid changing MediaWiki environment as much as possible
        XenForo_Application::disablePhpErrorHandler();
        $initConfig = array(
            'undoMagicQuotes' => false,
            'setMemoryLimit' => false,
            'resetOutputBuffering' => false,
        );

        XenForo_Application::initialize($xfPath . '/library', $xfPath, true, $initConfig);
        if (XenForo_Application::$versionId < 1020000) {
            die('XenForo extension requires XenForo v1.2.0+.');
        }

        $needPublicSession = true;
        if (class_exists('MediaWiki_Dependencies_Public')
            && !empty($_SERVER['SCRIPT_FILENAME'])
            && substr(basename($_SERVER['SCRIPT_FILENAME']), 0, 5) === 'index'
        ) {
            // start front controller to keep track of online user activity
            $fc = new XenForo_FrontController(new MediaWiki_Dependencies_Public());
            $fc->setSendResponse(false);
            $fc->run();

            $needPublicSession = false;
        }

        if ($needPublicSession) {
            XenForo_Session::startPublicSession();
        }

        error_reporting($originalErrorReporting);
        if (!empty($originalTimezone)) {
            date_default_timezone_set($originalTimezone);
        }
    }

    public static function addHooks()
    {
        global $wgHooks;
        global $wgXenForoAuthPlugin;

        if (!class_exists('XenForo_Application')) {
            // safe guard
            return false;
        }

        /*
         * Hook for adding/modifying the URLs contained in the personal URL bar
         */
        $wgHooks['PersonalUrls'][] = array($wgXenForoAuthPlugin, 'addLinkHook');

        /*
         * User authentication hook
         *
         * This is not really perfect but the best place MediaWiki provides to hook
         * in the user initialization / authentication process.
         */
        $wgHooks['UserLoadFromSession'][] = array($wgXenForoAuthPlugin, 'userLoadFromSessionHook');

        /*
         * Hook to place own data in the session managed by mediawiki.
         * This is called every time $wgUser->setCookies() is called.
         */
        $wgHooks['UserSetCookies'][] = array($wgXenForoAuthPlugin, 'userSetCookiesHook');

        /*
         * Hook to manage logout of a user properly (e.g. clear own session data)
         * This is called every time $wgUser->logout() is called.
         */
        $wgHooks['UserLogout'][] = array($wgXenForoAuthPlugin, 'userLogoutHook');

        return true;
    }
}

function mwxf_setup()
{
    global $wgHooks, $wgExtensionFunctions;

    $wgHooks['AuthPluginSetup'][] = 'MWXF_Setup::bootstrap';
    $wgExtensionFunctions[] = 'MWXF_Setup::initXenForo';
    $wgExtensionFunctions[] = 'MWXF_Setup::addHooks';

    // sondh @ 20111128
    $wgHooks['ArticleSaveComplete'][] = 'MWXF_Hooks::ArticleSaveComplete';
}