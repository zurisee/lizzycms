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



    public function createAccessCodeForUser($user)
    {
        if (!$user) {
            $msg = "{{ lzy-create-accesscode-needs-username }}";
        } elseif (!$this->auth->isKnownUser($user)) {
            $msg = "{{ lzy-create-accesscode-user-unknown }} $user";
        } else {
            $tick = new Ticketing();
            $code = $tick->createHash();
            $this->updateDbUserRec($user, ['accessCode' => $code]);
            $msg = "<div class='lzy-admin-task-response'><h3>Access-Code</h3>".
                "<p>{{ lzy-access-code-changed-response }}: $user</p>".
                "<p><strong>$code</strong></p></div>";
        }
        $this->page->addOverlay(['text' => $msg, 'closable' => 'reload', 'mdCompile' => true]);

    } // createAccessCodeForUser



    public function handleCreateTicketRequest()
    {
        if (!$_POST) {
            $form = $this->renderCreateTicketForm();
            $this->page->addOverlay(['text' => $form, 'closable' => 'reload']); // close shall reload page to remove url-arg
            $jq = <<<EOT

$('#lzy-create-ticket-panel').addClass('lzy-tilted');
lzyPanels[ lzyPanelWidgetInstance ] = new LzyPanels();
lzyPanels[ lzyPanelWidgetInstance ].init( '#lzy-create-ticket-panel', 1 );
lzyPanelWidgetInstance++;

EOT;
            $this->page->addJq( $jq );
            $this->page->addModules( 'PANELS' );
        } else {

            $n = get_post_data('max_accesses');
            if (!$n) {
                $n = 1;
            }
            $user = get_post_data('user');
            if (!$user) {
                $user = 'guest';
            }
            $group = get_post_data('group');
            if (!$group) {
                $group = 'guests';
            }
            $page = get_post_data('lzy-selected-page');
            $pgRec = $this->lzy->siteStructure->findSiteElem( $page, true, true );
            $folder = ($pgRec['urlpath'] !== false) ? $pgRec['urlpath']: $pgRec['folder'];
            $link = $GLOBALS['globalParams']['absAppRootUrl'] . $folder;
            if (@$_POST['util']) {
                $accessCodeValidyTime = strtotime($_POST['util']) - time();

            } else {
                $accessCodeValidyTime = get_post_data('duration');
                $unit = @$_POST['unit'];
                if ($unit === 'minutes') {
                    $accessCodeValidyTime = 60 * $accessCodeValidyTime;

                } elseif ($unit === 'hours') {
                    $accessCodeValidyTime = 3600 * $accessCodeValidyTime;

                } elseif ($unit === 'days') {
                    $accessCodeValidyTime = 86400 * $accessCodeValidyTime;

                }
                if (!$accessCodeValidyTime) {
                    $accessCodeValidyTime = 86400; // one day
                }
            }
            $payload = [
                'user' => $user,
                'group' => $group,
                'link' => $folder,
            ];
            $tick = new Ticketing(['hashSize' => 12, 'defaultType' => 'user-ticket']);
            $hash = $tick->createHash();
            $hash = $tick->createTicket($payload, $n, $accessCodeValidyTime, false, $hash);
            $str = <<<EOT
<pre>
Ticket: $hash

Link:   $link$hash
</pre>
EOT;

            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']); // close shall reload page to remove url-arg
        }
    } // handleCreateTicketRequest



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



    private function renderCreateTicketForm()
    {
        $pages = $this->lzy->siteStructure->getListOfPages();
        $select = '';
        $currPg = $this->lzy->siteStructure->getPageName();
        foreach ($pages as $page) {
            if ($currPg === $page) {
                $select .= "\t\t<option value='$page' selected>$page</option>\n";
            } else {
                $select .= "\t\t<option value='$page'>$page</option>\n";
            }
        }
        $select = "\t<select name='lzy-selected-page'>\n$select\n\t</select>\n";
        $selectUser = $this->renderDropdownOfUsers();

        $form = <<<EOT

    <h1>Create Ticket</h1>
	<div class='lzy-create-ticket-form lzy-form-wrapper lzy-form-colored'>
	  <form id='test-form' class='test-form lzy-form lzy-encapsulated' method='post'>
	  <div class="lzy-fieldset"><fieldset>
        <legend>Access Restrictions</legend>
		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-1 lzy-form-field-type-number'>
			<label for='fld_max_accesses_1'>Max accesses:
			</label><input type='number' id='fld_max_accesses_1' name='max_accesses' placeholder="1" value='1' />
		</div><!-- /field-wrapper -->


		<div  id='lzy-create-ticket-panel'>
		<div  class="lzy-panel-ticket-duration">
            <h1>Duration</h1>
            <div class='lzy-form-field-wrapper lzy-form-field-wrapper-2 lzy-form-field-type-number'>
                <label for='fld_duration_1'>Duration:
                </label><input type='number' id='fld_duration_1' name='duration' value="1" />
            </div><!-- /field-wrapper -->
        
            <div class='lzy-form-field-wrapper lzy-form-field-wrapper-3 lzy-form-field-type-radio lzy-form-field-type-choice  lzy-horizontal'>
                <fieldset class='lzy-form-label lzy-form-radio-label'><legend class='lzy-legend'>Unit:</legend>
                  <div class='lzy-fieldset-body'>
                <div class='lzy-radio_unit_1-1 lzy-form-radio-elem lzy-form-choice-elem'>
                    <input id='lzy-radio_unit_1-1' type='radio' name='unit' value='minutes' /><label for='lzy-radio_unit_1-1'>Minute(s)</label>
                </div>
                <div class='lzy-radio_unit_1-2 lzy-form-radio-elem lzy-form-choice-elem'>
                    <input id='lzy-radio_unit_1-2' type='radio' name='unit' value='hours' /><label for='lzy-radio_unit_1-2'>Hour(s)</label>
                </div>
                <div class='lzy-radio_unit_1-3 lzy-form-radio-elem lzy-form-choice-elem'>
                    <input id='lzy-radio_unit_1-3' type='radio' name='unit' value='days' checked="checked" /><label for='lzy-radio_unit_1-3'>Day(s)</label>
                </div>
                  </div><!--/lzy-fieldset-body -->
                </fieldset>
            </div><!-- /field-wrapper -->
		</div><!-- /.lzy-panel-ticket-duration -->

		<div  class="lzy-panel-ticket-time">
            <h1>Until</h1>
            <div class='lzy-form-field-wrapper lzy-form-field-wrapper-4 lzy-form-field-type-datetime'>
                <label for='fld_util_1'>Util:
                </label><input type='datetime-local' id='fld_util_1' name='util' />
            </div><!-- /field-wrapper -->
		</div><!-- /.lzy-panel-ticket-time -->
		</div><!-- /#lzy-create-ticket-panel -->
       </fieldset></div>

       <div class="lzy-fieldset"><fieldset>
        <legend>For</legend>
		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-5 lzy-form-field-type-text'>
			<label for='fld_user_1'>User:</label>
			$selectUser
		</div><!-- /field-wrapper -->

		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-7 lzy-form-field-type-text'>
			<label for='fld_link_1'>Page:</label>
			$select
		</div><!-- /field-wrapper -->

		<div class='lzy-form-field-type-buttons'>
		<input type='submit' id='btn_test-form_submit' value='Submit'  class='lzy-button lzy-form-button lzy-form-button-submit' />
		<input type='reset' id='btn_test-form_reset' value='Cancel'  class='lzy-button lzy-form-button lzy-form-button-reset' />
		</div><!-- /field-wrapper -->
      </fieldset></div>

	  </form>
	</div><!-- /lzy-form-wrapper -->

EOT;
        return $form;
    } // renderCreateTicketForm



    private function renderDropdownOfUsers( $preselected = 'guest' )
    {
        $out = "\t\t<select id='fld_user_1' name='lzy-selected-user'>\n";
        $out .= "\t\t\t<option value=''></option>\n";
        $users = $this->auth->getKnownUsers();
        $users = array_keys($users);
        sort($users);
        foreach ($users as $user) {
            $selected = ''; //selected=''
            if ($preselected && ($preselected === $user)) {
                $selected = " selected='true' ";
            }
            $out .= "\t\t\t<option value='$user'$selected>$user</option>\n";
        }
        $out .= "\t\t</select>\n";
        return $out;
    } // getAllUsers

} // UserAdminBase