<?php

define('MSG', 'lzy-account-form-message');
define('NOTI', 'lzy-account-form-notification');

define('SHOW_PW_INFO_ICON', '<span class="lzy-icon-info"></span>');
define('SHOW_PW_ICON', '<span class="lzy-icon-show"></span>');

$lizzyAccountCounter = 0;



class UserAccountForm
{
    private $un_preset = '';

    public function __construct($lzy, $infoIcon = SHOW_PW_INFO_ICON)
    {
        global $lizzyAccountCounter;
        $this->showPwIcon = SHOW_PW_ICON;

        $this->infoIcon = $infoIcon;
        if (!isset($GLOBALS['globalParams']['legacyBrowser']) || $GLOBALS['globalParams']['legacyBrowser']) {
            $this->infoIcon = '(i)';
        }
        if ($lzy) {
            $this->lzy = $lzy;
            $this->config = $lzy->config;
            $this->lzyPage = $lzy->page;
            if (isset($lzy->trans)) {      //??? hack -> needs to be cleaned up: invoked from diff places
                $this->trans = $lzy->trans;
            } else {
                $this->trans = $lzy;
            }
            $this->trans->readTransvarsFromFile('~sys/config/admin.yaml', false, true);
            $this->checkInsecureConnection();
            $this->lzyPage->addModules('USER_ADMIN, JS_POPUPS');
        } else {
            $this->lzy = null;
            $this->config = null;
            $this->lzyPage = null;
            $this->trans = null;
        }
        $this->loggedInUser = (isset($_SESSION['lizzy']['user'])) ? $_SESSION['lizzy']['user'] : false;
        $this->inx = &$lizzyAccountCounter;
        $this->message = (isset($lzy->auth->message)) ? $lzy->auth->message : '';
        $this->warning = (isset($lzy->auth->warning)) ? $lzy->auth->warning : '';
    } // __construct


    public function renderLoginForm($notification = '', $message = '', $returnRaw = false)
    {
        $this->un_preset = '{{^ lzy-username-preset }}';
        $this->page = new Page;
        if ($this->config->admin_enableAccessLink) {
            $str = $this->createMultimodeLoginForm($notification, $message);
        } else {
            $str = $this->createPWAccessForm($notification, $message);
        }

        // avoid leave-page warning when submitting login
        //  (which could happen, if current page contains a form)
        $jq = <<<EOT
$('.lzy-login-form').submit(function(e) {
    lzyFormUnsaved = false;
});

EOT;
        $this->lzy->page->addJq($jq);
        if ($returnRaw) {
            return $str;
        }

        $this->page->addModules('PANELS');
        $this->page->addOverride($str);
        $this->page->setOverrideMdCompile(false);

        return $this->page;
    } // authForm


    public function renderLoginUnPwForm($notification = '', $message = '')
    {
        $this->page = new Page;
        $str = $this->createPWAccessForm($notification, $message);

        $this->page->addOverride($str);

        return $this->page;
    } // authForm


    public function renderLoginAcessLinkForm($notification = '', $message = '')
    {
        $this->page = new Page;
        $str = $this->createLinkAccessForm($notification);

        $this->page->addOverride($str);

        return $this->page;
    } // authForm


    public function renderSignUpForm($group, $notification = '', $message = '')
    {
        $this->page = new Page;
        setStaticVariable('self-signup-to-group', $group);
        if ($message) {
            $str = $this->createSignUpForm($notification, '');
            $str = str_replace('$$', $str, $message);

        } else {
            $str = "<h2>{{ lzy-self-sign-up }}</h2>";
            $str .= $this->createSignUpForm($notification, '');
        }

        $this->page->addOverride($str);

        return $this->page;
    } // authForm


