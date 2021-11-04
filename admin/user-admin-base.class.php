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

        // for security: make sure user admin widgets may not be used within an iframe (or similar):
        header("content-security-policy:  frame-ancestors 'none'");
    } // __construct



    public function handleRequests()
    {
        // add first-user:
        if (@$_POST['_lzy-form-cmd'] === 'lzy-adduser') {
            return $this->handleAddFirstUserRequest();

        } elseif ($cmd = @$_GET['admin']) {
            if ($cmd === 'first-user') {
                return $this->renderAddFirstUserForm();
            }
        }

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



    private function renderAddFirstUserForm()
    {
        $this->trans->readTransvarsFromFile('~sys/'.LOCALES_PATH.'/admin.yaml', false, true);
        $html = <<<EOT

<div class="lzy-add-first-user-wrapper">
    <h1>{{ lzy-adduser-header }}</h1>
	<div class='lzy-form-wrapper lzy-form-colored'>
	  <form class='lzy-form lzy-encapsulated' method='post'>
		<input type='hidden' name='_lzy-form-cmd' value='lzy-adduser' class='lzy-form-cmd' />

		<div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-adduser-username'>{{ username }}<span class='lzy-form-required-marker' aria-hidden='true'>*</span></label>
                    
                </span><!-- /lzy-label-wrapper -->
			<input type='text' id='lzy-adduser-username' name='lzy-username' required aria-required='true' class='lzy-form-input-elem' />
		</div><!-- /field-wrapper -->


		<div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-adduser-email'>{{ email }}<span class='lzy-form-required-marker' aria-hidden='true'>*</span></label>
                    
                </span><!-- /lzy-label-wrapper -->
			<input type='text' id='lzy-adduser-email' name='lzy-email' required aria-required='true' class='lzy-form-input-elem' />
		</div><!-- /field-wrapper -->


		<div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-adduser_requested-groups'>{{ lzy-groups }}</label>
                    
                </span><!-- /lzy-label-wrapper -->
			<input type='text' id='lzy-adduser_requested-groups' name='requested-groups' class='lzy-form-input-elem' value='admins' />
		</div><!-- /field-wrapper -->


		<div class='lzy-form-field-wrapper lzy-form-field-type-password'>
                <span class='lzy-label-wrapper'>
		                    <label for='lzy-adduser-password'>{{ password }}</label>
		                    
		                </span><!-- /lzy-label-wrapper -->
		            <div class='lzy-form-input-elem'>
                <input type='password' class='lzy-form-password' id='lzy-adduser-password' name='lzy-password' aria-invalid='false' />
                    <label class='lzy-form-pw-toggle' for="lzy-form-show-pw-1-4">
                        <input type="checkbox" id="lzy-form-show-pw-1-4" class="lzy-form-show-pw">
                        <img src="~sys/rsc/show.png" class="lzy-form-show-pw-icon" alt="{{ show password }}" title="{{ show password }}" />
                    </label>

            </div>
		</div><!-- /field-wrapper -->


		<div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-adduser_displayname'>{{ displayName }}</label>
                    
                </span><!-- /lzy-label-wrapper -->
			<input type='text' id='lzy-adduser_displayname' name='lzy-displayname' class='lzy-form-input-elem' />
		</div><!-- /field-wrapper -->


		<div class='lzy-form-field-wrapper lzy-form-field-type-hash'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-adduser-accesscode'>{{ accessCode }}</label>
                    
                </span><!-- /lzy-label-wrapper -->
			<input type='text' class='lzy-input-hash '  name='lzy-accesscode' />
			<button id='lzy-adduser-create-hash' class='lzy-button lzy-generate-hash' type='button'>{{ lzy-generate-hash }}</button>
		</div><!-- /field-wrapper -->

		<div class='lzy-form-required-comment'>{{ lzy-form-required-comment }}</div>

		<div class='lzy-form-field-type-buttons'>
		<input type='reset' id='btn_lizzy-form1_cancel' value='Cancel'  class='lzy-form-button lzy-form-button-cancel' />
		<input type='submit' id='btn_lizzy-form1_submit' value='Submit'  class='lzy-form-button lzy-form-button-submit' />
		</div><!-- /field-wrapper -->

	  </form>
	</div><!-- /lzy-form-wrapper -->
</div>

EOT;
        $this->lzy->page->addModules('USER_ADMIN');
        $jq = <<<'EOT'
			$('#lzy-adduser-create-hash').click(function() {
			    let $wrapper = $(this).closest('.lzy-form-field-wrapper');
			    let $input = $('.lzy-input-hash', $wrapper);
			    const newHash = createHash( 8, false ); // unambiguous
			    $input.val( newHash );
			});
			$('.lzy-form .lzy-form-pw-toggle').click(function(e) {
			    e.preventDefault();
			    let $form = $(this).closest('.lzy-form');
			    let $pw = $('.lzy-form-password', $form);
			    let rcsPath = systemPath + 'rsc/';
			    if ($pw.attr('type') === 'text') {
			        $pw.attr('type', 'password');
			        $('.lzy-form-show-pw-icon', $form).attr('src', rcsPath + 'show.png');
			    } else {
			        $pw.attr('type', 'text');
			        $('.lzy-form-show-pw-icon', $form).attr('src', rcsPath +'hide.png');
			    }
			});
EOT;
        $this->lzy->page->addJq($jq);
        $this->lzy->page->addOverlay($html);
        mylog('rendering First-User Form');
        return true;
    } // renderAddFirstUserForm



    private function handleAddFirstUserRequest()
    {
        // security check: action only allowed if a) config/users.yaml not existing
        if (file_exists(CONFIG_PATH.'users.yaml')) {
            mylog('First-User: file config/users.yaml already exists -> skipped');
            return false;
        }
        $username = @$_POST['lzy-username'];
        $email = @$_POST['lzy-email'];
        $password = @$_POST['lzy-password'];
        if ($password) {
            $password = password_hash( $password, PASSWORD_DEFAULT );
        }
        $requestedGroups = @$_POST['requested-groups'];
        $displayName = @$_POST['lzy-displayname'];
        $accessCode = @$_POST['lzy-accesscode'];

        $newUser = <<<EOT
$username:
    email: $email
    password: $password
    groups: $requestedGroups
    username: $username
    accessCode: $accessCode
    displayName: $displayName
EOT;
        mylog('First-User created: '.preg_replace("/\n\s*/", '; ', $newUser));

        $newUser = <<<EOT
# User DB
#==========

# Initial admin user:
$newUser

#--------------------------------------------------------------------------------------
# See https://getlizzy.net/misc/access_control/ for reference
# Options:
# ========
# password:
#       A hash code of the password is stored, not the original password.
#       -> use convert tool '<url>?convert' to create hashed password.
# groups:
#       Only members of a group here get access. 
#       Predefined groups: admins, editors, fileadmins. 
#       In the sitemap file (config/sitemap.txt) pages may be designated as 
#       restricted:group
# email:
#       (optional) permits logging in withough a password: the user enters that (or 
#       the username) and Lizzy will a one-time access code to that address. Clicking 
#       on the contained link will log the user in.
# emailList:
#       (optional) points to a file containing a list of email-address. Owners of these 
#       addresses are permitted to log in using the one-time access code mechanism.
# displayName:
#       (optional) if set, that name will be shown in the webpage (e.g. in the 
#       'Logged in as…' link)
# maxSessionTime:
#       (optional), if set, it defines the time in seconds that the user can access 
#       the page without logging in again.
# accessCodeEnabled:
#       (optional) permits to disable the one-time access code mechanism for a 
#       particular user.
# accessCodeValidyTime:
#       (optional) defines the time during whith a one-time access code (or link) 
#       is valid. Maximum time possible is 60 minutes.
# accessCode:
#       (optional) a password-like string consisting of 6 uppercase letters and digits
#       (plain or hashed) that can be used to build a custom link: append to the URL
#       like a subfolder, e.g. https://getlizzy.net/mypage/U8KDF2
#       Any user who knows this link can automatically log in and access otherwise
#       restricted pages. Not very secure but convenient.
# accessCodeValidUntil:
#       (optional) defines the date and time until an accessCode (see above) expires.
# inactive:
#       (optional) If true, the owner of this account cannot log in.
#--------------------------------------------------------------------------------------


# The sample password below is 'insecure-pw' - do not use in production!

# author:
#     password:     $2a$10\$nvgug7wDF/.ErP5lVzKnY.977FaZP0kedf4UQkwk7BCpVCrgZrHD6
#     groups:       editors
#     email:        name@domain.net


# user:
#     password:     $2a$10\$nvgug7wDF/.ErP5lVzKnY.977FaZP0kedf4UQkwk7BCpVCrgZrHD6
#     groups:       members, vips
#     email:        user@domain.net
#     displayName:  John Doe


EOT;
        file_put_contents(CONFIG_PATH.'users.yaml', $newUser);

        reloadAgent(false, '{{ lzy-first-user-added }}');
    } // handleAddFirstUserRequest




    public function renderOnetimeLinkEntryForm($email, $validUntilStr, $prefix)
    {
        $form = <<<EOT

    <div class='lzy-onetime-link-sent'>
        {{ $prefix sent }}
    
        <form class="lzy-onetime-code-entry" method="post">
            <label for="">{{ lzy-enter onetime code }}</label>
            <input type="hidden" value="$email" name="lzy-login-user" />
            <input id="lzy-onetime-code" type="text" name="lzy-onetime-code" style="width:8em;" />
            <input type="submit" class='lzy-button lzy-admin-submit-button' value="{{ Submit }}" />
        </form>
    
        <p>{{ $prefix sent2 }}</p>
        <p>{{ $prefix sent3 }}</p>
        {{^ lzy-sign-up further info }}
        
        {{ vgap }}
        <a href="./" class="lzy-button">{{ Cancel }}</a>
    </div>

EOT;
        $form = $this->trans->translate( $form );
        $form = str_replace(['%until%','%email%'], [$validUntilStr, $email], $form);
        return $form;
    } // renderOnetimeLinkEntryForm



    public function sendCodeByMail($submittedEmail, $ticketType, $accessCodeValidyTime, $userRec = false)
    {
        global $lizzy;

        $message = '';
        $validUntil = time() + $accessCodeValidyTime;
        $validUntilStr = strftime('%R  (%x)', $validUntil);

        $user = isset($userRec['username']) ? $userRec['username'] : '';
        if (isset($userRec['displayName'])) {
            $displayName = $userRec['displayName'];
        } else {
            $displayName = $submittedEmail;
        }

        $tick = new Ticketing(['unambiguous' => true, 'defaultType' => $ticketType]);

        $otRec = ['username' => $user, 'email' => $submittedEmail];
        $hash = $tick->createTicket($otRec, 1, $accessCodeValidyTime);

        $url = $lizzy['pageUrl'] . $hash . '/';

        // --- lzy-ot-access
        if ($ticketType === 'lzy-ot-access') {
            $subject = "[{{ site_title }}] {{ lzy-email-access-link-subject }} {$lizzy['host']}";
            $message = "{{ lzy-email-access-link0 }}$displayName{{ lzy-email-access-link1 }}    ".
                "→ $url {{ lzy-email-access-link2 }}{{ lzy-email-access-link3 }}{{ lzy-email-access-greeting }}\n";
            $message = $this->trans->translate( $message );
            $message = str_replace(['%hash%', '%email%'], [$hash, $submittedEmail], $message );
            $this->sendMail($submittedEmail, $subject, $message);

            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-onetime access link');
            $this->lzy->loginFormRequiredOverride = false;
            writeLogStr("one time link sent to: $submittedEmail -> '$hash'", LOGIN_LOG_FILENAME);


        // --- email-signup
        } elseif ($ticketType === 'email-signup') {
            $subject = "[{{ site_title }}] {{ lzy-email-sign-up-subject }} {$lizzy['host']}";
            $message = "{{ lzy-email-sign-up1 }} → $url {{ lzy-email-sign-up2 }}{{ lzy-email-sign-up3 }}$validUntilStr. {{ lzy-email-sign-up-greeting }} \n";

            $this->sendMail($submittedEmail, $subject, $message);
            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-sign-up-link');


        // --- lzy-change-email-request
        } elseif ($ticketType === 'lzy-change-email-request') {
            if (isset($userRec['email']) && ($userRec['email'] === $submittedEmail)) {
                reloadAgent(false,"email-change-mail-unchanged");
            }
            $res = $this->isInvalidEmailAddress($submittedEmail);
            if ($res) {
                reloadAgent( false, $res );
            }
            $subject = "[{{ site_title }}] {{ lzy-email-change-mail-subject }} {$lizzy['host']}";
            $message = "{{ lzy-email-change-mail-up1 }} $url {{ lzy-email-change-mail-up2 }} $hash {{ lzy-email-change-mail-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);
            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-change-mail-link');


        // --- request-email-verification
        } elseif ($ticketType === 'request-email-verification') {
            $subject = "[{{ site_title }}] {{ lzy-request-email-verification-subject }} {$lizzy['host']}";
            $message = "{{ lzy-request-email-verification-up1 }} $url {{ lzy-request-email-verification-up2 }} $hash {{ lzy-request-email-verification-up3 }}$validUntilStr. \n";

            $this->sendMail($submittedEmail, $subject, $message);
            $message = $this->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-email-verification-link');

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



    public function createOneTimeTicket( $user, $landingPage = false, $email = '', $accessCodeValidyTime = false)
    {
        $tick = new Ticketing(['unambiguous' => true, 'defaultType' => 'lzy-ot-access']);

        $otRec = ['username' => $user, 'email' => $email];
        if ($landingPage) {
            $otRec['landingpage'] = $landingPage;
        }
        return $tick->createTicket($otRec, 1, $accessCodeValidyTime);
    } // createOneTimeTicket



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
            $page = get_post_data('lzy-landing-page');
            $pgRec = $this->lzy->siteStructure->findSiteElem( $page, true, true );
            $folder = ($pgRec['urlpath'] !== false) ? $pgRec['urlpath']: $pgRec['folder'];
            $link = $GLOBALS['lizzy']['absAppRootUrl'];
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
                'landingPage' => $folder,
            ];
            $tick = new Ticketing(['hashSize' => 12, 'defaultType' => 'landing-page']);
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



    public function addUserToDB($username, $userRec)
    {
        $knownUsers = $this->auth->getKnownUsers();
        if (isset($knownUsers[ $username ])) {
            return 'lzy-adduser-failed-username-already-in-use';
        }

        if ($this->auth->findUserRecKey( $username )) {
            return 'lzy-adduser-failed-already-exists';
        } elseif ($this->auth->findUserRecKey($userRec['email'], 'email')) {
            return 'lzy-adduser-failed-email-already-in-use';
        }
        $knownUsers[$username] = $userRec;
        writeToYamlFile($this->auth->userDB, $knownUsers);
        return true;
    } // addUser



    protected function isInvalidEmailAddress($email) {
        if (!is_legal_email_address( $email )) {
            return 'email-changed-email-invalid';
        }
        if ($this->auth->findUserRecKey($email, '*')) {
            return 'email-changed-email-in-use';

        }
        if ($this->auth->findEmailInEmailList($email)) {
            return 'email-changed-email-in-use';
        }
        return false;
    } // isInvalidEmailAddress



    private function renderCreateTicketForm()
    {
        $pages = $this->lzy->siteStructure->getListOfPages();
        $selectLandingPage = '';
        $currPg = $this->lzy->siteStructure->getPageName();
        foreach ($pages as $page) {
            if ($currPg === $page) {
                $selectLandingPage .= "\t\t<option value='$page' selected>$page</option>\n";
            } else {
                $selectLandingPage .= "\t\t<option value='$page'>$page</option>\n";
            }
        }
        $selectLandingPage = "\t<select name='lzy-landing-page'>\n$selectLandingPage\n\t</select>\n";
        $selectUser = $this->renderDropdownOfUsers();

        $form = <<<EOT

    <h1>Create "LandingPage-Ticket"</h1>
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
			<label for='fld_link_1'>Landing-Page:</label>
			$selectLandingPage
		</div><!-- /field-wrapper -->

		<div class='lzy-form-field-type-buttons'>
		<input type='submit' id='btn_test-form_submit' value='Submit'  class='lzy-button lzy-form-button lzy-form-button-submit' />
		<input type='reset' id='btn_test-form_reset' value='Cancel'  class='lzy-button lzy-form-button lzy-form-button-reset' />
		</div><!-- /field-wrapper -->
      </fieldset></div>

	     <div class="lzy-create-ticket-comment">{{ lzy-create-ticket-comment }}</div>
	  </form>
	</div><!-- /lzy-form-wrapper -->

EOT;
        return $form;
    } // renderCreateTicketForm



    protected function renderDropdownOfPages( $preselected )
    {
        $out = "\t\t<select id='fld_pages_1' name='lzy-selected-landingpage'>\n";
        $out .= "\t\t\t<option value=''></option>\n";
        $pages = $this->lzy->siteStructure->getListOfPages(false, true);
        foreach ($pages as $rec) {
            $selected = '';
            if ($preselected && ($preselected === $rec[1])) {
                $selected = " selected='true'";
            }
            $out .= "\t\t\t<option value='{$rec[1]}'$selected>{$rec[0]}</option>\n";
        }
        $out .= "\t\t</select>\n";
        return $out;
    } // renderDropdownOfPages



    protected function renderDropdownOfUsers( $preselected = '' )
    {
        $out = "\t\t<select id='fld_user_1' name='lzy-selected-user'>\n";
        $out .= "\t\t\t<option value=''></option>\n";
        $users = $this->auth->getKnownUsers();
        $users = array_keys($users);
        sort($users);
        foreach ($users as $user) {
            $selected = '';
            if ($preselected && ($preselected === $user)) {
                $selected = " selected='true'";
            }
            $out .= "\t\t\t<option value='$user'$selected >$user</option>\n";
        }
        $out .= "\t\t</select>\n";
        return $out;
    } // renderDropdownOfUsers



    protected function renderCheckboxListOfGroups( $preselected = 'selfadmin', $proxyuser = '' )
    {
        $out = "\t\t<div class='lzy-form-field-wrapper lzy-form-field-wrapper-2 lzy-form-field-type-checkbox lzy-form-field-type-choice lzy-horizontal'>";

        $groups = $this->auth->getKnownGroups();
        sort($groups);
        foreach ($groups as $i => $group) {
            if ($proxyuser === $group) {
                continue;
            }
            $checked = ''; //selected=''
            if ($preselected && ($preselected === $group)) {
                $checked = " checked='true' ";
            }
            $out .= <<<EOT
                <div class='lzy-chckb_lzy-usradm-invite-groups-prompt_1-$i lzy-form-checkbox-elem lzy-form-choice-elem'>
                    <input id='lzy-chckb_lzy-usradm-invite-groups-prompt_1-$i' type='checkbox' name='lzy-usradm-invite-groups[]' value='$group' aria-describedby='lzy-formelem-info-text-1-2_$i'$checked />
                    <label for='lzy-chckb_lzy-usradm-invite-groups-prompt_1-$i'>$group</label>
                </div>
EOT;

        }
        $out .= "\t\t</div>\n";
        return $out;
    } // renderCheckboxListOfGroups



    protected function init( $preOpenPanel = 1)
    {
        $jq = <<<EOT

 // Initialize panels:
initLzyPanel('#lzy-login-panels-widget', $preOpenPanel);

 // Password show/hide:
$('.lzy-form-pw-toggle').click(function(e) {
    e.preventDefault();
    var \$form = $('form');
    var \$pw = $('.lzy-form-password', \$form);
    if (\$pw.attr('type') === 'text') {
        \$pw.attr('type', 'password');
        $('.lzy-form-show-pw-icon', \$form).attr('src', systemPath+'rsc/show.png');
    } else {
        \$pw.attr('type', 'text');
        $('.lzy-form-show-pw-icon', \$form).attr('src', systemPath+'rsc/hide.png');
    }
});

 // Info tooltip:
$('.lzy-formelem-show-info').tooltipster({
    trigger: 'click',
    contentCloning: true,
    animation: 'fade',
    delay: 200,
    animation: 'grow',
    maxWidth: 420,
});
			
EOT;
        $this->page->addJq($jq);
        $this->page->addModules('EVENT_UE,PANELS,TOOLTIPSTER');
    } // init



    protected function checkInsecureConnection()
    {
        global $lizzy;
        $relaxedHosts = str_replace('*', '', $this->config->site_allowInsecureConnectionsTo);

        if (!$this->config->isLocalhost && !(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on')) {
            $url = str_ireplace('http://', 'https://', $lizzy['pageUrl']);
            $url1 = preg_replace('|https?://|i', '', $lizzy['pageUrl']);
            if (strpos($url1, $relaxedHosts) !== 0) {
                $this->page->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
            }
            return false;
        }
        return true;
    } // checkInsecureConnection



    protected function wrapTag($className, $str)
    {
        if (($className === MSG) && isset($GLOBALS['lizzy']['auth-message'])) {
            $str .= ' '.$GLOBALS['lizzy']['auth-message'];
        }
        $str = trim($str);
        if ($str) {
            $str = "\t\t\t<div class='$className'>$str</div>\n";
        }
        return $str;
    } // wrapTag


} // UserAdminBase