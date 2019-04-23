<?php

require_once($GLOBALS['IP'] . '/includes/AuthPlugin.php');

class MWXF_AuthPlugin extends AuthPlugin
{
    private $_oldWgAuth = null;
    private $_inUserLoadFromSessionHook = false;

    public function setOldWgAuth(AuthPlugin $auth)
    {
        $this->_oldWgAuth = $auth;
    }

    /**
     * This function is called during mediawiki's authentication process if
     * the normal login (e.g. via an old session) fails.
     * In this case the function looks for externally provided authentication
     * credentials to log in a user.
     *
     * @see userLoadFromSessionHook
     * @param User &$user
     * Reference to the global user object. This is passed on from the invoking
     * hook function.
     * @return boolean True if login succeeded
     */
    private function login($user)
    {
        $visitor = XenForo_Visitor::getInstance();

        if ($visitor->get('user_id') == 0) {
            // guest?
            $user->doLogout(); // just to make sure
            $_COOKIE["{$GLOBALS['wgCookiePrefix']}UserID"] = 0; // super sure now

            return false;
        }

        $username = $visitor->get('username');
        $username = Title::makeTitleSafe(NS_USER, $username)->getText();

        $created = false;
        if (!User::idFromName($username)) {
            $this->createUser($user, $username, $visitor);
            $created = true;
        }

        $loggedIn = false;
        if (User::idFromName($username)) {
            // log the user in
            $user = User::newFromName($username);
            if (!$created) {
                $this->modifyUserIfNeeded($user, $visitor);
            }
            $user->load();
            wfSetupSession();

            if ($user->isLoggedIn()) {
                $user->setCookies();
                $loggedIn = true;
            }
        }

        return $loggedIn;
    }

    /**
     * @param User $user
     * @param string $username
     * @param XenForo_Visitor $visitor
     * @return bool
     */
    private function createUser($user, $username, $visitor)
    {
        // setup the user object
        $user->loadDefaults($username);

        $oldVersion = version_compare($GLOBALS['wgVersion'], '1.24', '<=');
        if ($oldVersion) {
            $user->mPassword = 'nologin';
            $user->mNewpassTime = 1;
            $user->setOption('rememberpassword', 0);
        }

        // create database entry
        $user->addToDatabase();

        // see if it worked ...
        if ($user->mId > 0) {
            // set all attributes other than the Uid/Username
            $this->modifyUserIfNeeded($user, $visitor);

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param User $user
     * @param XenForo_Visitor $visitor
     */
    private function modifyUserIfNeeded($user, $visitor)
    {
        $changed = false;

        // check email
        $new = $visitor->get('email');
        $old = $user->getEmail();

        if ($new != $old) {
            $user->setEmail($new);
            $user->confirmEmail();
            $changed = true;
        }

        // check groups
        $new = array();
        if ($visitor->get('is_admin')) {
            $new[] = 'bureaucrat';
            $new[] = 'sysop';
        }
        $new[] = 'xf_ug' . $visitor->get('user_group_id');

        // secondary groups
        $secondaryGroupIds = explode(',', $visitor->get('secondary_group_ids'));
        foreach ($secondaryGroupIds as $secondaryGroupId) {
            $secondaryGroupId = intval($secondaryGroupId);
            if ($secondaryGroupId > 0) {
                $new[] = 'xf_ug' . $secondaryGroupId;
            }
        }

        $old = $user->getGroups();
        $diff = array_diff($new, $old);

        if (!empty($diff)) {
            foreach ($new as $newGroup) {
                if (!in_array($newGroup, $old)) {
                    $user->addGroup($newGroup);
                }
            }
            foreach ($old as $oldGroup) {
                if (!in_array($oldGroup, $new)) {
                    $user->removeGroup($oldGroup);
                }
            }
            $changed = true;
        }

        if ($changed) {
            $user->saveSettings();
        }
    }

    public function addLinkHook(
        &$personalUrls,
        /** @noinspection PhpUnusedParameterInspection */
        &$title)
    {
        if (!empty($personalUrls['anonlogin'])) {
            // kill the anon login link since XenForo does not support it
            unset($personalUrls['anonlogin']);
        }

        if (!empty($personalUrls['createaccount'])) {
            // redirect account creation to XenForo
            $personalUrls['createaccount']['href'] = XenForo_Link::buildPublicLink('canonical:register');
        }

        return true;
    }

    public function userLoadFromSessionHook($user, &$result)
    {
        // check if this hook was already called
        if ($this->_inUserLoadFromSessionHook) {
            return true;
        }
        $this->_inUserLoadFromSessionHook = true;

        // TODO put this somewhere else and invent a config option
        if (session_id() == '') {
            // Normally MW should create the session itself, but if you delete it manually you're gonna crash
            global $wgCookiePrefix;
            $MW_sessionName = $wgCookiePrefix . "_session";

            session_name($MW_sessionName);
            session_start();
        }

        $this->login($user);
        $this->_inUserLoadFromSessionHook = false;

        return true;
    }

    public function userSetCookiesHook(
        /** @noinspection PhpUnusedParameterInspection */
        $user, &$session, &$cookies)
    {
        return true;
    }

    public function userLogoutHook(
        /** @noinspection PhpUnusedParameterInspection */
        $user)
    {
        // surprisingly the logout code stays the same since XenForo 1.1.5 till 1.4.5
        // this should work for a long time ahead (at least I hope so)
        if (XenForo_Visitor::getInstance()->get('is_admin')) {
            $class = XenForo_Application::resolveDynamicClass('XenForo_Session');

            /** @var XenForo_Session $adminSession */
            $adminSession = new $class(array('admin' => true));
            $adminSession->start();
            $adminSession->delete();
        }

        XenForo_Application::get('session')->delete();
        XenForo_Helper_Cookie::deleteAllCookies(
            array('session'),
            array('user' => array('httpOnly' => false))
        );

        XenForo_Visitor::setup(0);

        return true;
    }

    public function userExists($username)
    {
        /** @var XenForo_Model_User $userModel */
        $userModel = XenForo_Model::create('XenForo_Model_User');

        $user = $userModel->getUserByName($username);
        if (!empty($user)) {
            return true;
        }

        return parent::userExists($username);
    }


    public function authenticate($username, $password)
    {
        /** @var XenForo_Model_User $userModel */
        $userModel = XenForo_Model::create('XenForo_Model_User');

        $userId = $userModel->validateAuthentication($username, $password);
        if (!$userId) {
            // TODO: log strikes
            return parent::authenticate($username, $password);
        }

        $userModel->setUserRememberCookie($userId);

        XenForo_Model_Ip::log($userId, 'user', $userId, 'mediawiki_login');

        $session = XenForo_Session::startPublicSession();
        $session->changeUserId($userId);
        XenForo_Visitor::setup($userId);

        return true;
    }


    public function strict()
    {
        return true;
    }

    public function strictUserAuth($username)
    {
        return true;
    }

    public function autoCreate()
    {
        return true;
    }
}