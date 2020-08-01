<?php

require_once SYSTEM_PATH.'ticketing.class.php';

define('DEFAULT_INVITATION_LINK_VALIDITY_TIME', 604800);

class AdminTasks
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->auth = $lzy->auth;
        $this->page = $lzy->page;
        $this->trans = $lzy->trans;
        $this->loggedInUser = $this->auth->getLoggedInUser();

        $this->trans->readTransvarsFromFile('~sys/config/admin.yaml');
        $this->trans->readTransvarsFromFile('~sys/config/useradmin.yaml');
        $this->userAdminInitialized = file_exists(CONFIG_PATH.$this->lzy->config->admin_usersFile);
    }




    public function handleAdminRequests($requestType)
    {
        $res = false;
        if ($requestType === 'add-users') {                // admin is adding users
            if (!$this->auth->isAdmin()) { return false; }
            $emails = get_post_data('lzy-add-users-email-list');
            $group = get_post_data('lzy-add-user-group');
            $str = $this->addUsers($emails, $group);
            $res = [false, $str, 'Override'];

        } elseif ($requestType === 'add-user') {           // admin is adding a user
            if (!$this->auth->isAdmin() && $this->userAdminInitialized) { return false; }
            $email = get_post_data('lzy-add-user-email');
            $pw = get_post_data('lzy-add-user-password-password');
            if ($pw) {
                $pw = password_hash($pw, PASSWORD_DEFAULT);
            } else {
                $pw = '';
            }
            $un = get_post_data('lzy-add-user-username');
            $key = ($un) ? $un : $email;
            $rec[$key] = [
                'email' => $email,
                'groups' => get_post_data('lzy-add-user-group'),
                'username' => $un,
                'password' => $pw,
                'displayName' => get_post_data('lzy-add-user-displayname'),
                'emaillist' => get_post_data('lzy-add-user-emaillist')
            ];
            $str = $this->addUser($rec);
            $res = [false, $str, 'Message'];


        } elseif ($requestType === 'lzy-invite-user-email') {           // admin requested invitation mail to new user:
            if ($this->lzy->config->admin_enableSelfSignUp) {
                $emails = get_post_data('lzy-invite-user-email-addresses');
                $result = $this->sendInvitationByMails($emails, DEFAULT_INVITATION_LINK_VALIDITY_TIME);
                $result = str_replace('<', '&lt;', $result);
                $msg = <<<EOT
                    <h1 style="margin: 2em 0 1em;">Sending invitations:</h1>
                    <pre>\n$result</pre>
                    <div style="margin: 2em 0">
                        <a href='{$GLOBALS["globalParams"]["pageUrl"]}'>Continue...</a>
                    </div>
EOT;
            } else {
                $msg = "Feature admin_enableSelfSignUp not enabled";
            }
            $res = [false, $msg, 'Override'];

        } else {
            $user = $this->loggedInUser;
            $userRec = $this->auth->getLoggedInUser( true );
            if (!(isset($userRec['locked']) && $userRec['locked'])) {
                if (isset($userRec['groupAccount']) && $userRec['groupAccount'] &&
                    isset($_SESSION["lizzy"]["loginEmail"])) {
                    // if a member of a group account wants to change profile -> create new sub-account
                    $email = $_SESSION["lizzy"]["loginEmail"];
                    $key = $_SESSION["lizzy"]["loginEmail"];
                    $newRec[$key] = [
                        'email' => $email,
                        'groups' => $userRec["groups"],
                        'username' => $key,
                        'derivedFrom' => $user
                    ];
                    $this->addUser( $newRec );
                    $user = $email;
                    $this->auth->loadKnownUsers();
                    $this->auth->logout();
                    $res = $this->auth->setUserAsLoggedIn($email, $newRec);
                }

                if ($requestType === 'change-password') {           // user is changing his/her password:
                    $password = get_post_data('lzy-change-password-password');
                    $password2 = get_post_data('lzy-change-password2-password2');
                    $res = $this->auth->isValidPassword($password, $password2);
                    if ($res == '') {
                        $str = $this->changePassword($user, $password);
                        $res = [false, $str, 'Message'];

                    } else {
                        $this->message = "<div class='lzy-adduser-wrapper'>$res</div>";
                        $accountForm = new UserAccountForm($this->lzy);
                        $str = $accountForm->createChangePwForm($user, $this->message, "<h1>{{ lzy-change-password-title }}</h1>");
                        $res = [false, $str, 'Override'];
                    }


                } elseif ($requestType === 'lzy-change-username') {        // user changes username
                    $username = get_post_data('lzy-change-user-username');
                    $displayName = get_post_data('lzy-change-user-displayname');
                    $str = $this->changeUsername($username, $displayName);
                    $this->lzy->page->addMessage($str);


                } elseif ($requestType === 'lzy-change-email') {           // user changes mail address
                    $email = get_post_data('lzy-change-user-request-email');
                    $str = $this->sendChangeMailAddress_Mail($email);
                    $res = [false, $str, 'Override'];


                } elseif ($requestType === 'lzy-delete-account') {           // user deletes account
                    $str = $this->deleteUserAccount();
                    $this->auth->logout();
                    $res = [false, $str, 'Message'];
                }
            }
        }

        if ($res) {
            if (isset($res[2]) && ($res[2] === 'Overlay')) {
                $this->page->addOverlay($res[1], false, false);
            } elseif ($res[2] === 'Override') {
                $this->page->addOverride($res[1], false, false);
            } else {
                $this->page->addMessage($res[1], false, false);
            }
        }
    } // handleAdminRequests




    public function handleAdminRequests2($adminTask)
    {
        $notification = getStaticVariable('lastLoginMsg');
        $group = getUrlArg('group', true);

        $accountForm = new UserAccountForm($this->lzy);
        if (!$this->loggedInUser) {
            // everyone who is not logged in:
            if ($adminTask === 'login') {
                $pg = $accountForm->renderLoginForm($notification);

            } elseif (($adminTask === 'self-signup') && $this->lzy->config->admin_enableSelfSignUp) {
                $pg = $accountForm->renderSignUpForm($notification);
                return $pg;
            }
            if (!$GLOBALS['globalParams']['isAdmin'] && $this->userAdminInitialized) {
                return false;
            }
        }



        // for logged in users of any group:
        if ($adminTask === 'change-password') {
            $pg = $accountForm->renderChangePwForm($this->loggedInUser, $notification);
            $this->page->merge($pg);
            return $pg;

        } elseif ($this->lzy->config->admin_userAllowSelfAdmin && ($adminTask === 'edit-profile')) {
            $userRec = $this->auth->getLoggedInUser(true);
            $html = $accountForm->renderEditProfileForm($userRec, $notification);
            $this->page->addOverride($html, true, false);
            $this->page->addModules('PANELS');
            $jq = "initLzyPanel('.lzy-panels-widget', 1);";
            $this->page->addJq( $jq );
            return '';

        } elseif ($adminTask === 'invite-new-user') {
            $html = $accountForm->renderInviteUserForm($notification);
            $this->page->addOverride($html, true, false);
            return '';
        }


//???
        if (!$GLOBALS['globalParams']['isAdmin'] && $this->userAdminInitialized) {
            return '';
        }

        // only Admins permitted beyond this point
        if ($adminTask === 'add-users') {
            $pg = $accountForm->renderAddUsersForm($group, $notification);

        } elseif ($adminTask === 'add-user') {
            $pg = $accountForm->renderAddUserForm($group, $notification);
            $jq = "setTimeout(function() { $('#lzy-login-email3').focus(); console.log('focus');}, 500);\n";
            if (isset($_GET['lzy-preset-email'])) {
                $email = $_GET['lzy-preset-email'];
                $jq .= "$('input[name=lzy-add-user-email]').val('$email');\n";
            }
            if (isset($_GET['lzy-preset-groups'])) {
                $groups = $_GET['lzy-preset-groups'];
                $jq .= "$('input[name=lzy-add-user-group]').val('$groups');\n";
            }
            if ($jq) {
                $this->page->addJq($jq);
            }

        } else {
            return "Error: mode unknown 'adminTask'";
        }
        $override = $pg->get('override', true);
        $override['mdCompile'] = false;
        $this->page->addOverride($override);
        $this->page->addModules('USER_ADMIN');

    } // handleAdminRequests2





    //....................................................
    public function sendSignupMail($email, $group = 'guest')
    {
        $accessCodeValidyTime = $this->lzy->config->admin_defaultAccessLinkValidyTime;
        list($message) = $this->sendCodeByMail($email, 'email-signup', $accessCodeValidyTime, 'unknown-user', false);
        return $message;
    } // sendSignupMail




    //....................................................
    public function sendChangeMailAddress_Mail($email)
    {
        $accessCodeValidyTime = $this->lzy->config->admin_defaultAccessLinkValidyTime;
        $userRec = $this->auth->getLoggedInUser(true);
        list($message) = $this->sendCodeByMail($email, 'email-change-mail', $accessCodeValidyTime, $userRec);
        return $message;
    } // sendChangeMailAddress_Mail




    //....................................................
    public function addUsers($emails, $group)
    {
        $lines = explode("\n", $emails);
        $newRecs = [];
        foreach ($lines as $line)
        {
            if (preg_match('/(.*)\<(.*)\>/', $line, $m)) {
                $name = trim($m[1]);
                $email = $m[2];
            } elseif (preg_match('/.*\@.*\..*/', $line)) {
                $name = '';
                $email = $line;
            } else {
                continue;
            }
            $newRecs[$email] = ['email' => $email, 'displayName' => $name, 'groups' => $group];
        }

        if ($newRecs) {
            $str = '';
            foreach ($newRecs as $rec) {
                $str .= "<li><span class='lzy-adduser-mail'>{$rec['email']}</span> [<span class='lzy-adduser-name'>{$rec['displayName']}</span>]</li>\n";
            }
            $str = "<div class='lzy-adduser-wrapper lzy-adduser-response'>\n<div>{{ lzy-add-users-response }} <strong>$group</strong> {{ lzy-add-users-response2 }}:</div>\n<ul>$str</ul>\n</div>\n";
            $this->addUsersToDB($newRecs);

        } else {
            $str = "<div class='lzy-adduser-wrapper lzy-adduser-response'>{{ lzy-add-users-none-added }} <strong>$group</strong> {{ lzy-add-users-none-added2 }</div>";
        }
        return $str;
    }



    //....................................................
    public function signupUser($rec)
    {
        $username = get_post_data('lzy-self-signup-user-username');
        $pw = get_post_data('lzy-self-signup-user-password');

        if (!$username || !$pw) {
            $str = "<div class='lzy-adduser-wrapper lzy-adduser-response'>{{ lzy-add-user-data-missing }}</div>";
            return $str;
        }
        $res = $this->checkPasswordQuality($pw);
        if ($res) {
            return "<div class='lzy-adduser-wrapper lzy-adduser-response lzy-error'>{{ lzy-insufficient-password }}: $pw</div>";
        }

        $userRec = [
            'password' => password_hash($pw, PASSWORD_DEFAULT),
            'username' => $username,
            'email' => $rec['email'],
            'displayName' => $rec['displayName'],
            'groups' => $rec['groups'],
        ];
        $acStr = $acAnnouncement = '';
        if (isset($rec['accessCode'])) {
            $userRec['accessCode'] = $rec['accessCode'];
            $href = $GLOBALS["globalParams"]["absAppRootUrl"].$rec['accessCode'];
            $link = "<a class='lzy-user-self-signup-access-link' href='$href'>$href</a>";
            $acStr = "\t\t\t\t<div><span>{{ lzy-user-self-signup-access-link }}:</span><span>$link</span></div>\n";
            $acExplanation = "\t\t\t\t{{ lzy-user-self-signup-access-code1 }}\n";
        }
        $res = $this->addUserToDB($username, $userRec);
        if ($res === true) {
            $str = <<<EOT
            <div class='lzy-adduser-wrapper lzy-adduser-response'>
                <h1>{{ lzy-add-user-response }}</h1> 
                <div><span>{{ lzy-user-self-signup-username }}:</span><span>{$userRec['username']}</span></div>
                <div><span>{{ lzy-user-self-signup-password }}:</span><span>{$pw}</span></div>
                <div><span>{{ lzy-user-self-signup-email }}:</span><span>{$userRec['email']}</span></div>
                <div><span>{{ lzy-user-self-signup-displayname }}:</span><span>{$userRec['displayName']}</span></div>
$acStr
$acExplanation
            </div>
            <p><a href="{$GLOBALS["globalParams"]["absAppRootUrl"]}">{{ lzy-form-continue }}</a> </p>
EOT;

        } else {
            $str = "<div class='lzy-adduser-wrapper lzy-adduser-response lzy-error'>{{ $res }}: $username</div>";
        }
        return $str;
    } // signupUser



    //....................................................
    public function addUser($rec)
    {
        $this->addUsersToDB($rec);
        $rec = array_pop($rec); //???
        $str = "<div class='lzy-adduser-wrapper lzy-adduser-response'>{{ lzy-add-user-response }}: {$rec['email']}</div>";
        return $str;
    }



    //....................................................
    public function changePassword($user, $password)
    {
        $str = '';
        $knownUsers = $this->auth->getKnownUsers();
        if (isset($knownUsers[$user])) {
            $this->updateDbUserRec($user, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-password-changed-response }}</div>";
        }
        return $str;
    }



    //....................................................
    public function changeUsername($newUsername, $displayName)
    {
        $rec = $this->auth->getLoggedInUser( true );
        $user = $rec['username'];

        if (is_legal_email_address($user) && !isset($rec['email'])) {
            $rec['email'] = $user;
        }
        if (!$newUsername && !$displayName) {
            return "<div class='lzy-admin-task-response'>{{ lzy-username-change-no-change-response }}</div>";
        }
        if ($user === $newUsername) {
            if (!$displayName) {
                return "<div class='lzy-admin-task-response'>{{ lzy-username-change-no-change-response }}</div>";
            }
            $newUsername = '';
        }

        if (!$newUsername) {
            $newUsername = $user;
            $res = false;
        } else {
            $newUsername = strtolower($newUsername);
            $res = $this->isInvalidUsername($newUsername);
        }
        if ($res) { // user name already in use or invalid!
            $str = "<div class='lzy-admin-task-response'>$res</div>";
            return $str;
        }
        if ($user !== $rec['username']) {
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-change-illegal-name-response }}</div>";

        } else {
            if ($displayName) {
                if (($dn = $this->auth->findUserRecKey($displayName, 'displayName')) && ($dn !== $newUsername)) {
                    return "<div class='lzy-admin-task-response'>{{ lzy-username-change-illegal-displayname-response }}</div>";
                } else {
                    $rec['displayName'] = $displayName;
                }
            }
            $this->deleteDbUserRec($user);
            $rec['username'] = $newUsername;
            $this->addUserToDB($newUsername, $rec);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-changed-response }}</div>";
            $this->auth->setUserAsLoggedIn($newUsername, $rec);
            $this->auth->loadKnownUsers();
        }
        return $str;
    } // changeUsername



    private function isInvalidUsername($username) {
        if ($username === 'admin') {
            return '{{ lzy-username-changed-error-name-taken }}';

        } elseif ($res = $this->auth->findUserRecKey($username, '*')) {
            return '{{ lzy-username-changed-error-name-taken }}';

        } elseif ($res = $this->auth->findEmailInEmailList($username)) {
            return '{{ lzy-username-changed-error-name-taken }}';
        }

        if (!preg_match('/^\w{2,15}$/', $username)) {
            return '{{ lzy-username-changed-error-illegal-name }}';
        }
        return false;
    } // checkValidUsername



    public function deleteUserAccount($user = false)
    {
        if (!$user) {
            if (!$user = $this->loggedInUser) {
                return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-failed-response }}</div>";
            }
        } else {
            if (!$this->auth->isAdmin()) {
                return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-failed-response }}</div>";
            }
        }
        $this->deleteDbUserRec($user);
        $this->auth->logout();
        return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-success-response }}</div>";
    }



    public function createGuestUserAccount($email)
    {
        $group = 'guest';
        if (isset($_SESSION['lizzy']['self-signup-to-group'])) {
            $group = $_SESSION['lizzy']['self-signup-to-group'];
            unset($_SESSION['lizzy']['self-signup-to-group']);
        }

        // for security: self-signup for admin-group only possible as long as there
        // no accounts registered at all, i.e. only the first signup may become admin:
        $knownUsers = $this->auth->getKnownUsers();
        if ($this->auth->findUserRecKey($email)) {
            writeLog("sending invitation: user '$email' already exists.");
            return false;
        }

        if ($group === 'admin') {
            if (sizeOf($knownUsers) > 0) {
                writeLog("self-signup for group admin blocked - only allowed if there are NO accounts defined yet!");
                return false;

            } else {
                $this->addUsersToDB([ $email => ['email' => $email, 'groups' => $group]]);
                writeLog("new admin user added: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
                return true;
            }

        } else {
            $this->addUsersToDB([ $email => ['email' => $email, 'groups' => $group]]);
            writeLog("new guest user added: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
            return true;
        }
    } // createGuestUserAccount



    public function renderCreateUserForm( $rec )
    {
        $accountForm = new UserAccountForm($this->lzy);

        $override = $accountForm->renderCreateUserForm($rec);
        return $override;
    } // renderCreateUserForm




    public function changeEMail($email)
    {
        if ($res = $this->isInvalidEmailAddress($email)) {
            return $res;
        }
        $email = strtolower($email);
        $userRec = $this->auth->getLoggedInUser( true );
        $oldEmail = isset($userRec['email']) ? $userRec['email'] : '';
        $user = $userRec['username'];
        $userRec['email'] = $email;
        if (is_legal_email_address($user) && ($oldEmail === $user)) {
            $this->deleteDbUserRec($user);
            $userRec['username'] = $user = $email;
            $this->addUserToDB($user, $userRec);
        } else {
            $this->updateDbUserRec($user, $userRec);
        }
        $this->auth->loadKnownUsers();
        $this->auth->setUserAsLoggedIn($user, $userRec);
        writeLog("email for user changed: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
    } // changeEMail





    private function isInvalidEmailAddress($email) {
        if (!is_legal_email_address( $email )) {
            return 'email-changed-email-invalid';
        }
        if ($res = $this->auth->findUserRecKey($email, '*')) {
            return 'email-changed-email-in-use';

        }
        if ($res = $this->auth->findEmailInEmailList($email)) {
            return 'email-changed-email-in-use';
        }

        return false;
    } // isInvalidEmailAddress




    //-------------------------------------------------------------
    private function sendMail($to, $subject, $message)
    {
        if (strpos($subject, '{{') !== false) {
            $subject = $this->lzy->trans->translate($subject);
        }
        if (strpos($message, '{{') !== false) {
            $message = $this->lzy->trans->translate($message);
        }
        return $this->lzy->sendMail($to, $subject, $message, false);
    } // sendMail



    private function addUserToDB($username, $userRec)
    {
        $knownUsers = $this->auth->getKnownUsers();
        if (!$username || !is_array($userRec)) {
            return 'Bad parameters';
        }
        if (isset($knownUsers[$username])) {
            return 'lzy-username-already-taken';
        }
        $knownUsers[$username] = $userRec;
        writeToYamlFile($this->auth->userDB, $knownUsers);
        return true;
    } // addUserToDB




    private function addUsersToDB($userRecs)
    {
        if (!is_array($userRecs)) {
            return false;
        }
        $knownUsers = $this->auth->getKnownUsers();
        foreach ($userRecs as $un => $rec) {
            if (isset($knownUsers[$un])) {
                return false;
            }
            $knownUsers[$un] = $rec;
        }
        writeToYamlFile($this->auth->userDB, $knownUsers);
        return true;
    } // addUsersToDB




    private function deleteDbUserRec($user)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (isset($userRecs[$user])) {
            unset($userRecs[$user]);
            writeToYamlFile($this->auth->userDB, $userRecs);
            $this->auth->knownUsers = $userRecs;
        }
    } // deleteDbUserRec



    private function updateDbUserRec($user, $rec)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (!isset($userRecs[$user])) {
            return false;
        }
        $userRec = &$userRecs[$user];
        foreach ($rec as $k => $v) {
            $userRec[$k] = $v;
        }
        writeToYamlFile($this->auth->userDB, $userRecs);
        return true;
    } // updateDbUserRec




    public function sendCodeByMail($submittedEmail, $mode, $accessCodeValidyTime, $userRec = false)
    {
        global $globalParams;

        $message = '';
        $validUntil = time() + $accessCodeValidyTime;
        $validUntilStr = strftime('%R  (%x)', $validUntil);

        $user = isset($userRec['username']) ? $userRec['username'] : '';
        if (isset($userRec['displayName'])) {
            $displayName = $userRec['displayName'];
        } else {
            $displayName = $submittedEmail;
        }

        $tick = new Ticketing();

        $otRec = ['username' => $user, 'email' => $submittedEmail,'mode' => $mode];
        $hash = $tick->createTicket($otRec, 1, $accessCodeValidyTime);

        $url = $globalParams['pageUrl'] . $hash . '/';
        if ($mode === 'email-login') {
            $subject = "[{{ site_title }}] {{ lzy-email-access-link-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-access-link0 }}$displayName{{ lzy-email-access-link1 }} $url {{ lzy-email-access-link2 }} $hash {{ lzy-email-access-link3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($user, $validUntilStr, 'lzy-onetime access link');
            writeLog("one time link sent to: $submittedEmail -> '$hash'", LOGIN_LOG_FILENAME);

        } elseif ($mode === 'email-signup') {
            $subject = "[{{ site_title }}] {{ lzy-email-sign-up-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-sign-up1 }} $url {{ lzy-email-sign-up2 }} $hash {{ lzy-email-sign-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-sign-up-link');

        } elseif ($mode === 'email-change-mail') {
            if (isset($userRec['email']) && ($userRec['email'] === $submittedEmail)) {
                reloadAgent(false,"email-change-mail-unchanged");
            }
            $res = $this->isInvalidEmailAddress($submittedEmail);
            if ($res) {
                reloadAgent( false, $res );
            }
            $subject = "[{{ site_title }}] {{ lzy-email-change-mail-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-change-mail-up1 }} $url {{ lzy-email-change-mail-up2 }} $hash {{ lzy-email-change-mail-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-change-mail-link');
        }
        return [$message, $displayName];
    } // sendCodeByMail




    //....................................................
    public function sendInvitationByMails($submittedEmail, $accessCodeValidyTime)
    {
        global $globalParams;

        $groups = get_post_data('lzy-invite-user-groups');
        $subject = get_post_data('lzy-invite-user-subject');
        $text = get_post_data('lzy-invite-user-mailtext-');
        $text = str_replace(' BR ', "\n", $text);

        $out = '';
        $validUntil = time() + $accessCodeValidyTime;
        $validUntilStr = strftime('%x', $validUntil);

        if (strpos($submittedEmail, ",") !== false) {
            $submittedEmail = str_replace(',', ';', $submittedEmail);
        }
        $emails = explodeTrim(';', $submittedEmail);

        foreach ($emails as $email) {
            $name = '';
            if (preg_match('/^(.*)<(.*)>/', $email, $m)) {
                $name = trim($m[1]);
                $email = $m[2];
            }
            if ($res = $this->auth->findUserRecKey($email, '*')) {
                $out .= "email already in use: $email\n";
                continue;
            }

            $tick = new Ticketing();

            $otRec = ['username' => '', 'displayName' => $name, 'email' => $email, 'mode' => 'user-signup-invitation', 'groups' => $groups];
            $ac = get_post_data('lzy-invite-user-create-hash-');
            if ($ac === 'on') {
                $otRec['accessCode'] = $tick->createHash();
            }
            $hash = $tick->createTicket($otRec, 1, $accessCodeValidyTime);
//$hash = $tick->createTicket($otRec, 100, $accessCodeValidyTime);

            $url = $globalParams['pageUrl'] . $hash . '/';
            $subject = str_replace(['{site_title}', '{lzy-invitation-default-subject}', '{lzy-invitation-host}'],
                    ['{{ site_title }}', '{{ lzy-signup-invitation-subject }}', $globalParams['host']], $subject);
            $message = str_replace(['{lzy-invitation-link}', '{lzy-invitation-expiry-date}'], [$url, $validUntilStr], $text);

            $ok = $this->sendMail($email, $subject, $message);
            if ($ok) {
                $out .= "sent: $email\n";
            } else {
                $out .= "failed: $email\n";
            }
        }

        return $out;
    } // sendInvitationByMails




    //....................................................
    public function sendAccessLinkMail()
    {
        if ($pM = $this->auth->getPendingMail()) {

            $headers = "From: {$pM['from']}\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $subject = $this->trans->translate( $pM['subject'] );
            $message = $this->trans->translate( $pM['message'] );

            if ($this->localCall) {
                $this->page->addOverlay("<pre class='debug-mail'><div>Subject: $subject</div>\n<div>$message</div></pre>");
                $this->page->addJq("$( 'body' ).keydown( function (e) {if (e.which === 27) { $('.overlay').hide(); } });");
            } else {
                if (!mail($pM['to'], $subject, $message, $headers)) {
                    fatalError("Error: unable to send e-mail", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
            }
        }
    } // sendAccessLinkMail



    private function checkPasswordQuality($password)
    {
        if (!$this->lzy->admin_enforcePasswordQuality)  {
            return false;
        }
        // min length:
        if (strlen($password) < 6) {
            return 'lzy-password-too-short';
        }
        if (!preg_match('/[A-Z]/', $password )) {
            return 'lzy-password-without-uppercase-letter';
        }
        if (!preg_match('/\W/', $password )) {
            return 'lzy-password-without-special-character';
        }
        if (!preg_match('/\d/', $password )) {
            return 'lzy-password-without-digit';
        }
        return false;
    } // checkPasswordQuality

} // class