    public function renderAddUserForm($group, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-add-user }}</h2>";
        $this->page = new Page;
        $str .= $this->createAddUserForm($notification, $message, $group);

        $this->page->addOverride($str);

        return $this->page;
    }


    public function renderCreateUserForm($rec)
    {
        $accessCodeValidyTime = 900;
        $tick = new Ticketing();
        $rec['mode'] = 'sign-up-invited-user';
        $hash = $tick->createTicket($rec, 1, $accessCodeValidyTime);

        $str = "\t\t\t<h1>{{ lzy-user-self-signup-title }}</h1>";
        $str .= "\t\t\t<p>{{ lzy-user-self-signup-intro }}</p>";
        $str .= $this->createCreateUserForm($rec, $hash);

        $js = <<<'EOT'
var ok = false;

function checkUn( ok ) {
    var un = $('#lzy-login-textinput1').val();
    if (un) {
        console.log(`checking username: ${un}`);
        $.ajax({
          url: "./?lzy-check-username=" + un,
        }).done(function( data ) {
            if (data === 'ok') {
                if (ok === true) {
                    ok = false;
                    $('.lzy-signup-form').submit();
                }
            } else {
                $('#lzy-login-textinput1').focus();
                lzyPopup({
                    content: data,
                    buttons: '{{ lzy-signup-continue }}'
                });
            }
        });
    } else {
        $('#lzy-login-textinput1').focus();
        $('#lzy-login-textinput1 + .lzy-error-message').text( '{{ lzy-username-required }}' );
    }
    return false;
}

EOT;
        $this->lzyPage->addJs( $js );
        $minPwLength = $this->config->admin_minPasswordLength;

        $jq = <<<EOT
\$('.lzy-signup-submit-btn').click(function(e) {
    e.preventDefault();
    var pw = \$('#lzy-login-password1').val();
    var pw2 = \$('#lzy-login-password21').val();
    \$('#lzy-login-password1 + .lzy-error-message').text( '' );
    \$('#lzy-login-textinput1 + .lzy-error-message').text( '' );
    var dfd = \$.Deferred();
    dfd.done( checkUn );
  
    if (pw) {
        if (pw !== pw2) {
            lzyPopup({
                text: '{{ lzy-signup-passwords-not-identical }}',
                buttons: '{{ lzy-signup-continue }}'
            });
       } else if ((pw.length < $minPwLength) || (!pw.match(/[A-Z]/)) || (!pw.match(/\W/)) || (!pw.match(/\d/)) ) {
            lzyPopup({
                contentRef: '#lzy-weak-pw-warning',
                buttons: '{{ lzy-signup-continue-anyway }}, {{ lzy-signup-abort }}',
                closeOnBgClick: false,
                callbacks: function() { dfd.resolve( true ); }
            });
        } else {
            checkUn( true );
        }
    } else {
        \$('#lzy-login-password1').focus();
        \$('#lzy-login-password1 + .lzy-error-message').text( '{{ lzy-password-required }}' );
    }
});

EOT;
        $this->lzyPage->addJq( $jq );

        $str .= "\t<div id='lzy-weak-pw-warning' style='display: none;'><div>{{ lzy-weak-pw-warning }}</div></div>\n";
        return $str;
    } // renderCreateUserForm


    public function renderAddUsersForm($group, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-add-users-to-group }} \"$group\"</h2>";
        $this->page = new Page;
        $str .= $this->createAddUsersForm($notification, $message, $group);

        $this->page->addOverride($str);

        return $this->page;
    }


    public function renderChangePwForm($user, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-change-password }}</h2>";
        $this->page = new Page;
        $str .= $this->createChangePwForm($user, $notification, $message);

        $this->page->addOverride($str);

        return $this->page;
    }


    public function renderOnetimeLinkEntryForm($user, $validUntilStr, $prefix)
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
    }



    public function renderInviteUserForm($userRec, $notification = '', $message = '')
    {
        $html = "\t\t<h2>{{ lzy-invite-user-request-header }}</h2>\n";
        $html .= $this->createInviteUserForm($notification = '', $message = '');
        $html .= "\t{{ lzy-invite-user-request-description }}\n";
        return $html;
    } // renderInviteUserForm




    public function renderEditProfileForm($userRec, $notification = '', $message = '')
    {
        $user = isset($userRec['username']) ? $userRec['username'] : '';
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] &&
            isset($_SESSION["lizzy"]["loginEmail"])) {
            $user = $_SESSION["lizzy"]["loginEmail"];
        }
        $email = isset($userRec['email']) ? $userRec['email'] : '';
        $form1 = $this->createChangePwForm($user, $notification, $message);
        $username = $this->createChangeUsernameForm($user, $notification, $message);
        $emailForm = $this->createChangeEmailForm($user, $notification, $message, $email);
        $delete = $this->createDeleteProfileForm($user, $notification, $message);

        $accessLinkForm = '';
        if ($this->config->admin_userAllowSelfAccessLink) {
            $accessLink = $this->createAccessLinkForm($user, $notification, $message);
            $accessLinkForm = <<<EOT
    
    
            <div>
            <h1>{{ lzy-create-accesslink }}</h1>
            
            <h2>{{ lzy-create-accesslink }}</h2>
$accessLink

            </div><!-- /lzy-panel-page -->

EOT;
        }

        $html = <<<EOT
        <h2>{{ lzy-edit-profile }} &laquo;$user&raquo;</h2>
