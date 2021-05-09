<?php


class UserAdmin extends UserAdminBase
{
    public function __construct( $lzy, $hash = false )
    {
        parent::__construct( $lzy );
        $lzy->trans->readTransvarsFromFile('~sys/'.LOCALES_PATH.'/admin.yaml', false, true);
        $lzy->page->addModules('USER_ADMIN, POPUPS, TOOLTIPSTER');

        $this->handleUserAdminRequests( $hash );
    } // __construct



    // entry point for rending forms: 'invite', 'signup'
    public function render( $options )
    {
        $this->options      = $options;
        $mode               = @$options['mode']? $options['mode']: 'signup';

        if (strpos($mode, 'invite') !== false) {
            $html = $this->renderInviteUsersForm();

        } elseif (strpos($mode, 'signup') !== false) {
            $html = $this->renderUserSignupForm();
        }
        return $html;
    } // render




    // === Request Handlers ======================================

    // common entry point: 'invite-users', 'user-signup'
    private function handleUserAdminRequests( $hash )
    {
        $tickType = '';
        if ($hash) {
            $tck = new Ticketing();
            $tickRec = $tck->consumeTicket($hash);
            $tickType = @$tickRec['_ticketType'];
        }
        $cmd = getPostData('_lizzy-form-cmd');
        if (!$cmd && !$tickType) {
            return;
        }

        if ($cmd === 'invite-users') {
            $this->handleInviteUsers( true );

        } elseif ($cmd === 'edit-invite-users') {
            $this->handleInviteUsers();

        } elseif ($tickType === 'user-signup') { //???
            $this->renderUserSignupForm();

        } elseif ($tickType === 'lzy-confirm-email') { //???
            $this->handleEmailConfiration( $tickRec );
        }
    } // handleUserAdminRequests



    private function handleEmailConfiration( $tickRec )
    {
        unset($tickRec['_ticketType']);
        if (isset($tickRec['username'])) {
            $username = $tickRec['username'];
        } else {
            $username = $tickRec['email'];
        }
        $res = $this->addUserToDB($username, $tickRec);
        if ($res === true) {
            // success -> create a ot-ticket which reloads the browser, opening the right page and logging in:
            $landingPage = $GLOBALS['globalParams']['pageUrl'];
            $hash = $this->createOneTimeTicket( $username, $landingPage );
            reloadAgent("./$hash", '{{ lzy-signup-success }}');

        } else {
            $res = [ "{{ lzy-signup-failed }} {{ $res }}" ];
            return $res;
        }
    } // handleEmailConfiration



    private function handleInviteUsers( $sendImmediately = false )
    {
        // <- admin filled in form with intived persons
        // -> create landing-page tickets and open overlay for launching mail-client
        $emailRaw = getPostData('lzy-usradm-invite-emails', true);
        $groups = implode(' ', getPostData('lzy-usradm-invite-groups'));
        $proxyUser = getPostData('proxyuser');
        $landingpage = getPostData('landingpage');
        $time = intval(getPostData('registrationperiod'));
        if ($time <= 0) {
            $time = PHP_INT_MAX;
        } else {
            $time += time();
        }

        $emails = explodeTrim("\n,;", $emailRaw);
        $tck = new Ticketing();
        $out = '';
        foreach ($emails as $email) {
            $name = '';
            if (preg_match('/(.*?) < (.*?) >/x', $email, $m)) {
                $name = trim($m[1]);
                $email = $m[2];
            }
            $hash = createHash(5, true);
            $rec = [
                'user' => $proxyUser,
                'email' => $email, // -> to prefill signup form
                'groups' => $groups,
                'displayName' => $name, // optional -> to prefill signup form
                'landingPage' => $landingpage, // pagePath of signup form
                'hash' => $hash,
            ];
            $hash = $tck->createTicket($rec, 10, $time, 'landing-page', $hash);
            $out .= $this->processInvitationMail($hash, $email, $name, $time, $sendImmediately);
        }
        if ($out) {
            $out = "# Invite New Users\n\n$out";
            $out = compileMarkdownStr($out);
            $this->page->addOverlay($out);
        }
    } // handleInviteUsers



