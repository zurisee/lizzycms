<?php

if (!defined('SHOW_PW_INFO_ICON')) {
    define('SHOW_PW_INFO_ICON', '<span class="lzy-icon lzy-icon-info"></span>');
    define('MSG', 'lzy-account-form-message');
    define('NOTI', 'lzy-account-form-notification');
}
$GLOBALS['lizzy']['adminFormsCounter'] = 0;


class UserAdminBase
{
    public function __construct( $lzy = null )
    {
        if ($lzy) {
            $this->lzy = $lzy;
            $this->config = $lzy->config;
            $this->page = &$lzy->page;
            $this->auth = $lzy->auth;
            if (isset($lzy->trans)) {      //??? hack -> needs to be cleaned up: invoked from diff places
                $this->trans = $lzy->trans;
            } else {
                $this->trans = $lzy;
            }
        } else {
            $this->lzy = null;
            $this->config = null;
            $this->page = null;
            $this->auth = null;
            $this->trans = null;
        }
        $this->loggedInUser = $this->auth->getUsername();
        $GLOBALS['lizzy']['adminFormsCounter']++;
        $this->inx = $GLOBALS['lizzy']['adminFormsCounter'];

    } // __construct



    public function handleRequests()
    {
        // check action requests related to EditProfile:
        $editProfileCmds = [
            'lzy-change-password',
            'lzy-change-username',
            'lzy-change-email-request',
            'lzy-change-email-confirm',
            'lzy-change-email',
            'lzy-create-accesslink',
            'lzy-delete-account',
            'lzy-create-accesslink',
            'lzy-delete-accesslink',
            'lzy-delete-account'
        ];
        if (array_intersect( array_keys($_REQUEST), $editProfileCmds )) {
            $this->trans->readTransvarsFromFile('~sys/'.LOCALES_PATH.'/admin.yaml', false, true);
            $this->checkInsecureConnection();
            $this->page->addModules('USER_ADMIN, POPUPS');

            require_once ADMIN_PATH . 'user-edit-profile.class.php';
            $this->usrEd = new UserEditProfileBase($this->lzy);
        }
    } // handleRequests



    protected function renderOnetimeLinkEntryForm($user, $validUntilStr, $prefix)
    {
        $form = <<<EOT

    <div class='lzy-onetime-link-sent'>
    {{ $prefix sent }}

    <form class="lzy-onetime-code-entry" method="post">
        <label for="">{{ lzy-enter onetime code }}</label>
        <input type="hidden" value="$user" name="lzy-login-user" />
        <input id="lzy-onetime-code" type="text" name="lzy-onetime-code" style="text-transform:uppercase;width:6em;" />
        <input type="submit" class='lzy-button lzy-admin-submit-button' value="{{ submit }}" />
    </form>

    <p> {{ $prefix sent2 }} $validUntilStr</p>
    <p> {{ $prefix sent3 }}</p>
    {{^ lzy-sign-up further info }}
    </div>

EOT;
        return $form;
    } // renderOnetimeLinkEntryForm



    protected function checkInsecureConnection()
    {
        global $globalParams;
        $relaxedHosts = str_replace('*', '', $this->config->site_allowInsecureConnectionsTo);

        if (!$this->config->isLocalhost && !(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on')) {
            $url = str_ireplace('http://', 'https://', $globalParams['pageUrl']);
            $url1 = preg_replace('|https?://|i', '', $globalParams['pageUrl']);
            if (strpos($url1, $relaxedHosts) !== 0) {
                $this->page->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
            }
            return false;
        }
        return true;
    } // checkInsecureConnection



    protected function wrapTag($className, $str)
    {
        if (($className === MSG) && isset($GLOBALS['globalParams']['auth-message'])) {
            $str .= ' '.$GLOBALS['globalParams']['auth-message'];
        }
        if ($str) {
            $str = "\t\t\t<div class='$className'>$str</div>\n";
        }
        return $str;
    } // wrapTag



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

        $tick = new Ticketing(['unambiguous' => true, 'defaultType' => 'ot-access-ticket']);

        $otRec = ['username' => $user, 'email' => $submittedEmail,'mode' => $mode];
        $hash = $tick->createTicket($otRec, 1, $accessCodeValidyTime);

        $url = $globalParams['pageUrl'] . $hash . '/';
        if ($mode === 'email-login') {
            $subject = "[{{ site_title }}] {{ lzy-email-access-link-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-access-link0 }}$displayName{{ lzy-email-access-link1 }} $url {{ lzy-email-access-link2 }} $hash {{ lzy-email-access-link3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $message = $this->renderOnetimeLinkEntryForm($user, $validUntilStr, 'lzy-onetime access link');
            $this->lzy->loginFormRequiredOverride = false;
            writeLogStr("one time link sent to: $submittedEmail -> '$hash'", LOGIN_LOG_FILENAME);

        } elseif ($mode === 'email-signup') {
            $subject = "[{{ site_title }}] {{ lzy-email-sign-up-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-sign-up1 }} $url {{ lzy-email-sign-up2 }} $hash {{ lzy-email-sign-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);
            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-sign-up-link');

        } elseif ($mode === 'lzy-change-email-request') {
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
            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-change-mail-link');
        }
        return [$message, $displayName];
    } // sendCodeByMail



    protected function sendMail($to, $subject, $message)
    {
        if (strpos($subject, '{{') !== false) {
            $subject = $this->lzy->trans->translate($subject);
        }
        if (strpos($message, '{{') !== false) {
            $message = $this->lzy->trans->translate($message);
        }
        $message = str_replace(['\\n', '\\t'], ["\n", "\t"], $message);
        return $this->lzy->sendMail($to, $subject, $message, false);
    } // sendMail



    public function updateDbUserRec($user, $rec, $overwrite = false)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (!isset($userRecs[$user])) {
            return false;
        }
        if ($overwrite) {
            $userRecs[$user] = $rec;
        } else {
            $userRec = &$userRecs[$user];
            foreach ($rec as $k => $v) {
                $userRec[$k] = $v;
            }
        }
        writeToYamlFile($this->auth->userDB, $userRecs);
        return true;
    } // updateDbUserRec



    protected function isInvalidUsername($username) {
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
    } // isInvalidUsername



    protected function deleteDbUserRec($user)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (isset($userRecs[$user])) {
            unset($userRecs[$user]);
            writeToYamlFile($this->auth->userDB, $userRecs);
            $this->auth->loadKnownUsers();
        }
    } // deleteDbUserRec



    protected function addUserToDB($username, $userRec)
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



    protected function isInvalidEmailAddress($email) {
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

} // UserAdminBase