$message
        <div class="lzy-panels-widget lzy-tilted accordion one-open-only lzy-account-form lzy-login-multi-mode">
            <div>
            <h1>{{ lzy-change-password }}</h1>
      
            <h2>{{ lzy-change-password }}</h2>
$form1      
            
            </div><!-- /lzy-panel-page -->
            
            
            <div>
            <h1>{{ lzy-change-name }}</h1>
            
            <h2>{{ lzy-change-user-name }}</h2>
$username
            </div><!-- /lzy-panel-page -->
    
    
            <div>
            <h1>{{ lzy-change-e-mail }}</h1>
            
            <h2>{{ lzy-change-e-mail }}</h2>
$emailForm
            </div><!-- /lzy-panel-page -->
    
    
            <div>
            <h1>{{ lzy-delete-profile }}</h1>
            
            <h2>{{ lzy-delete-profile }}</h2>
$delete

            </div><!-- /lzy-panel-page -->
$accessLinkForm
    </div><!-- / .lzy-panels-widget -->

EOT;

        $userAdmCss = '~/css/user_admin.css';
        if (!file_exists(resolvePath($userAdmCss))) {
            $userAdmCss = '';
        }
        $this->lzyPage->addModules("USER_ADMIN, $userAdmCss" );

        return $html;
    } // renderEditProfileForm





    //-------------------------------------------------------------
    public function createMultimodeLoginForm($notification, $message = '')
    {
        global $globalParams;
        $message = $this->wrapTag(MSG, $message);

        $subject = '{{ lzy-login-problems }}';
        $body = '%0a%0a{{page}}:%20' . $globalParams['pageUrl'];
        $loginProblemMail = "{{ concat('lzy-forgot-password1', webmaster-email,'?subject=$subject&body=$body', 'lzy-forgot-password2') }}";

        $form1 = $this->createLinkAccessForm($notification);
        $form2 = $this->createPWAccessForm($notification);

        $html = <<<EOT
        <h1>{{ lzy-login-with-choice }}</h1>
$message
        <div class="lzy-panels-widget lzy-tilted one-open-only lzy-account-form lzy-login-multi-mode">
            <div><!-- lzy-panel-page -->
            <h2>{{ lzy-login-without-password }}</h2>
      
$form1      
            
            </div><!-- /lzy-panel-page -->
            
            <div><!-- lzy-panel-page -->
            <h2>{{ lzy-login-with-password }}</h2>
            
$form2

            </div><!-- /lzy-panel-page -->
    
    </div><!-- / .lzy-panels-widget -->

EOT;

        return $html;
    } // createMultimodeLoginForm




    private function createLinkAccessForm($notification, $message = '')
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-onetimelogin-request-', true);
        $submitButton = $this->createSubmitButton('lzy-onetime-link-');

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form lzy-login-by-email"  action="./" method="POST">
$notification
$email
$submitButton
                </form>
            </div><!-- /lzy-account-form-wrapper -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createLinkAccessForm




    private function createPWAccessForm($notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $usernameInput = $this->renderUsernameInput('lzy-login-');
        $passwordInput = $this->renderPasswordInput('lzy-login-password-', false);
        $submitButton = $this->createSubmitButton('lzy-login-');

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
$notification
$usernameInput
$passwordInput                    
$submitButton
                </form>
            </div><!-- /lzy-account-form-wrapper -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm




    private function createSignUpForm($notification, $message)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-self-signup-email-', true, true);
        $submitButton = $this->createSubmitButton('lzy-self-signup-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-signup" value="signup-email" />
$notification
$email
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    }



    private function createAddUserForm($notification, $message, $group)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-add-user-', true,true);
        $username = $this->renderTextlineInput('lzy-add-user-', 'username');
        $this->inx++;
        $password = $this->renderPasswordInput('lzy-add-user-password-', false);
        $this->inx++;
        $displayName = $this->renderTextlineInput('lzy-add-user-', 'displayname');
        $this->inx++;
        if ($group) {
            $groupField = $this->renderHiddenInput('lzy-add-user-group', $group);
            $groupField .= "<div class=''><span>{{ lzy-add-user-group-is }}:</span><span>$group</span></div>";
        } else {
            $groupField = $this->renderTextlineInput('lzy-add-user-', 'group');
        }
        $this->inx++;
        $emailList = $this->renderTextlineInput('lzy-add-user-', 'emaillist');
        $submitButton = $this->createSubmitButton('lzy-add-user-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="add-user" />
$notification
$email
$username
$password
$displayName
$groupField
$emailList
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm



    private function createAddUsersForm($notification, $message, $group)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $newUser = $this->renderTextareaInput('lzy-add-users-', 'email-list', false,'Name &lt;name@domain.net>');
        $submitButton = $this->createSubmitButton('lzy-add-users-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="add-users" />
                    <input type="hidden" name="lzy-add-user-group" value="$group" />
$notification
$newUser
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createAddUsersForm



    private function createCreateUserForm($rec, $hash)
    {
        $this->inx++;
        $path = $GLOBALS["globalParams"]["pagePath"];
        if ($path) {
            $path = trunkPath($path, 1, false);
        } else {
            $path = '';
        }
        $url = $GLOBALS["globalParams"]["appRoot"].$path.$hash.'/';
        $email = $rec['email'];

        $username = $this->renderTextlineInput('lzy-self-signup-user-', 'username', true);
        $password = $this->renderPasswordInput('lzy-self-signup-user-password-', true, false, true);
        $submitButton = $this->createSubmitButton('lzy-add-users-', 'lzy-signup-submit-btn');

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">                
                <form class="lzy-signup-form"  action="$url" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="self-signup" />
                    <div class="lzy-user-self-signup-email-box">
                        <span class="lzy-user-self-signup-email-label">{{ lzy-user-self-signup }}</span>
                        <span class="lzy-user-self-signup-email">$email</span>
                        <span class="lzy-user-self-signup-email-label2">{{ lzy-user-self-signup2 }}</span>
                    </div>

$username
$password
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createCreateUserForm




    public function createChangePwForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $passwordInput = $this->renderPasswordInput('lzy-change-password-', true);
        $this->inx++;
        $passwordInput2 = $this->renderPasswordInput('lzy-change-password2-', true,true);
        $submitButton = $this->createCancelButton('lzy-change-password-');
        $submitButton .= $this->createSubmitButton('lzy-change-password-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="change-password" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$passwordInput  
$passwordInput2                  
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        $jq = "\t$('.lzy-change-password-cancel').click(function(){ lzyReload(); });\n";
        $this->lzyPage->addJq($jq);
        return $str;
    } // createPWAccessForm





    public function createChangeUsernameForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $prev = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-username }} $user)</span>\n";

        $username = $this->renderTextlineInput('lzy-change-user-', 'username', false, false, false, $prev);
        $this->inx++;

        $userRec = $this->lzy->auth->getUserRec();
        $currDisplayName = $userRec['displayName'];
        $prev = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-displayname }} $currDisplayName)</span>\n";
        $displayName = $this->renderTextlineInput('lzy-change-user-', 'displayname', false, false, false, $prev);
        $submitButton = $this->createCancelButton('lzy-change-username-');
        $submitButton .= $this->createSubmitButton('lzy-change-username-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="lzy-change-username" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$username  
$displayName                 
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm





    private function createInviteUserForm($notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $email = $this->renderTextareaInput('lzy-invite-user-email-', 'addresses',true);
        $group = $this->renderTextlineInput('lzy-invite-user-', 'groups', false, '','{{ guest }}');
        $checkbox = $this->renderCheckbox('lzy-invite-user-create-hash-', '');
        $this->inx++;

        $subject = "{{ lzy-signup-invitation-subject }}";
        $subject = $this->renderTextlineInput('lzy-invite-user-', 'subject', false, '', $subject);

        $mailText = $this->renderTextareaInput('lzy-invite-user-mailtext-', '', '', '', '{{ lzy-signup-invitation }}');
        $submitButton = $this->createSubmitButton('lzy-invite-user-email-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-invite-user-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="lzy-invite-user-email" />
                    <input type="hidden" name="lzy-user" value="" />
$notification
$email
$group
$checkbox

                <div style="height:2em;"></div>
                <h3>{{ lzy-invite-new-user-edit-email-header }}</h3>
<div class="lzy-invite-user-mailtext-wrapper">
$subject
$mailText
</div>

$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->config->feature_replaceNLandTabChars = true;
        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createInviteUserForm



    private function createChangeEmailForm($user, $notification, $message = '', $prev = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        if ($prev) {
            $prev = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-email }} $prev)</span>\n";
        }

        $email = $this->renderEMailInput('lzy-change-user-request-', true,true, $prev);
        $submitButton = $this->createSubmitButton('lzy-change-user-email-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="lzy-change-email" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$email                 
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createChangeEmailForm



    private function createDeleteProfileForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <div>{{ lzy-delete-profile-text }}</div>
                <button class="lzy-button lzy-login-form-button lzy-delete-profile-request-button">{{ lzy-delete-profile-request-button }}</button>

            </div><!-- /account-form -->

EOT;

        $this->lzyPage->addPopup([
            'type' => 'confirm',
            'text' => '{{ lzy-delete-profile-confirm-prompt }}',
            'triggerSource' => '.lzy-delete-profile-request-button',
            'onConfirm' => 'lzyReload("?lzy-user-admin=lzy-delete-account");',
            'onCancel' => 'lzyReload();',
        ]);
        $this->lzyPage->addJQFiles('USER_ADMIN');
        return $str;
    } // createDeleteProfileForm





    private function createAccessLinkForm($user, $notification, $message = '')
    {
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;
        $accessCode = $this->lzy->auth->getAccessCode();
        $delAccessCode = '';
        if ($accessCode) {
            $delAccessCode = '<button class="lzy-button lzy-login-form-button lzy-accesslink-delete-button">{{ lzy-accesslink-delete-button }}</button>';
        }

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <div>{{ lzy-create-accesslink-text }}</div>
                <button class="lzy-button lzy-login-form-button lzy-accesslink-request-button">{{ lzy-accesslink-request-button }}</button>
                $delAccessCode
                <div class="lzy-create-accesslink-response"></div>

            </div><!-- /account-form -->

EOT;
        $jq = <<<EOT
$('.lzy-accesslink-request-button').click(function() {
    console.log('requesting access-link');
    $( this ).prop('disabled', true).addClass('lzy-disabled');
    $.ajax({
          url: "./?lzy-user-admin=createAccessLink"
    }).done(function( data ) {
        $('.lzy-create-accesslink-response').html( data );
    });
});
$('.lzy-accesslink-delete-button').click(function() {
    console.log('requesting deletion of access-link');
    $( this ).prop('disabled', true).addClass('lzy-disabled');
    $.ajax({
          url: "./?lzy-user-admin=deleteAccessLink"
    }).done(function( data ) {
        $('.lzy-create-accesslink-response').html( data );
    });
});
EOT;
        $this->lzy->page->addJq($jq);

        // logout before invoking access-link
        // -> this is to make sure that the access-links remains visible to the user, so he can create a bookmark or desktop icon
        $js = <<<EOT

function lzyInvokeAccessLink(url) {
    $.ajax({
        url: "./?logout"
    }).done(function() {
        window.location = url;
    });
}

EOT;
        $this->lzy->page->addJs($js);

        return $str;
    } // createPWAccessForm





    // --- Render Form Elements: -------------------------------------------------
    private function createSubmitButton($prefix, $class = 'lzy-admin-submit-button')
    {
        return <<<EOT
                    <input type="submit" id="lzy-login-submit-button{$this->inx}" class="$class {$prefix}submit-button lzy-button" value="{{ {$prefix}send }}">

EOT;
    } // createSubmitButton




    private function createCancelButton($prefix)
    {
        return <<<EOT
                    <button id="lzy-login-cancel-button{$this->inx}" class="lzy-admin-cancel-button lzy-button" onclick="lzyReload(); return false;">{{ lzy-admin-cancel-button }}</button>

EOT;
    }




    private function renderPasswordInput($prefix, $required = true, $hideShowPwIcon = false, $renderPwRepeatField = '')
    {
        $i = '';
        if (preg_match('/(\d+)/', $prefix, $m)) {
            $i = $m[1];
        }
        $name = rtrim($prefix, '-');
        $required = ($required) ? ' required aria-required="true"': '';
        $showPwIcon = (!$hideShowPwIcon) ? '<div class="lzy-form-show-password"><a href="#" aria-label="{{ lzy-login-show-password }}">'.$this->showPwIcon.'</a></div>': '';

        if ($renderPwRepeatField) {
            $renderPwRepeatField = <<<EOT
                        <label for="lzy-login-password2{$this->inx}" class="lzy-form-password-label lzy-form-password-label2">
                            <span>{{ {$prefix}prompt2 }}:</span>
                            <a href="#" class="lzy-admin-show-info lzy-admin-show-info2" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info {$prefix}info" style="display: none">{{ {$prefix}info2 }}</span>
                        </label>
                        <input type="password" id="lzy-login-password2{$this->inx}" class="lzy-form-password lzy-form-password2" name='{$name}$i'$required />

EOT;

        }
        return <<<EOT
                    <div class="lzy-form-element">
                        <label for="lzy-login-password{$this->inx}" class="lzy-form-password-label">
                            <span>{{ {$prefix}prompt }}:</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info {$prefix}info" style="display: none">{{ {$prefix}info }}</span>
                        </label>
$showPwIcon
                        <input type="password" id="lzy-login-password{$this->inx}" class="lzy-form-password" name='{$name}$i'$required />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
$renderPwRepeatField
                    </div>

EOT;
    }




    private function renderEMailInput($prefix, $required = false, $forceEmailType = false, $prev = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $type = ($forceEmailType) ? 'email': 'text';
        $js = ($forceEmailType) ? ' onkeyup="this.setAttribute(\'value\', this.value);"': '';

        return <<<EOT
                    <div class="lzy-form-element">
                        <label for="lzy-login-email{$this->inx}" class="lzy-form-email-label">
                            <span>{{ {$prefix}prompt }}:</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info" style="display: none">{{ {$prefix}info-text }}</span>
                            $prev
                        </label>
                        <input type="$type" id="lzy-login-email{$this->inx}"  class="lzy-login-email" name="{$prefix}email"$required placeholder="name@domain.net"$js>
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions" style="display: none;">{{ lzy-error-email-required }}</output>
                    </div>
EOT;
    }




    private function renderUsernameInput($prefix)
    {
        return <<<EOT
                    <div class="lzy-form-element">
                        <label for="lzy-login-username{$this->inx}" class="lzy-form-username-label">
                            <span>{{ {$prefix}username-prompt }}:</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}username-info-title }}" aria-label="{{ {$prefix}username-info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info" style="display: none">{{ {$prefix}username-info-text }}</span>
                        </label>
                        <input type="text" id="lzy-login-username{$this->inx}" class="lzy-login-username" name="{$prefix}username" required aria-required="true" value="{$this->un_preset}" />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </div>

EOT;
    }


    private function renderHiddenInput($fieldName, $value)
    {
        return "\t\t\t<input type='hidden' name='$fieldName' value='$value' />\n";
    }



    private function renderTextlineInput($prefix, $fieldName, $required = false, $placeholder = '', $preset = '', $prev = '')
    {

        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        $preset = ($preset) ? " value='$preset'" : '';
        return <<<EOT
                    <div class="lzy-form-element">
                        <label for="lzy-login-textinput{$this->inx}" class="lzy-form-textinput-label">
                            <span>{{ {$prefix}{$fieldName}-prompt }}:</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info" style="display: none">{{ {$prefix}{$fieldName}-login-info }}</span>
                            $prev
                        </label>
                        <input type="text" id="lzy-login-textinput{$this->inx}" class="lzy-login-textinput" name="{$prefix}$fieldName"$required$placeholder$preset />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </div>

EOT;
    }


    private function renderTextareaInput($prefix, $fieldName, $required = false, $placeholder = '', $preset = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        return <<<EOT
                    <div class="lzy-form-element">
                        <label for="lzy-textarea{$this->inx}" class="lzy-textarea-label">
                            <span>{{ {$prefix}prompt }}:</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
                        </label>
                        <textarea id="lzy-textarea{$this->inx}" class="lzy-textarea lzy-textarea{$this->inx}" name="{$prefix}$fieldName"$required$placeholder>$preset</textarea>
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </div>
EOT;
    } // renderTextareaInput




    private function renderCheckbox($prefix, $fieldName)
    {
        return <<<EOT
                    <div class="lzy-form-element">
                        <input type="checkbox" id="lzy-checkbox{$this->inx}" class="lzy-form-checkbox" name="{$prefix}$fieldName" />
                        <label for="lzy-checkbox{$this->inx}" class="lzy-checkbox-label">
                            <span>{{ {$prefix}prompt }}</span>
                            <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                            <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
                        </label>
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </div>
EOT;
    } // renderCheckbox




    public function renderLoginLink( $userRec )
    {
        $linkToThisPage = $GLOBALS['globalParams']['pageUrl'];
        if ($this->loggedInUser) {
            $logInVar = $this->renderLoginAccountLink( $userRec );

        } else {
            if ($this->config->isLocalhost) {
                if ($this->config->admin_autoAdminOnLocalhost) {
                    $loggedInUser = 'Localhost-Admin';
                } else {
                    $loggedInUser = '{{ LoginLink }}';
                }
            } else {
                $loggedInUser = '{{ LoginLink }}';
            }
            $logInVar = <<<EOT
<div class='lzy-login-link'><a href='$linkToThisPage?login' class='lzy-login-link' title="$loggedInUser">{{ lzy-login-icon }}</a></div>

EOT;
        }
        return $logInVar;
    } // renderLoginLink




    public function renderLoginMenu( $userRec )
    {
        $logInVar = '';
        if ($this->loggedInUser) {
            $logInVar = $this->renderLoginAccountMenu( $userRec );
            $this->lzyPage->addPopup(['contentFrom' => '.lzy-login-menu', 'triggerSource' => '.lzy-login-link > a']);
        }
        return $logInVar;
    } // renderLoginMenu




    private function renderLoginAccountLink( $userRec )
    {
        $displayName = $this->loggedInUser;
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] && isset($_SESSION["lizzy"]["loginEmail"])) {
            $displayName = $_SESSION["lizzy"]["loginEmail"];
        }

        $logInVar = <<<EOT