    private function processInvitationMail($hash, $email, $name, $time, $sendImmediately)
    {
        $until = strftime('%x %R', $time);
        $host = $GLOBALS['globalParams']['host'];
        $url = "{$GLOBALS['globalParams']['absAppRootUrl']}$hash";
        $subject = "{{ lzy-email-sign-up-subject }}{$host}";
        $subject = $this->trans->translate($subject);
        $subject1 = htmlentities( $subject );

        $message = "{{ lzy-email-sign-up1 }} → $url {{ lzy-email-sign-up2 }}\n\n{{ lzy-email-sign-up3 }} $until.\n\n{{ lzy-email-sign-up-greeting }}\n";
        $message = $this->trans->translate($message);
        $message = str_replace('%website%', $host, $message);
        if ($name) {
            $message = str_replace('%name%', " $name", $message);
        } else {
            $message = str_replace('%name%', '', $message);
        }
        $message1 = htmlentities( str_replace( "\n",'%0D%0A', $message) );

        if ( $sendImmediately ) {
            mylog("User-Invitation-Mail sent to $email\nSubject: $subject\n$message", false);
            $this->lzy->sendMail($email, $subject, $message, false);
            $out = '';

        } else {
            mylog("User-Invitation-Mail prepared for $email\nSubject: $subject\n$message");
            $addr = "$email?subject=$subject1&body=$message1";
            $out =
                <<<EOT
## {{ link('mailto:$addr', '$email') }}
Subject: $subject

<pre>$message</pre>

EOT;
        }
        return $out;
    } // processInvitationMail






    // === Form Rendering ======================================

    private function renderInviteUsersForm()
    {
        // args from page / macro-call:
        $proxyuser    = isset($this->options['proxyuser'])? $this->options['proxyuser']: 'proxyuser';
        $group        = isset($this->options['group'])? $this->options['group']: 'guests';
        $landingPage  = isset($this->options['landingPage'])? $this->options['landingPage']: '';
        $registrationPeriod  = isset($this->options['registrationPeriod'])? $this->options['registrationPeriod']: '1 week';
        $groupSelect  = parent::renderCheckboxListOfGroups( $group );

        $warning = '';
        $users = $this->auth->getKnownUsers();
        if (!in_array($proxyuser, array_keys($users))) {
            $warning = "<div class='lzy-warning'>{{ lzy-invite-user-missing-warning }}: \"$proxyuser\"</div>";
        }

        if (strpos($registrationPeriod, 'day') !== false) {
            $registrationPeriod = 86400;
        } elseif (strpos($registrationPeriod, 'week') !== false) {
            $registrationPeriod = 86400 * 7;
        } elseif (strpos($registrationPeriod, 'month') !== false) {
            $registrationPeriod = 86400 * 31;
        } else {
            $registrationPeriod = -1;
        }

        $until = strftime('%x %R', time() + $registrationPeriod);
        $host = $GLOBALS['globalParams']['host'];
        $url = "{$GLOBALS['globalParams']['absAppRootUrl']}XXXXXX";
        $subject = "{{ lzy-email-sign-up-subject }}{$host}";
        $subject = $this->trans->translate($subject);
        $message = "{{ lzy-email-sign-up1 }} → $url {{ lzy-email-sign-up2 }}{{ lzy-email-sign-up3 }}$until. {{ lzy-email-sign-up-greeting }} \n";
        $message = $this->trans->translate($message);

        $html = <<<EOT

	<div class='lzy-form-wrapper lzy-form-colored'>
	    <h2>{{ lzy-invite-user-request-header }}</h2>
	  <form id='lizzy-form1' class='lzy-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
		<input type='hidden' class='lzy-form-cmd' name='_lizzy-form-cmd' value='invite-users' />
		<input type='hidden' name='landingpage' value='$landingPage' />
		<input type='hidden' name='proxyuser' value='$proxyuser' />
		<input type='hidden' name='registrationperiod' value='$registrationPeriod' />

		<div class='lzy-form-field-wrapper lzy-form-field-type-textarea'>
            <span class='lzy-label-wrapper'>
                <label for='lzy-usradm-invite-emails-textarea'>{{ lzy-invite-user-email-prompt }}</label>
                <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-usradm-invite-info-text" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span  class="lzy-invisible">
                        <span id="lzy-usradm-invite-info-text" class="lzy-formelem-info-text lzy-usradm-invite-info-text">{{ lzy-invite-user-email-info }}</span>
                    </span>
                </button>
            </span><!-- /lzy-label-wrapper -->
            
            <div class='lzy-textarea-autogrow lzy-form-input-elem'>
                <textarea id='lzy-usradm-invite-emails-textarea' name='lzy-usradm-invite-emails' class='lzy-form-input-elem' aria-describedby='lzy-usradm-invite-info-text'></textarea>
            </div><!-- /lzy-form-input-elem -->
        </div><!-- /field-wrapper -->

		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-3 lzy-form-field-type-dropdown'>
            <span class='lzy-label-wrapper'>
                <label for='fld_lzy-usradm-invite-groups-prompt_1'>{{ lzy-invite-user-groups-prompt }}</label>
                <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-formelem-info-text-1-3_1" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span  class="lzy-invisible">
                        <span id="lzy-formelem-info-text-1-3_1" class="lzy-formelem-info-text lzy-formelem-info-text-1-3_1">{{ lzy-invite-user-groups-info }}</span>
                    </span>
                </button>
            </span><!-- /lzy-label-wrapper -->
$groupSelect
		</div><!-- /field-wrapper -->
$warning

		<div class='lzy-form-field-type-buttons'>
            <input type='reset' id='btn_lizzy-form1_cancel' value='{{ lzy-admin-cancel-button }}'  class='lzy-form-button lzy-form-button-cancel' />
            <input type='button' id='btn_lizzy-form1_prepare' value='{{ lzy-email-sign-up-prepare  }}'  class='lzy-form-button lzy-form-button-submit lzy-disabled' />
            <input type='submit' id='btn_lizzy-form1_submit' value='{{ lzy-email-sign-up-send  }}'  class='lzy-form-button lzy-form-button-submit lzy-disabled' />
		</div><!-- /field-wrapper -->
		
	  </form>
	</div><!-- /lzy-form-wrapper -->
	
	
    <div class='lzy-reveal-controller'>
        <input id='lzy-reveal-controller-1' class='lzy-reveal-controller-elem lzy-reveal-icon' type='checkbox' data-reveal-target='#lzy-preview' />
        <label for='lzy-reveal-controller-1'>{{ lzy-invite-user-mail-preview }}</label>
    </div>
    <div id="lzy-preview">
        <pre>
Subject: $subject

$message
        </pre>
    </div>

EOT;
    $html = str_replace('%website%', $host, $html);

        $jq = <<<'EOT'
$('#lzy-usradm-invite-emails-textarea').on('input', function() {
    let $btn = $('.lzy-form-button-submit');
    if ($btn.hasClass('lzy-disabled')) {
        $btn.prop('disabled', false).removeClass('lzy-disabled');
    }
});
$('#btn_lizzy-form1_prepare').click(function() {
    $('#lizzy-form1 .lzy-form-cmd').val('edit-invite-users');
    $('#lizzy-form1').submit();
});
EOT;
        $this->page->addJq( $jq );
        $this->page->addModules('REVEAL');

        return $html;
    } // renderInviteUsersForm



