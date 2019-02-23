<?php

class Authentication
{
	public $message = '';
	private $userRec = false;


    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->config = $this->lzy->config;
        $this->localCall = $lzy->config->isLocalhost;
        $this->loadKnownUsers();
        $this->loginTimes = (isset($_SESSION['lizzy']['loginTimes'])) ? unserialize($_SESSION['lizzy']['loginTimes']) : array();
		if (!isset($_SESSION['lizzy']['user'])) {
			$_SESSION['lizzy']['user'] = false;
		}
        setStaticVariable('lastLoginMsg', '');
    } // __construct



    public function authenticate()
    {
        // checks and verifies login attempts, if post-variables are received:
        // - credentials [lzy-login-username, lzy-login-password-password]
        // - oneTimeCode [lzy-onetime-code]


        $res = null;
        if (isset($_POST['lzy-login-username']) && isset($_POST['lzy-login-password-password'])) {    // user sent un & pw
            $credentials = array('username' => $_POST['lzy-login-username'], 'password' => $_POST['lzy-login-password-password']);
            if (($user = $this->validateCredentials($credentials))) {
                $res = [$user, "{{ lzy-login-successful }}", 'Message'];
            } else {
                $res = [null, "{{ lzy-login-failed }}", 'Message'];
            }

        } elseif (isset($_POST['lzy-onetimelogin-request-email'])) {     // user sent email for logging in
            $res = $this->handleOnetimeLoginRequest();

        } elseif (isset($_POST['lzy-onetime-code']) && isset($_POST['lzy-login-user'])) {    // user sent accessCode
            $str = $this->validateOnetimeAccessCode( $_POST['lzy-onetime-code'] ); // reloads & never returns if login successful
            $res = [false, $str, 'Message'];
        }

        if ($res === null) {        // no login attempt detected -> check whether already logged in:
            $user = $this->setUserAsLoggedIn();
            if (!$user) {
                $this->logout();
            }
            $res = true;

            if ($user && isset($_GET['reload-arg'])) {
                $res = [$user, "<p>{{ lzy-{$_GET['reload-arg']} }}</p>", 'Message'];   //
            }
        }

        if (!$res || (isset($res[0]) && ($res[0] == false)) ) { // logout
            $this->logout();

        } elseif (is_string($res)) {    // string contains username of user to be logged in
            $this->setUserAsLoggedIn($res);

        } elseif (isset($res[0]) && is_string($res[0])) { // or username in res[0]
            $this->setUserAsLoggedIn($res[0]);
        }

        $this->handleLoginUserNotification($res);   // inform user about login/logout etc.
    } // authenticate




	private function validateCredentials($credentials)
	{
	    // returns username or false, if no valid match was found
        $requestingUser = strtolower($credentials['username']);

        $res = false;
		if (!isset($this->knownUsers[$requestingUser])) {    // user found in user-DB:
            $requestingUser = $this->findUserRecKey($requestingUser);
        }
		if (isset($this->knownUsers[$requestingUser])) {
            $rec = $this->knownUsers[$requestingUser];
            $rec['username'] = $requestingUser;
            $correctPW = (isset($rec['password'])) ? $rec['password'] : '';
            $providedPW = isset($credentials['password']) ? trim($credentials['password']) : '####';

            // check username and password:
            if (password_verify($providedPW, $correctPW)) {  // login succeeded
                writeLog("logged in: $requestingUser [{$rec['groups']}] (" . getClientIP(true) . ')', LOGIN_LOG_FILENAME);
                $res = $requestingUser;

            } else {                                        // login failed: pw wrong
                $rep = '';
                if ($this->handleFailedLogins()) {
                    $rep = ' REPEATED';
                }
                $this->monitorFailedLoginAttempts();
                writeLog("*** Login failed$rep (wrong pw): $requestingUser [" . getClientIP(true) . ']', LOGIN_LOG_FILENAME);
                $this->message = '{{ Login failed }}';
                setStaticVariable('lastLoginMsg', '{{ Login failed }}');
                $this->unsetLoggedInUser();
                $jq = "$('#lzy-login-form').popup('show')";
                $this->lzy->page->addJq($jq, 'append');
            }
        }
        return $res;
    } // validateCredentials




    //....................................................
    public function handleAccessCodeInUrl($pagePath)
    {
        $codeCandidate = basename($pagePath);
        if ($codeCandidate && preg_match('/^[A-Z][A-Z0-9]{4,}$/', $codeCandidate)) {
            $this->validateOnetimeAccessCode($codeCandidate);    // reloads on success, returns on failure

            $this->validateAccessCode($codeCandidate);   // check access code in user records and log in&reload if found

            $pagePath = trunkPath($pagePath,1, false); // remove hash-code from
        }
        return $pagePath;   // access granted
    } // handleAccessCodeInUrl




    //....................................................
    public function validateOnetimeAccessCode($code)
    {
        // invoked in 2 possible ways:
        //  1) from analyzeHttpRequest() -> code = last part of url
        //  2) from authenticate() -> code from post variable

        list($userRec, $oneTimeRec) = $this->readOneTimeAccessCode($code);
        $this->handleOneTimeCodeActions($oneTimeRec); // reloads if login successful

        $getArg = false;
        if ($userRec) {
            $user = $userRec['username'];
            $this->setUserAsLoggedIn( $user, null, $oneTimeRec["email"] );
            $displayName = $this->getDisplayName();
            if ($displayName) {
                $user .= " ({$displayName})";
            }
            writeLog("one time link accepted: $user [".getClientIP().']', LOGIN_LOG_FILENAME);

            // access granted, remove hash-code from url, if there is one:
            $requestedUrl = $GLOBALS['globalParams']['requestedUrl'];
            if ($requestedUrl && preg_match('|/[A-Z][A-Z0-9]{4,}/?$|', $requestedUrl)) {
                $requestedUrl = trunkPath($requestedUrl, 1, false); // remove hash-code from
            }
            reloadAgent($requestedUrl, 'login-successful'); // access granted, remove hash-code from url

        } else {
            $rep = '';
            if ($this->handleFailedLogins()) {
                $rep = ' REPEATED';
            }
            $this->monitorFailedLoginAttempts();
            writeLog("*** one time link rejected$rep: $code [".getClientIP().']', LOGIN_LOG_FILENAME);
            $getArg = 'login-failed';
        }
        return $getArg;
    } // validateOnetimeAccessCode



    private function readOneTimeAccessCode($code)
    {
        // checks whether there is a pending oneTimeAccessCode, purges old entries
        require_once SYSTEM_PATH.'ticketing.class.php';

        $tick = new Ticketing();
        $ticket = $tick->consumeTicket($code);
        if (!$ticket) {
            $this->monitorFailedLoginAttempts();
            writeLog("*** one-time link rejected: $code [".getClientIP().']', LOGIN_LOG_FILENAME);
            return false;
        }
        $username = $ticket['username'];
        $userRec = $this->getUserRec( $username );

        return [$userRec, $ticket];
    } // readOneTimeAccessCode



    private function handleOneTimeCodeActions($oneTimeRec)
    {
        $getArg = false;
        $mode = isset($oneTimeRec['mode']) ? $oneTimeRec['mode'] : false;

        if (($mode === 'email-signup') && isset($oneTimeRec['user'])) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this->lzy);
            if (!$adm->createGuestUserAccount($oneTimeRec['user'])) {
                $this->lzy->page->addMessage('lzy-user-account-creation-failed');
            }

        } elseif (($mode === 'email-change-mail') && isset($oneTimeRec['email'])) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this->lzy);
            $adm->changeEMail($oneTimeRec['email']);
            reloadAgent(false, 'email-change-successful');
        }
        return $getArg;
    } // handleOneTimeCodeActions




    private function validateAccessCode($codeCandidate)
    {
        // this is an access code stored in a user's record
        if (!$this->knownUsers) {
            return false;
        }
        foreach ($this->knownUsers as $user => $rec) {
            if (isset($rec['accessCode'])) {
                $code = $rec['accessCode'];
                if (password_verify($codeCandidate, $code)) {
                    if (isset($rec['accessCodeValidUntil'])) {
                        $validUntil = strtotime($rec['accessCodeValidUntil']);
                        if ($validUntil < time()) {
                            return false;
                        }
                    }
                    $this->setUserAsLoggedIn($user, $rec);
                    $requestedUrl = trunkPath($GLOBALS['globalParams']['requestedUrl'],1, false); // remove hash-code from
                    reloadAgent($requestedUrl, 'login-successful');
                }
            }
        }
        return false;
    } // validateAccessCode




    public function getDisplayName()
    {
        if (isset($this->userRec["displayName"])) {
            return $this->userRec["displayName"];
        } else {
            return $this->userRec["name"];
        }
    } // getDisplayName




    public function setUserAsLoggedIn($user = false, $rec = null, $loginEmail = '')
	{
        if ($loggedIn = $this->getLoggedInUser()) {
            return $loggedIn;
        }
	    if (!$user) {
            return false;
        }
	    if (isset($rec['inactive']) && $rec['inactive']) {  // account is inactive, no login allowed
	        return false;
        }
	    if (($rec == null) && ($key = $this->findUserRecKey($user))) {
	        if (!$rec = $this->knownUsers[$key]) {
	            return false;
            }
        }
        $this->userRec = $rec;

        $this->loginTimes[$user] = time();
        session_regenerate_id();
        $_SESSION['lizzy']['user'] = $user;
        $isAdmin = $this->checkAdmission('admins');
        $GLOBALS['globalParams']['isAdmin'] = $isAdmin;
        $_SESSION['lizzy']['isAdmin'] = $isAdmin;
        $_SESSION['lizzy']['loginTimes'] = serialize($this->loginTimes);
        $_SESSION['lizzy']['loginEmail'] = $loginEmail;

        if (isset($rec['displayName'])) {
            $displayName = $rec['displayName']; // displayName from user rec
            $_SESSION['lizzy']['userDisplayName'] = $displayName;

        } else {
            $_SESSION['lizzy']['userDisplayName'] = $user;
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
			if (!$rec) {    // just to be safe: if logged in user has nor record, don't allow to proceed
			    $_SESSION['lizzy']['user'] = false;

            } else {                    // user is logged in
                $res = $user;
                $isAdmin = $this->isAdmin(true);
                $GLOBALS['globalParams']['isAdmin'] = $isAdmin;

                $lastLogin = (isset($this->loginTimes[$user])) ? $this->loginTimes[$user] : 0;  // check maxSessionTime
                if (isset($this->knownUsers[$user]['validity-period'])) {
                    $validityPeriod = $this->knownUsers[$user]['validity-period'];
                    if ($lastLogin < (time() - $validityPeriod)) {
                        $rec = false;
                        $res = [false, '{{ validity-period expired }}', 'LoginForm'];
                        $this->unsetLoggedInUser();
                    }
                } elseif ($this->config->admin_defaultLoginValidityPeriod) {
                    if ($lastLogin < (time() - $this->config->admin_defaultLoginValidityPeriod)) {
                        $rec = false;
                        $res = [false, '{{ validity-period expired }}', 'LoginForm'];
                        $this->unsetLoggedInUser();
                    }
                }
            }
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




    public function getUserRec( $username )
    {
        if (isset($this->knownUsers[$username])) {
            return $this->knownUsers[$username];
        } else {
            return [];
        }
    } // getUserRec




    public function getKnownUsers()
    {
        if (is_array($this->knownUsers)) {
            return $this->knownUsers;
        } else {
            return [];
        }
    } // getKnownUsers




    public function isKnownUser($user, $tolerant = false)
    {
        if ($tolerant) {
            return findUserRecKey($user);
        } else {
            return (is_array($this->knownUsers) && in_array($user, array_keys($this->knownUsers)));
        }
    } // isKnownUser




    public function checkGroupMembership($requiredGroup)
    {
        if ($this->localCall && $this->config->admin_autoAdminOnLocalhost) {	// no restriction
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
		if (($lockProfile == false) || ($this->localCall && $this->config->admin_autoAdminOnLocalhost)) {	// no restriction
			return true;
		}
		
		$rec = $this->userRec;
		if (!$rec) {
		    return false;
        } elseif (!isset($rec['username'])) {
            $rec['username'] = '';
        }

        if ($this->isGroupMember($rec['groups'], 'admins')) { // admins have access by default
		    return true;
        }

		$lockProfiles = explode(',', $lockProfile);
		foreach ($lockProfiles as $lp) {
			$lp = trim($lp);
			if ($lp == $rec['username']) {
				return true;
			} elseif ($lp == $rec['groups']) {
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
        $groupToCheckAgainst = ",$groupToCheckAgainst,";
        $res = (strpos($usersGroups, $groupToCheckAgainst) !== false);
        return $res;
    } // isGroupMember





    //-------------------------------------------------------------
    public function logout()
    {
        $user = getStaticVariable('user');
        $user .= (isset($_SESSION['lizzy']['userDisplayName'])) ? ' ('.$_SESSION['lizzy']['userDisplayName'].')' : '';
        writeLog("logged out: $user [".getClientIP(true).']', LOGIN_LOG_FILENAME);

        $this->unsetLoggedInUser();
    } // logout



    //-------------------------------------------------------------
    public function unsetLoggedInUser($user = '')
    {
        if ($user) {
            $this->loginTimes[$user] = 0;
        }
        $this->userRec = null;
        $_SESSION['lizzy']['user'] = false;
        $_SESSION['lizzy']['userDisplayName'] = false;
        $isAdmin = ($this->localCall && $this->config->admin_autoAdminOnLocalhost);
        $_SESSION['lizzy']['isAdmin'] = $isAdmin ;
        $GLOBALS['globalParams']['isAdmin'] = $isAdmin;
    } // unsetLoggedInUser



    //-------------------------------------------------------------
    private function handleFailedLogins()
    {
        $repeated = false;
        $ip = getClientIP();
        $failedLogins = getYamlFile(FAILED_LOGIN_FILE);     // enforce delay for retries from same ip
        $tnow = time();
        foreach ($failedLogins as $t => $ip1) {
            if ($t < ($tnow - 60)) {
                unset($failedLogins[$t]);
            } elseif ($ip == $ip1) {
                sleep(3);
                $repeated = true;
                unset($failedLogins[$t]);
            }
        }
        $failedLogins[time()] = $ip;
        writeToYamlFile(FAILED_LOGIN_FILE, $failedLogins);
        return $repeated;
    } // handleFailedLogins




    //-------------------------------------------------------------
    public function isValidPassword($password, $password2 = false)
    {
        if ($password2 && ($password != $password2)) {
            return '{{ lzy-change-password-not-equal-response }}';
        }
        if (strlen($password) < 8) {
            return '{{ lzy-change-password-too-short-response }}';
        }
        if (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/\d/', $password) ||
            !preg_match('/[^\w\d]/', $password)) {
            return '{{ lzy-change-password-insufficient-response }}';
        }
        return '';
    }




    //-------------------------------------------------------------
    public function isPrivileged()
    {
        return $this->checkAdmission('admins,editors');
    } // isPrivileged




    //-------------------------------------------------------------
    public function isAdmin($thorough = false)
    {
        if (!$thorough && getStaticVariable('isAdmin')) {
            return true; //??? secure?
        }
        return $this->checkAdmission('admins');
    } // isAdmin



    public function loadKnownUsers()
    {
        $this->userDB = $usersFile = $this->config->configPath.$this->config->admin_usersFile;
        $this->knownUsers = getYamlFile($usersFile);
        foreach ($this->knownUsers as $key => $rec) {
            if (!isset($rec['groups'])) {
                $this->knownUsers[$key]['groups'] = isset($rec['group']) ? $rec['group'] : ''; // make group a synonym for groups
            }
            $this->knownUsers[$key]['username'] = $key;
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
                if ($searchField == '*') {
                    if ($name == $username) {
                        $res = $name;
                        break;
                    }
                    foreach ($rec as $key => $value) {
                        if (strtolower($value) == $username) {
                            $res = $name;
                            break 2;
                        }
                    }
                    continue;
                }
                if ($searchField) {
                    if (isset($rec[$searchField]) && (strtolower($rec[$searchField]) == $username)) {
                        $res = $name;
                        break;
                    }
                } elseif ($name == $username) {
                    $res = $name;
                    break;

                } elseif (isset($rec['username']) && ($rec['username'] == $username)) {
                    $res = $name;
                    break;

                } elseif ($tolerant && isset($rec['displayName']) && (strtolower($rec['displayName']) == $username)) {
                    $res = $name;
                    break;
                }
            }
        }
        return $res;
    } // findUserRecKey




    private function findEmailMatchingUserRec($searchKey, $checkInEmailList = false)
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




    private function sendOneTimeCode($submittedEmail, $rec)
    {
        $accessCodeValidyTime = isset($rec['accessCodeValidyTime']) ? $rec['accessCodeValidyTime'] : $this->config->admin_defaultAccessLinkValidyTime;

        require_once SYSTEM_PATH.'admintasks.class.php';
        $adm = new AdminTasks($this->lzy);
        $message = $adm->sendCodeByMail($submittedEmail, 'email-login', $accessCodeValidyTime, $rec);

        return $message;
    } // sendOneTimeCode





    public function findEmailInEmailList(string $submittedEmail)
    {
        $found = false;
        $submittedEmail = strtolower($submittedEmail);
        foreach ($this->knownUsers as $name => $rec) {
            if (isset($rec['accessCodeEnabled']) && ($rec['accessCodeEnabled'] == false)) {
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




    private function handleOnetimeLoginRequest()
    {
        $emailRequest = $_POST['lzy-onetimelogin-request-email'];

        list($emailRequest, $rec) = $this->findEmailMatchingUserRec($emailRequest, true);
        if ($emailRequest) {
            if (isset($rec['inactive']) && $rec['inactive']) {  // account set to inactive?
                writeLog("Account '{$rec['username']}' is inactive: $emailRequest", LOGIN_LOG_FILENAME);
                $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];

            } elseif (!is_legal_email_address($emailRequest)) { // valid email address?
                writeLog("invalid email address in rec '{$rec['username']}': $emailRequest", LOGIN_LOG_FILENAME);
                $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];   //
            } else {
                $uname = $rec['username'];
                $displayName = $this->getDisplayName();
                $uname = $displayName ? "$uname ($displayName)" : $uname;
                $message = $this->sendOneTimeCode($emailRequest, $rec);
                writeLog("one time link sent to: $uname", LOGIN_LOG_FILENAME);

                $res = [false, $message, 'Overlay'];   // if successful, a mail with a link has been sent and user will be authenticated on using that link
            }
        } else {
            $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];   //
        }
        return $res;
    } // handleOnetimeLoginRequest




    /**
     * @param $res
     */
    private function handleLoginUserNotification($res): void
    {
        if (is_array($res) && isset($res[2])) { // [login/false, message, communication-channel]
            if ($res[2] == 'Overlay') {
                $this->lzy->page->addOverlay($res[1], false, false);

            } elseif ($res[2] == 'Override') {
                $this->lzy->page->addOverlay($res[1], false, false);

            } elseif ($res[2] == 'LoginForm') {
                $accForm = new UserAccountForm($this);
                $form = $accForm->renderLoginForm($this->message, $res[1], true);
                $this->lzy->page->addOverlay($form, true, false);

            } else {
                $this->lzy->page->addMessage($res[1], false, false);
            }
        }
    } // handleLoginUserNotification



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
                writeLog("!!!!! Possible hacking attempt [".getClientIP().']', LOGIN_LOG_FILENAME);
                sleep(5);
            }
        }
        file_put_contents(HACK_MONITORING_FILE, $out);

    } // monitorFailedLoginAttempts


} // class Authentication