<div class="lzy-login-link"> <a href="#" title="{{ lzy-logged-in-as }} $displayName">{{ lzy-login-icon }}</a></div>

EOT;
        return $logInVar;
    } // renderLoginAccountLink




    private function renderLoginAccountMenu( $userRec )
    {
        $pageUrl = $GLOBALS['globalParams']['pageUrl'];
        $username = $this->loggedInUser;
        $locked = isset($userRec['locked']) && $userRec['locked'];
        $groups = $userRec['groups'];
        $option = '';
        if ($this->config->admin_userAllowSelfAdmin && !$locked) {
            $option = "\t\t\t<li><a href='$pageUrl?admin=edit-profile'>{{ Your Profile }}</a></li>\n";
        }
        if ($GLOBALS["globalParams"]["isAdmin"]) {
            if ($this->config->admin_enableSelfSignUp) {
                $option .= "\t\t\t<li><a href='$pageUrl?admin=invite-new-user'>{{ lzy-adm-invite-new-user }}</a></li>\n";
            } elseif ($GLOBALS["globalParams"]["localCall"]) {
                $option .= "\t\t\t<li>(<span title='-> admin_enableSelfSignUp'>Option \"{{ lzy-adm-invite-new-user }}\" not enabled</span>)</li>\n";
            }
        }
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] && isset($_SESSION["lizzy"]["loginEmail"])) {
            $username = $_SESSION["lizzy"]["loginEmail"];
        }

        $logInVar = <<<EOT