    private function renderUserSignupForm( $tickRec = false )
    {
        if (!$tickRec) {
            $tickRec = @$this->lzy->landingPageTickRec;
        }
        $group   = isset($this->options['group'])? $this->options['group']: 'guests';
        $group   = isset($tickRec['group'])? $tickRec['group']: $group;
        $hash    = isset($tickRec['hash'])? $tickRec['hash']: '';
        $email   = isset($tickRec['email'])? $tickRec['email']: '';

        $formOptions = $this->options;
        unset($formOptions['mode']);
        $formOptions['group'] = ['type' => 'bypassed', 'value' => $group];
        $formOptions['hash'] = ['type' => 'bypassed', 'value' => $hash];
        if ($email) {
            $formOptions['verifiedemail'] = ['type' => 'bypassed', 'value' => $email];
            $formOptions['E-Mail']['value'] = $email;
        }
        require_once SYSTEM_PATH.'forms.class.php';
        $form = new Forms($this->lzy);
        $html = $form->renderForm( $formOptions );

        return $html;
    } // renderUserSignupForm



    public function renderNewPwForm()
    {
        $str = "<h2>{{ lzy-define-new-password }}</h2>";
        $str .= "<div class='lzy-define-new-password-text'>{{ lzy-define-new-password-text }}</div>";
        $str .= <<<EOT

	<div class='lzy-profile-edit-form-wrapper lzy-form-colored'>
	  <form id='lizzy-lzy-profile-edit-1' class='lzy-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>

        <div class='lzy-form-field-wrapper lzy-profile-ed-field-wrapper-1 lzy-form-field-type-password'>
            <span class='lzy-label-wrapper'>
                <label for='fld_lzy-change-password-prompt_1'>{{ lzy-change-password-prompt }}</label>
                <button aria-hidden="true" class="lzy-formelem-show-info tooltipster" data-tooltip-content="#lzy-formelem-info-text-1-1_1" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span class="lzy-invisible">
                        <span class="lzy-formelem-info-text lzy-formelem-info-text-1-1_1" id="lzy-formelem-info-text-1-1_1">{{ lzy-change-password-info }}</span>
                    </span>
                </button>
            </span><!-- /lzy-label-wrapper -->
        
            <div class='lzy-form-input-elem'>
                <input aria-describedby='lzy-formelem-info-text-1-1_1' class='lzy-form-password' id='fld_lzy-change-password-prompt_1' name='lzy-change-password' type='password'/>
                <label class='lzy-form-pw-toggle' for="lzy-profile-ed-show-pw-1-1">
                    <input class="lzy-form-show-pw" id="lzy-profile-ed-show-pw-1-1" type="checkbox" />
                    <img alt="show password" class="lzy-form-show-pw-icon" src="~sys/rsc/show.png" title="show password" />
                </label>
            </div>
        </div><!-- /field-wrapper -->
        
        
        <div class='lzy-form-field-wrapper lzy-profile-ed-field-wrapper-2 lzy-form-field-type-password'>
            <span class='lzy-label-wrapper'>
                <label for='fld_lzy-change-password2-prompt_1'>{{ lzy-change-password2-prompt }}</label>
                <button aria-hidden="true" class="lzy-formelem-show-info" data-tooltip-content="#lzy-formelem-info-text-1-2_1" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span class="lzy-invisible">
                        <span class="lzy-formelem-info-text lzy-formelem-info-text-1-2_1" id="lzy-formelem-info-text-1-2_1">{{ lzy-change-password2-info }}</span>
                    </span>
                </button>
            </span><!-- /lzy-label-wrapper -->
        
            <div class='lzy-form-input-elem'>
                <input aria-describedby='lzy-formelem-info-text-1-2_1' class='lzy-form-password' id='fld_lzy-change-password2-prompt_1' name='lzy-change-password2' type='password' />
                <label class='lzy-form-pw-toggle' for="lzy-profile-ed-show-pw-1-2">
                    <input class="lzy-form-show-pw" id="lzy-profile-ed-show-pw-1-2" type="checkbox" />
                    <img alt="show password" class="lzy-form-show-pw-icon" src="~sys/rsc/show.png" title="show password" />
                </label>
            </div>
        </div><!-- /field-wrapper -->

		<div class='lzy-form-field-type-buttons'>
            <input type='reset' id='btn_profile-change-pw-cancel' value='{{ lzy-admin-cancel-button }}'  class='lzy-form-button lzy-form-button-cancel' />
	    	<input type='submit' id='btn_profile-change-pw-submit' value='{{ lzy-change-password-send }}'  class='lzy-form-button lzy-form-button-submit lzy-admin-submit-button' />
		</div><!-- /field-wrapper -->

      </form>
    </div>
EOT;

        $this->page->addJQFiles('USER_ADMIN');
        $jq = "\n\t$('#btn_profile-change-pw-cancel').click(function(){ lzyReload(); });\n";
        $this->page->addJq($jq);

        return $str;
    } // renderNewPwForm
} // UserAdmin




 // this callback will be invoked when the forms.class receives sign-up data:
