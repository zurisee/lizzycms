<?php


class Authentication
{
	public  $message = '';
	private $userRec = false;
	private $knownUsers = null;
	private $lastLogMsg = false;


    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->config = $this->lzy->config;
        $this->localHost = $lzy->config->isLocalhost;
        $this->loadKnownUsers();
        $this->loginTimes = (isset($_SESSION['lizzy']['loginTimes'])) ? unserialize($_SESSION['lizzy']['loginTimes']) : array();
		if (!isset($_SESSION['lizzy']['user'])) {
			$_SESSION['lizzy']['user'] = false;
		}
        setStaticVariable('lastLoginMsg', '');

        $this->userInitialized = false;
        $GLOBALS['lizzy']['isLoggedin'] = false;
        $GLOBALS['lizzy']['isPrivileged'] = false;
        $GLOBALS['lizzy']['isAdmin'] = false;
    } // __construct



    public function authenticate()
    {
        // checks and verifies login attempts, if post-variables are received:
        // - credentials [lzy-login-username, lzy-login-password-password]
        // - oneTimeCode [lzy-onetime-code]

        $res = null;
        if (isset($_POST['lzy-login-username']) && isset($_POST['lzy-login-password'])) {    // user sent un & pw
            $credentials = array('username' => $_POST['lzy-login-username'], 'password' => $_POST['lzy-login-password']);
            if (($user = $this->validateCredentials($credentials))) {
                if (is_string($user)) {
                    $user = $this->setUserAsLoggedIn($user, true);
                    $msg = $this->lzy->trans->translate('{{ lzy-login-successful-as }}');
                    reloadAgent(false, "$msg: $user");
                } elseif (is_array($user)) {
                    if ((@$user[2] === 'Override') && isset($user[1])) {
                        $this->lzy->page->addOverride($user[1]);
                    } elseif ((@$user[2] === 'Overlay') && isset($user[1])) {
                        $this->lzy->page->addOverlay($user[1]);
                    }
                    $res = false;
                }
            } else {
                $this->validateAccessCode( $credentials );
                $res = [null, "{{ lzy-login-failed }}", 'Message'];
            }
        }
        // note: if user sent an accessCode, it will be handled by this->validateAccessCode()

        if ($res === null) {        // no login attempt detected -> check whether already logged in:
            if (isset($_GET['logout'])) {
                $this->logout();
                reloadAgent();
            }

            $user = $this->getLoggedInUser();
            $user = $this->setUserAsLoggedIn( $user );
            $res = true;

            if ($user && ($msg = getNotificationMsg())) {
                $msg = $this->lzy->trans->translateVariable($msg, true);
                $res = [$user, "<p>{{ $msg }}</p>", 'Message' ];

                // in case user used ot-code in place of password, nudge setting a propre PW:
                $this->renderForceDefinePWIfRequired();
            }
        }

        $this->lzy->initiateInBrowserUserNotification($res);   // inform user about login/logout etc.
    } // authenticate



    public function getUsername()
    {
        if (isset($this->loggedInUser)) {
            return $this->loggedInUser;
        } else {
            return @$_SESSION['lizzy']['user'];
        }
    } // getUsername



    public function getUsersGroups()
    {
        return $this->loggedInUser;
    } // getUsersGroups



    public function getKnownGroups()
    {
        $knownUsers = $this->getKnownUsers();
        $groups = [];
        foreach ($knownUsers as $user) {
            $grps = explodeTrim(' ,', $user['groups']);
            foreach ($grps as $group) {
                if ($group) {
                    $groups[$group] = $group;
                }
            }
        }
        return array_keys($groups);
    } // getKnownGroups



	private function validateCredentials($credentials)
	{
	    // returns username or false, if no valid match was found
        $requestingUser = $credentials['username'];

        $res = false;
		if (!isset($this->knownUsers[$requestingUser])) {    // user found in user-DB:
            $requestingUser = $this->findUserRecKey($requestingUser);
        }
		if (isset($this->knownUsers[$requestingUser])) {
            $rec = $this->knownUsers[$requestingUser];

            // handle case where user just enters username (or email) and leaves pw empty:
            if (!trim($credentials['password']) && isset($rec['email'])) {
                $accForm = new UserLoginBase($this->lzy);
                $res = $accForm->handleOnetimeLoginRequest( $rec['email'], $rec );
                return $res;
            }

            $rec['username'] = $requestingUser;
            $correctPW = (isset($rec['password'])) ? $rec['password'] : '';
            $providedPW = isset($credentials['password']) ? trim($credentials['password']) : '####';

            // check username and password:
            if (password_verify($providedPW, $correctPW)) {  // login succeeded
                writeLogStr("logged in: $requestingUser [{$rec['groups']}] (" . getClientIP(true) . ')', LOGIN_LOG_FILENAME);
                $res = $requestingUser;

            } else {
                // login by un&pw failed: pw wrong -> check whether it was a one-time code:
                $res = $this->validateOnetimeAccessCode( $providedPW, true );
                if (!$res) {
                    $res = $this->validateAccessCode(['username' => $requestingUser, 'password' => $providedPW]);    // reloads on success, returns on failure
                }
                if (!$res) {
                    // login failed for good:
                    $rep = '';
                    if ($this->handleFailedLogins()) {
                        $rep = ' REPEATED';
                    }
                    $this->monitorFailedLoginAttempts();
                    writeLogStr("*** Login failed$rep (wrong pw): $requestingUser [" . getClientIP(true) . ']', LOGIN_LOG_FILENAME);
                    $this->message = '{{ Login failed }}';
                    setStaticVariable('lastLoginMsg', '{{ Login failed }}');
                    $this->unsetLoggedInUser();
                    $jq = "$('#lzy-login-form').lzyPopup('show')";
                    $this->lzy->page->addJq($jq, 'append');
                }
            }
        }
        return $res;
    } // validateCredentials



    public function validateOnetimeAccessCode($code, $forceDefinePW = false)
    {
        $user = $this->readOneTimeAccessCode($code);
        if (!$user) {
            return false;
        }
        if ($forceDefinePW) {
            setStaticVariable('forceDefinePW', true);
        }

        $this->setUserAsLoggedIn( $user, true );
        writeLogStr("user '$user' successfully logged in via access link ($code). [".getClientIP().']', LOGIN_LOG_FILENAME);
        $msg = $this->lzy->trans->translate('{{ lzy-login-successful-as }}');
        $msg = "$msg: $user";
        reloadAgent(false, $msg);
    } // validateOnetimeAccessCode



    public function readOneTimeAccessCode($code)
    {
        // checks whether there is a pending oneTimeAccessCode, purges old entries
        $tick = new Ticketing();
        $ticket = $tick->consumeTicket($code, true); // type=true keeps '_ticketType' from being suppressed $ticket
        if (!$ticket) {
            $errMsg = $tick->getLastError();
            $this->lastLogMsg = "*** one-time link rejected: $code ($errMsg) [".getClientIP().']';
            return false;
        }

        if ($ticket['_ticketType'] === 'lzy-ot-access') { // reponse from login-by-email
            return $ticket ['username'];

        } else {
            $this->lastLogMsg = "*** one-time link rejected: $code (wrong ticket-type) [".getClientIP().']';
            return false;
        }
    } // readOneTimeAccessCode



    public function validateAccessCode( $codeCandidate )
    {
        // this is an access code stored in a user's record
        if (!$this->knownUsers) {
            return false;
        }
        $requestingUser = false;
        if (is_array($codeCandidate)) {
            // this is the rare case that user used login-form to submit accessCode:
            $requestingUser = $codeCandidate['username'];
            if (!$requestingUser) {
                //ToDo: hack-prevention
                reloadAgent(false, "{{ lzy-login-failed }}");
            }
            $codeCandidate = $codeCandidate['password'];
        }
        foreach ($this->knownUsers as $user => $rec) {
            if (@$rec['inactive']) {
                continue;
            }
            if (isset($rec['accessCode'])) {
                $code = $rec['accessCode'];
                if (password_verify($codeCandidate, $code) || ($codeCandidate === $code)) {
                    if (isset($rec['accessCodeValidUntil'])) {
                        $validUntil = strtotime($rec['accessCodeValidUntil']);
                        if ($validUntil < time()) {
                            return false;
                        }
                    }
                    // if user sent username, make sure the accessCode belongs to him:
                    if ($requestingUser && ($requestingUser !== $user)) {
                        //ToDo: hack-prevention
                        reloadAgent(false, "{{ lzy-login-failed }}");
                    }

                    // mechanism to leave accessCode in URL for one more call:
                    $keepAccessCode = $this->lzy->keepAccessCode || !$_SESSION['lizzy']['user'];
                    $this->setUserAsLoggedIn($user, true);
                    writeLogStr("user '$user' successfully logged in via access link ($codeCandidate). [".getClientIP().']', LOGIN_LOG_FILENAME);

                    if ($keepAccessCode && !isset($_GET['login'])) {
                        $msg = $this->lzy->trans->translate('{{ lzy-login-successful-as }}');
                        $this->lzy->page->addMessage("$msg: $user", true);
                        return $user;
                    } else {
                        $msg = '';
                        $requestedUrl = $GLOBALS['lizzy']['requestedUrl'];
                        $requestedUrl = preg_replace('|/[A-Z][A-Z0-9]{4,}/?|', '/', $requestedUrl);
                        if (isset($_GET['login'])) {
                            $msg = $this->lzy->trans->translate('{{ lzy-login-successful-as }}') . ": $user";
                            $requestedUrl = preg_replace('/[?&]login/', '', $requestedUrl);
                        }
                        reloadAgent($requestedUrl, $msg);
                    }
                }
            }
        }
        return false;
    } // validateAccessCode



    public function validateTicket($ticket)
    {
        // checks whether there is a pending ticket, purges old entries
        require_once SYSTEM_PATH.'ticketing.class.php';

        $tick = new Ticketing();
        $ticket = $tick->consumeTicket($ticket);
        if (!$ticket) {
            $this->monitorFailedLoginAttempts();
            writeLogStr("*** ticket rejected: $ticket [".getClientIP().']', LOGIN_LOG_FILENAME);
            return false;
        }

        return $ticket;
    } // validateTicket



    public function getDisplayName()
    {
        if (isset($this->userRec["displayName"])) {
            return $this->userRec["displayName"];
        } else {
            return @$this->userRec["name"];
        }
    } // getDisplayName



    public function setUserAsLoggedIn($user, $force = false)
	{
	    if ($this->userInitialized && !$force) {
	        return '';
        }
        $this->userInitialized = true;

        if (!file_exists('config/users.yaml') && isLocalhost()) {
            // we are in initial state where no users or admin, has been defined yet:
            $user = 'autoAdmin';
            $isAdmin = true;
            $rec = [];

        } else {
            // check whether account is inactive -> no login allowed:
            $rec = $this->getUserRec($user);
            if (!$rec || (isset($rec['inactive']) && $rec['inactive'])) {
                return false;
            }

            $isAdmin = $this->isAdmin();
        }

        $this->userRec = $rec;

        $this->loginTimes[$user] = time();
        $this->loggedInUser = $user;
        $_SESSION['lizzy']['user'] = $user;
        $_SESSION['lizzy']['userRec'] = $rec;
        $isPrivileged = $isAdmin || $this->checkAdmission('editors');

        $_SESSION['lizzy']['isAdmin'] = $isAdmin;
        $_SESSION['lizzy']['isPrivileged'] = $isPrivileged;
        $_SESSION['lizzy']['loginTimes'] = serialize($this->loginTimes);

        // determine permission for datastorage access to files in 'config/':
        $_SESSION['lizzy']['configDbPermission'] = false;
        if ($this->config->admin_configDbPermission) {
            $configDbPermissionAry = explodeTrim(':', $this->config->admin_configDbPermission);
            $perm = $this->checkGroupMembership($configDbPermissionAry[1]);
            if ($perm && $configDbPermissionAry[0]) {
                $_SESSION['lizzy']['configDbPermission'] = ($configDbPermissionAry[0] === $user);
            }
            $_SESSION['lizzy']['configDbPermission'] = $perm;
        }

        $GLOBALS['lizzy']['user'] = $user;
        $GLOBALS['lizzy']['isLoggedin'] = boolval( $user );
        $GLOBALS['lizzy']['isPrivileged'] = $isPrivileged;
        $GLOBALS['lizzy']['isAdmin'] = $isAdmin;

        if (isset($rec['displayName'])) {
            $displayName = $rec['displayName']; // displayName from user rec
            $_SESSION['lizzy']['userDisplayName'] = $displayName;

        } else {
            $_SESSION['lizzy']['userDisplayName'] = $user;
        }

        // check user's preferred language (unless ?lang override request is present):
        if (isset($rec['lang']) && !isset($_GET['lang'])) {
            $subLang = $lang = $rec['lang'];
            if (preg_match('/(\w+)\d/', $lang, $m)) {
                $lang = $m[1];
            }
            setStaticVariable('lang', $lang);
            setStaticVariable('subLang', $subLang);
        }

        // check self-admin permission:
        $selfadmin = $this->config->admin_userAllowSelfAdmin;
        if ($selfadmin === '1') {
            $this->config->admin_userAllowSelfAdmin = true;
        } elseif ($selfadmin) {
            $permitted = $this->checkGroupMembership($this->config->admin_userAllowSelfAdmin);
            $this->config->admin_userAllowSelfAdmin = $permitted;
        }

        return $user;
    } // setUserAsLoggedIn



    public function getLoggedInUser( $returnRec = false )
	{
	    // if user is logged in (i.e. $_SESSION['lizzy']['user'] is set, returns string username
        // if user was logged in but session expired, returns array
        // if user is NOT logged in, returns false
		$res = false;
		$user = isset($_SESSION['lizzy']['user']) ? $_SESSION['lizzy']['user'] : false;
		if ($user) {
			$rec = (isset($this->knownUsers[$user])) ? $this->knownUsers[$user] : false;
            $this->userRec = $rec;
			if (!$rec) {    // just to be safe: if logged in user has nor record, don't allow to proceed
			    $_SESSION['lizzy']['user'] = false;

            } else {                    // user is logged in
                $res = $user;
                $isAdmin = $this->isAdmin(true);
                $GLOBALS['lizzy']['isLoggedin'] = boolval( $user );
                $GLOBALS['lizzy']['isPrivileged'] = $this->checkAdmission('admins,editors');
                $GLOBALS['lizzy']['isAdmin'] = $isAdmin;

                $lastLogin = (isset($this->loginTimes[$user])) ? $this->loginTimes[$user] : 0;  // check maxSessionTime
                if (isset($this->knownUsers[$user]['validity-period'])) {
                    $validityPeriod = $this->knownUsers[$user]['validity-period'];
                    if ($lastLogin < (time() - $validityPeriod)) {
                        writeLog("Login of $user expired by user's validity-period value $validityPeriod (last login: ".date('Y-m-d H:i').')');
                        $rec = false;
                        $res = [false, '{{ validity-period expired }}', 'LoginForm'];
                        $this->unsetLoggedInUser();
                    }
                } elseif ($this->config->admin_defaultLoginValidityPeriod) {
                    if ($lastLogin < (time() - $this->config->admin_defaultLoginValidityPeriod)) {
                        writeLog("Login of $user expired by default value {$this->config->admin_defaultLoginValidityPeriod} (last login: ".date('Y-m-d H:i').')');
                        $rec = false;
                        $res = [false, '{{ validity-period expired }}', 'LoginForm'];
                        $this->unsetLoggedInUser();
                    }
                }
            }
		} elseif ($this->config->admin_autoAdminOnLocalhost && $this->config->isLocalhost) {
		    $res = 'autoadmin';
            $GLOBALS['lizzy']['isAdmin'] = true;
            $_SESSION['lizzy']['isAdmin'] = true;
            $rec = false;
        }

		if ($res && $returnRec) {
            $this->userRec = $rec;
		    return $rec;

        } elseif ($res) {
            $this->userRec = $rec;
            return $res;
        } else {
		    return false;
        }
    } // getLoggedInUser



    public function getUserRec( $username = false )
    {
        if (!$username) {
            $rec = $this->userRec;
        } elseif (isset($this->knownUsers[$username])) {
            $rec = $this->knownUsers[$username];
        } else {
            return [];
        }
        if (isset($rec['password'])) {
            unset($rec['password']);
        }
        if (isset($rec['accessCode'])) {
            unset($rec['accessCode']);
        }
        return $rec;
    } // getUserRec



    public function getAccessCode( $username = false )
    {
        if (!$this->config->admin_userAllowSelfAccessLink) {
            return null;    // only allowed if admin_userAllowSelfAccessLink is active
        }
        if (!$username) {
            $rec = $this->userRec;
        } elseif (isset($this->knownUsers[$username])) {
            $rec = $this->knownUsers[$username];
        } else {
            return '';
        }
        if (isset($rec['accessCode'])) {
            return $rec['accessCode'];
        }
        return '';
    } // getAccessCode



    public function getKnownUsers()
    {
        if (is_array($this->knownUsers)) {
            return $this->knownUsers;
        } else {
            return [];
        }
    } // getKnownUsers



    public function isKnownUser($user = false, $tolerant = false)
    {
        if (!$user) {
            return $this->isLoggedIn();
        }
        if ($tolerant) {
            return findUserRecKey($user);
        } else {
            return (is_array($this->knownUsers) && in_array($user, array_keys($this->knownUsers)));
        }
    } // isKnownUser



    public function checkPrivilege($criteria) {
        if (!$criteria) {
            return false;
        }

        $not = false;
        if ($criteria[0] === '!') {
            $not = true;
            $criteria = substr($criteria, 1);
        }
        if (preg_match('/logged-?in/', $criteria)) {
            return $this->isLoggedIn() xor $not;

        } elseif ($criteria === 'privileged') { // editor or admin
            return $this->isPrivileged() xor $not;

        } elseif ($criteria === 'admin') {
            return $this->isAdmin() xor $not;
        }
        return false;
    } // checkPrivilege



    public function checkGroupMembership($requiredGroup)
    {
        if ($this->localHost && $this->config->admin_autoAdminOnLocalhost) {	// no restriction
	        return true;
        }

        if (isset($this->userRec['groups'])) {
            $requiredGroups = explode(',', $requiredGroup);
            $usersGroups = strtolower(str_replace(' ', '', ','.$this->userRec['groups'].','));
            foreach ($requiredGroups as $rG) {
                $rG = strtolower(trim($rG));
                if ((strpos($usersGroups, ",$rG,") !== false) ||
                    (strpos($usersGroups, ",admins,") !== false)) {
                    return true;
                }
            }
        }
        return false;
    } // checkGroupMembership



    public function checkAdmission($lockProfile)
	{
		if ((!$lockProfile) || ($this->localHost && $this->config->admin_autoAdminOnLocalhost)) {	// no restriction
			return true;
		}
		
		$rec = $this->userRec;
		if (!$rec) {
		    return false;
        } elseif (!isset($rec['username'])) {
            $rec['username'] = '';
        }

		$usersGroups = $rec['groups'];
        if ($this->isGroupMember($usersGroups, 'admins')) { // admins have access by default
		    return true;
        }

		$lockProfiles = explode(',', $lockProfile);
		foreach ($lockProfiles as $lp) {
            if ($this->isGroupMember($usersGroups, trim($lp))) { // admins have access by default
                return true;
            }
		}
		if ($rec && !$this->message) {
			$this->message = '{{ Insufficient privileges }}';
		}
		return false;
	} // checkAdmission



    private function isGroupMember($usersGroups, $groupToCheckAgainst)
    {
        if (!$usersGroups) {
            return false;
        }
        $usersGroups = str_replace(' ', '', ",$usersGroups,");
        $res = (strpos($usersGroups, ",$groupToCheckAgainst,") !== false);
        return $res;
    } // isGroupMember



    public function logout()
    {
        $user = getStaticVariable('user');
        if ($user) {
            $user .= (isset($_SESSION['lizzy']['userDisplayName'])) ? ' (' . $_SESSION['lizzy']['userDisplayName'] . ')' : '';
            writeLogStr("logged out: $user [" . getClientIP(true) . ']', LOGIN_LOG_FILENAME);
        } else {
            writeLogStr("logged out: undefined [" . getClientIP(true) . ']', LOGIN_LOG_FILENAME);
        }

        $this->unsetLoggedInUser();
    } // logout



    public function unsetLoggedInUser($user = '')
    {
        if ($user) {
            $this->loginTimes[$user] = 0;
        }
        $this->userRec = null;
        $_SESSION['lizzy']['user'] = false;
        $_SESSION['lizzy']['userDisplayName'] = false;
        $isAdmin = ($this->localHost && $this->config->admin_autoAdminOnLocalhost);
        $_SESSION['lizzy']['isAdmin'] = $isAdmin ;
        $GLOBALS['lizzy']['isAdmin'] = $isAdmin;
        $GLOBALS['lizzy']['isLoggedin'] = false;
        $GLOBALS['lizzy']['isPrivileged'] = false;
        $this->lzy->unCachePage();
    } // unsetLoggedInUser



    private function handleFailedLoginAttempts($code = '')
    {
        $rep = '';
        if ($this->handleFailedLogins()) {
            $rep = ' REPEATED';
        }
        $this->monitorFailedLoginAttempts();
        if ($rep) {
            writeLogStr("*** one time link rejected$rep: $code [" . getClientIP() . ']', LOGIN_LOG_FILENAME);
        }
    } // handleFailedLoginAttempts



    private function handleFailedLogins()
    {
        $repeated = false;
        $ip = getClientIP();
        $failedLogins = getYamlFile(FAILED_LOGIN_FILE);     // enforce delay for retries from same ip
        $tnow = time();
        foreach ($failedLogins as $t => $ip1) {
            if ($t < ($tnow - 60)) {
                unset($failedLogins[$t]);
            } elseif ($ip === $ip1) {
                sleep(3);
                $repeated = true;
                unset($failedLogins[$t]);
            }
        }
        $failedLogins[time()] = $ip;
        writeToYamlFile(FAILED_LOGIN_FILE, $failedLogins);
        return $repeated;
    } // handleFailedLogins



    private function monitorFailedLoginAttempts()
    {
        // More than HACKING_THRESHOLD failed login attempts within 15 minutes are considered a hacking attempt.
        // If that is detected, we delay ALL login attempts by 5 seconds.
        $tnow = time();
        $tooOld = time() - 900;
        $origin = $_SERVER["HTTP_HOST"];
        $out = "$origin|$tnow\n";   // add this attempt
        if (file_exists(HACK_MONITORING_FILE)) {
            $lines = file(HACK_MONITORING_FILE);
            $cnt = $allCnt = 0;
            foreach ($lines as $l) {
                list($o, $t) = explode('|', $l);
                if (intval($t) < $tooOld) {   // drop old entries
                    continue;
                }
                $allCnt++;
                if (strpos($origin, $o) === 0) {
                    $cnt++;
                }
                $out .= $l;
            }
            if (($cnt > HACKING_THRESHOLD) || ($allCnt > 4*HACKING_THRESHOLD)) {
                writeLogStr("!!!!! Possible hacking attempt [".getClientIP().']', LOGIN_LOG_FILENAME);
                sleep(5);
            }
        }
        file_put_contents(HACK_MONITORING_FILE, $out);

    } // monitorFailedLoginAttempts



    public function isValidPassword($password, $password2 = false)
    {
        if (($password2 !== false) && ($password !== $password2)) {
            return '{{ lzy-change-password-not-equal-response }}';
        }
        if ($this->config->admin_enforcePasswordQuality) {
            if (strlen($password) < $this->config->admin_minPasswordLength) {
                return '{{ lzy-change-password-too-short-response }}';
            }
            if (!preg_match('/[A-Z]/', $password) ||
                !preg_match('/\d/', $password) ||
                !preg_match('/[^\w\d]/', $password)) {
                return '{{ lzy-change-password-insufficient-response }}';
            }
        }
        return '';
    } // isValidPassword



    public function isPrivileged()
    {
        return $this->checkAdmission('admins,editors');
    } // isPrivileged



    public function isLoggedIn()
    {
        return $this->isAdmin() || (bool) $this->userRec;
    } // isPrivileged



    public function isAdmin($thorough = false)
    {
        if (!$thorough && $GLOBALS['lizzy']['isAdmin']) {
            return true;
        }
        return $this->checkAdmission('admins');
    } // isAdmin



    public function loadKnownUsers()
    {
        $this->userDB = $usersFile = $this->config->configPath.$this->config->admin_usersFile;
        $this->knownUsers = getYamlFile($usersFile);
        if (is_array($this->knownUsers)) {
            foreach ($this->knownUsers as $key => $rec) {
                if (!isset($rec['groups'])) {
                    $this->knownUsers[$key]['groups'] = isset($rec['group']) ? $rec['group'] : ''; // make group a synonym for groups
                } elseif (is_array($rec['groups']) && isset($rec['groups'][0])) {
                    $this->knownUsers[$key]['groups'] = $rec['groups'][0];
                }
                $this->knownUsers[$key]['username'] = $key;
            }
        } else {
            $this->knownUsers = [];
        }
    } // loadKnownUsers



    public function findUserRecKey($username, $searchField = false)
    {
        // looks for a user record that contains $username:
        //  - key (=username)
        //  - 'username' field
        //  - 'displayName' field, if $this->config->admin_allowDisplaynameForLogin is set
        //  - in all fields, if $searchField = '*'
        //  - in specific field, if $searchField is set

        $username = strtolower($username);
        if (isset($this->knownUsers[$username])) {
            $res = $username;
        } else {
            $res = false;
            $tolerant = $this->config->admin_allowDisplaynameForLogin;
            foreach ($this->knownUsers as $name => $rec) {
                if ($searchField === '*') {
                    if ($name === $username) {
                        $res = $name;
                        break;
                    }
                    foreach ($rec as $key => $value) {
                        if (strtolower($value) === $username) {
                            $res = $name;
                            break 2;
                        }
                    }
                    continue;
                }
                if ($searchField) {
                    if (isset($rec[$searchField]) && (strtolower($rec[$searchField]) === $username)) {
                        $res = $name;
                        break;
                    }
                } elseif (strtolower($name) === $username) {
                    $res = $name;
                    break;

                } elseif (isset($rec['email']) && ($rec['email'] === $username)) {
                    $res = $name;
                    break;

                } elseif (isset($rec['username']) && ($rec['username'] === $username)) {
                    $res = $name;
                    break;

                } elseif ($tolerant && isset($rec['displayName']) && (strtolower($rec['displayName']) === $username)) {
                    $res = $name;
                    break;
                }
            }
        }
        return $res;
    } // findUserRecKey



    public function findEmailMatchingUserRec($searchKey, $checkInEmailList = false)
    {
        if (!$searchKey) {
            return false;
        }
        $searchKey = strtolower($searchKey);

        // find matching record in DB of known users:
        // 1) match with key (aka 'username')
        // 2) match with explict 'email' field in rec
        // 3) match with item refered to by 'emailList'
        $email = '';
        $rec = null;
        if (isset($this->knownUsers[$searchKey])) {    // 1)
            $rec = $this->knownUsers[$searchKey];
            if (isset($rec['email'])) {
                $email = $rec['email'];
            } elseif (is_legal_email_address($searchKey)) {
                $email = $searchKey;
            } else {
                $email = false;
            }

        } elseif ($user = $this->findUserRecKey($searchKey, '*')) { // 2)
            $rec = $this->knownUsers[$user];
            if (isset($rec['email'])) {
                $email = $rec['email'];
            } elseif (is_legal_email_address($searchKey)) {
                $email = $searchKey;
            } else {
                $email = false;
            }

        } elseif ($checkInEmailList && ($rec = $this->findEmailInEmailList($searchKey))) { // 3
            $email = $searchKey;
        }
        return [$email, $rec];
    } // findEmailMatchingUserRec



    public function findEmailInEmailList($submittedEmail)
    {
        $found = false;
        $submittedEmail = strtolower($submittedEmail);
        foreach ($this->knownUsers as $name => $rec) {
            if (isset($rec['accessCodeEnabled']) && (!$rec['accessCodeEnabled'])) {
                continue;
            }

            if (isset($rec['emailList']) && $rec['emailList']) {
                $filename = $this->config->configPath . $rec['emailList'];
                if (file_exists($filename)) {
                    $str = file_get_contents($filename);
                    $str = strtolower( str_replace("\n", ' ', $str) );
                    if (preg_match_all('/(\w(([_\.\-\']?\w+)*)@(\w+)(([\.\-]?\w+)*)\.([a-z]{2,}))/i', $str, $m)) {
                        $emails = $m[0];
                        $found = in_array($submittedEmail, $m[0]);
                        break;
                    }
                }
            }
        }
        if (!$found) {
            $rec = false;
        }
        return $rec;
    } // findEmailInEmailList



    public function getListOfUsers( $group = false )
    {
        if ($group) {
            $allUsers = $this->knownUsers;
            $users = [];
            foreach ($allUsers as $un => $rec) {
                if ($this->isGroupMember($rec['groups'], $group)) {
                    $users[] = $un;
                }
            }
        } else {
            $users = array_keys( $this->knownUsers );
        }
        return implode(',', $users);
    } // getListOfUsers



    private function renderForceDefinePWIfRequired(): void
    {
        // in case user used ot-code in place of password, nudge setting a propre PW:
        if (@$_SESSION['lizzy']['forceDefinePW']) {
            require_once ADMIN_PATH . 'user-admin.class.php';
            $ua = new UserAdmin($this->lzy);
            $form = $ua->renderNewPwForm();
            $this->lzy->page->addOverlay($form);
            $_SESSION['lizzy']['forceDefinePW'] = false;
        }
    } // renderForceDefinePWIfRequired

} // class Authentication