<div class="lzy-login-link-menu">
    <div class="lzy-login-menu" style="display:none;">
        <div>{{ lzy-user-account }} <strong>$username</strong> [$groups]</div>
        <ol>
            <li><a href='$pageUrl?logout'>{{ Logout }}</a></li>$option
        </ol>
    </div>
</div>

EOT;
        return $logInVar;
    } // renderLoginAccountMenu




    public function getUsername()
    {
        return $this->loggedInUser;
    } // getUsername




    public function getDisplayName()
    {
        if (isset($_SESSION['lizzy']['userDisplayName'])) {
            $username = $_SESSION['lizzy']['userDisplayName'];
        }
        if (!$username) {
            $username = $this->loggedInUser;
        }
        return $username;
    } // getDisplayName




    //....................................................
    private function checkInsecureConnection()
    {
        global $globalParams;
        $relaxedHosts = str_replace('*', '', $this->config->site_allowInsecureConnectionsTo);

        if (!$this->config->isLocalhost && !(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on')) {
            $url = str_ireplace('http://', 'https://', $globalParams['pageUrl']);
            $url1 = preg_replace('|https?://|i', '', $globalParams['pageUrl']);
            if (strpos($url1, $relaxedHosts) !== 0) {
                $this->lzyPage->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
            }
            return false;
        }
        return true;
    } // checkInsecureConnection




    private function wrapTag($className, $str)
    {
        if (($className === MSG) && isset($GLOBALS['globalParams']['auth-message'])) {
            $str .= ' '.$GLOBALS['globalParams']['auth-message'];
        }
        if ($str) {
            $str = "\t\t\t<div class='$className'>$str</div>\n";
        }
        return $str;
    }

} // class