function lzySignupCallback($lzy, $form, $userSuppliedData )
{
    $email = $userSuppliedData['email'];
    $newUser = [
        'email' => $email,
        'password' => $userSuppliedData['password'],
        'groups' => $userSuppliedData['group'],
    ];
    $username = $userSuppliedData['username'];
    if (!$username) {
        $username = $email;
    } else {
        $newUser['username'] = $username;
    }

    $verifiedEmail = @$userSuppliedData['verifiedemail'];
    if ($verifiedEmail && ($verifiedEmail === $email)) {
        // user didn't modify email, so we it's verified -> add user to DB:
        $ua = new UserAdminBase($lzy);
        $res = $ua->addUserToDB($username, $newUser);
        if ($res === true) {
            // success -> create a ot-ticket which reloads the browser, opening the right page and logging in:
            $landingPage = $GLOBALS['globalParams']['pageUrl'];
            $hash = $ua->createOneTimeTicket( $username, $landingPage, $verifiedEmail );
            reloadAgent("./$hash", '{{ lzy-signup-success }}');

        } else {
            $res = [ "{{ lzy-signup-failed }} {{ $res }}" ];
        }
    } else {
        // user modified email, so we need to verify it:
        $tck = new Ticketing();
        $hash = $tck->createTicket($newUser, 1, false, 'lzy-confirm-email');
        $link = "{$GLOBALS['globalParams']['absAppRootUrl']}$hash";
        $host = $GLOBALS['globalParams']['host'];
        $subject = '{{ lzy-email-confirm-subject }}';
        $subject = $lzy->trans->translate($subject);
        $message = '{{ lzy-email-confirm-body }}';
        $message = $lzy->trans->translate($message);
        $message = str_replace(['%website%', '%link%', '%code%', '\\n', '\\t'], [$host, $link, $hash, "\n", "\t"], $message);

        $validUntilStr = strftime('%x %R', time() + 86400);
        $ua = new UserAdminBase($lzy);
        $msgToUser = $ua->renderOnetimeLinkEntryForm($email, $validUntilStr, 'lzy-signup-email-verification');

        $lzy->sendMail($email, $subject, $message, false);
        $res = [ false, $msgToUser ];
    }
    return $res;
} // lzySignupCallback