<?php

define('MSG', 'lzy-account-form-message');
define('NOTI', 'lzy-account-form-notification');

define('SHOW_PW_INFO_ICON', '<span class="lzy-icon-info"></span>');
define('SHOW_PW_ICON', '<span class="lzy-icon-show"></span>');
//define('SHOW_PW_ICON', '<svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 width="456.793px" height="456.793px" viewBox="0 0 456.793 456.793" style="enable-background:new 0 0 456.793 456.793;"	 xml:space="preserve"><g><path d="M448.947,218.474c-0.922-1.168-23.055-28.933-61-56.81c-50.707-37.253-105.879-56.944-159.551-56.944c-53.673,0-108.845,19.691-159.551,56.944c-37.944,27.876-60.077,55.642-61,56.81L0,228.396l7.845,9.923c0.923,1.168,23.056,28.934,61,56.811c50.707,37.254,105.878,56.943,159.551,56.943c53.672,0,108.844-19.689,159.551-56.943c37.945-27.877,60.078-55.643,61-56.811l7.846-9.923L448.947,218.474z M228.396,312.096c-46.152,0-83.699-37.548-83.699-83.699c0-46.152,37.547-83.699,83.699-83.699s83.7,37.547,83.7,83.699C312.096,274.548,274.548,312.096,228.396,312.096z M41.685,228.396c9.197-9.872,25.32-25.764,46.833-41.478c13.911-10.16,31.442-21.181,51.772-30.305c-15.989,19.589-25.593,44.584-25.593,71.782s9.604,52.193,25.593,71.782c-20.329-9.124-37.861-20.146-51.771-30.306C67.002,254.159,50.878,238.265,41.685,228.396z M368.273,269.874c-13.912,10.16-31.443,21.182-51.771,30.306c15.988-19.589,25.594-44.584,25.594-71.782s-9.605-52.193-25.594-71.782c20.33,9.124,37.861,20.146,51.771,30.305c21.516,15.715,37.639,31.609,46.832,41.477C405.91,238.268,389.785,254.161,368.273,269.874z"/><path d="M223.646,168.834c-27.513,4-50.791,31.432-41.752,59.562c8.23-20.318,25.457-33.991,45.795-32.917c16.336,0.863,33.983,18.237,33.59,32.228c1.488,22.407-12.725,39.047-32.884,47.191c46.671,15.21,73.197-44.368,51.818-79.352C268.232,175.942,245.969,166.23,223.646,168.834z"/></g></svg>');

$lizzyAccountCounter = 0;



class UserAccountForm
{
    private $un_preset = '';

    public function __construct($lzy, $infoIcon = SHOW_PW_INFO_ICON)
//    public function __construct($lzy, $infoIcon = '&#9432;')
    {
        global $lizzyAccountCounter;
        $this->showPwIcon = SHOW_PW_ICON;
//        $this->showPwIcon = <<<'EOT'
//<svg width="100%" height="100%" viewBox="0 0 512 512" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:1.41421;">
//    <g>
//        <path d="M499.4,250.8C448.4,164.5 355.8,110.4 256,110.4C156.2,110.4 63.5,164.5 12.6,250.8C10.5,253.9 10.5,258.1 12.6,261.2C63.6,347.5 156.2,401.6 256,401.6C355.8,401.6 448.5,347.5 499.4,261.2C501.5,258.1 501.5,253.9 499.4,250.8ZM256,380.8C165.5,380.8 81.2,333 34.4,256C81.2,179 165.5,131.2 256,131.2C346.5,131.2 430.8,179 477.6,256C430.8,333 346.5,380.8 256,380.8Z" style="fill-rule:nonzero;"/>
//        <path d="M256,162.4C204,162.4 162.4,204 162.4,256C162.4,308 204,349.6 256,349.6C308,349.6 349.6,308 349.6,256C349.6,204 308,162.4 256,162.4ZM256,328.8C215.4,328.8 183.2,296.5 183.2,256C183.2,215.5 215.5,183.2 256,183.2C296.5,183.2 328.8,215.5 328.8,256C328.8,296.5 296.6,328.8 256,328.8Z" style="fill-rule:nonzero;"/>
//        <path d="M256,214.4L256,235.2C267.4,235.2 276.8,244.6 276.8,256C276.8,267.4 267.4,276.8 256,276.8C244.6,276.8 235.2,267.4 235.2,256L214.4,256C214.4,278.9 233.1,297.6 256,297.6C278.9,297.6 297.6,278.9 297.6,256C297.6,233.1 278.9,214.4 256,214.4Z" style="fill-rule:nonzero;"/>
//        <path class="crossed" d="M466.554,127.017L468.71,128.769L470.137,131.153L470.662,133.881L470.223,136.625L468.871,139.053L466.771,140.872L57.991,385.529L55.396,386.521L52.618,386.564L49.992,385.655L47.836,383.903L46.409,381.519L45.884,378.791L46.323,376.047L47.674,373.62L49.774,371.8L458.555,127.143L461.15,126.152L463.928,126.108L466.554,127.017Z"/>
//    </g>
//</svg>
//
//EOT;

        $this->infoIcon = $infoIcon;
        if (!isset($GLOBALS['globalParams']['legacyBrowser']) || $GLOBALS['globalParams']['legacyBrowser']) {
            $this->infoIcon = '(i)';
        }
        if ($lzy) {
            $this->config = $lzy->config;
            $this->page = $lzy->page;
            if (isset($lzy->trans)) {      //??? hack -> needs to be cleaned up: invoked from diff places
                $this->trans = $lzy->trans;
            } else {
                $this->trans = $lzy;
            }
            $this->trans->readTransvarsFromFile('~sys/config/admin.yaml');
            $this->checkInsecureConnection();
            $this->page->addModules('USER_ADMIN');
        } else {
            $this->config = null;
            $this->page = null;
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
        if ($returnRaw) {
            return $str;
        }

        $this->page->addOverride($str);
        $this->page->addModules('PANELS');
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
        setStaticVariable('self-signup-to-group', $group);
        if ($message) {
            $str = $this->createSignUpForm($notification, '');
            $str = str_replace('$$', $str, $message);

        } else {
            $str = "<h2>{{ lzy-self-sign-up }}</h2>";
            $this->page = new Page;
            $str .= $this->createSignUpForm($notification);
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
        <input type="submit" class='lzy-button' value="{{ submit }}" />
    </form>

    <p> {{ $prefix sent2 }} $validUntilStr</p>
    <p> {{ $prefix sent3 }}</p>
    {{^ lzy-sign-up further info }}
    </div>

EOT;
        return $form;
    }



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
        $emailForm = $this->createChangeEmailForm($user, $notification, $message);
        $delete = $this->createDeleteProfileForm($user, $notification, $message);

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
            
            <h2>{{ lzy-change-e-mail }} ($email)</h2>
$emailForm
            </div><!-- /lzy-panel-page -->
    
    
            <div>
            <h1>{{ lzy-delete-profile }}</h1>
            
            <h2>{{ lzy-delete-profile }}</h2>
$delete

            </div><!-- /lzy-panel-page -->
    
    </div><!-- / .lzy-panels-widget -->

EOT;

        $userAdmCss = '~/css/user_admin.css';
        if (!file_exists(resolvePath($userAdmCss))) {
            $userAdmCss = '';
        }
        $this->page->addModules("USER_ADMIN, $userAdmCss" );

        return $html;
    }





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

        $this->page->addJQFiles('USER_ADMIN');
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

        $this->page->addJQFiles('USER_ADMIN');
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

        $this->page->addJQFiles('USER_ADMIN');
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

        $this->page->addJQFiles('USER_ADMIN');
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

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm




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

        $this->page->addJQFiles('USER_ADMIN');
        $jq = "\t$('.lzy-change-password-cancel').click(function(){ lzyReload(); });\n";
        $this->page->addJq($jq);
        return $str;
    } // createPWAccessForm





    public function createChangeUsernameForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $username = $this->renderTextlineInput('lzy-change-user-', 'username');
        $this->inx++;
        $displayName = $this->renderTextlineInput('lzy-change-user-', 'displayname');
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

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm





    private function createChangeEmailForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-change-user-request-', true,true);
//        $email = $this->renderEMailInput('lzy-change-user-email-', true,true);
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

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm



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

        $this->page->addPopup([
            'type' => 'confirm',
            'text' => '{{ lzy-delete-profile-confirm-prompt }}',
            'triggerSource' => '.lzy-delete-profile-request-button',
            'onConfirm' => 'lzyReload("?lzy-user-admin=lzy-delete-account");',
            'onCancel' => 'lzyReload();',
        ]);
        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm






    private function createSubmitButton($prefix)
    {
        return <<<EOT
                    <button id="lzy-login-submit-button{$this->inx}" class="lzy-admin-submit-button {$prefix}submit-button lzy-button" name="btn_submit" value="submit">{{ {$prefix}send }}</button>

EOT;
    }




    private function createCancelButton($prefix)
    {
        return <<<EOT
                    <button id="lzy-login-cancel-button{$this->inx}" class="lzy-admin-cancel-button lzy-button" onclick="lzyReload(); return false;">{{ lzy-admin-cancel-button }}</button>

EOT;
    }




    private function renderPasswordInput($prefix, $required = true, $hideShowPwIcon = false)
    {
        $i = '';
        if (preg_match('/(\d+)/', $prefix, $m)) {
            $i = $m[1];
        }
        $required = ($required) ? ' required aria-required="true"': '';
        $showPwIcon = (!$hideShowPwIcon) ? '<div class="lzy-form-show-password"><a href="#" aria-label="{{ lzy-login-show-password }}">'.$this->showPwIcon.'</a></div>': '';
//        $showPwIcon = (!$hideShowPwIcon) ? '<div class="lzy-form-show-password"><a href="#" aria-label="{{ lzy-login-show-password }}"><img src="~sys/rsc/show.png" class="login-form-icon" alt="" title="{{ lzy-login-show-password }}" /></a></div>': '';

        return <<<EOT
                    <label for="lzy-login-password{$this->inx}" class="lzy-form-password-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
$showPwIcon
                        <input type="password" id="lzy-login-password{$this->inx}" class="lzy-form-password" name='{$prefix}password$i'$required />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }




    private function renderEMailInput($prefix, $required = false, $forceEmailType = false)
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $type = ($forceEmailType) ? 'email': 'text';
        $js = ($forceEmailType) ? ' onkeyup="this.setAttribute(\'value\', this.value);"': '';

        return <<<EOT
                    <label for="lzy-login-email{$this->inx}" class="lzy-form-email-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}info-text }}</span>
                    </label>
                    <input type="$type" id="lzy-login-email{$this->inx}"  class="lzy-login-email" name="{$prefix}email"$required placeholder="name@domain.net"$js>
                    <output class='lzy-error-message' aria-live="polite" aria-relevant="additions" style="display: none;">{{ lzy-error-email-required }}</output>
EOT;
    }




    private function renderUsernameInput($prefix)
    {
        return <<<EOT
                   <label for="lzy-login-username{$this->inx}" class="lzy-form-username-label">
                        <span>{{ {$prefix}username-prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}username-info-title }}" aria-label="{{ {$prefix}username-info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}username-info-text }}</span>
                        <input type="text" id="lzy-login-username{$this->inx}" class="lzy-login-username" name="{$prefix}username" required aria-required="true" value="{$this->un_preset}" />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }


    private function renderHiddenInput($fieldName, $value)
    {
        return "\t\t\t<input type='hidden' name='$fieldName' value='$value' />\n";
    }



    private function renderTextlineInput($prefix, $fieldName, $required = false, $placeholder = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        return <<<EOT
                   <label for="lzy-login-textinput{$this->inx}" class="lzy-form-textinput-label">
                        <span>{{ {$prefix}{$fieldName}-prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}{$fieldName}-login-info }}</span>
                        <input type="text" id="lzy-login-textinput{$this->inx}" class="lzy-login-textinput" name="{$prefix}$fieldName"$required$placeholder>
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }


    private function renderTextareaInput($prefix, $fieldName, $required = false, $placeholder = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        return <<<EOT
                   <label for="lzy-textarea{$this->inx}" class="lzy-textarea-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
                        
                        <textarea id="lzy-textarea{$this->inx}" class="lzy-textarea" name="{$prefix}$fieldName"$required$placeholder></textarea>
                        
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }




    public function renderLoginLink( $userRec )
    {
        $linkToThisPage = $GLOBALS['globalParams']['pageUrl'];
        if ($this->loggedInUser) {
            $logInVar = $this->renderLoginAccountMenu( $userRec );
            $this->page->addPopup(['contentFrom' => '.lzy-login-menu', 'triggerSource' => '.lzy-login-link-menu > a']);

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
    }




    /**
     * @param $username
     * @return string
     */
    private function renderLoginAccountMenu( $userRec )
    {
        $pageUrl = $GLOBALS['globalParams']['pageUrl'];
//        $displayName = $this->getDisplayName();
        $displayName = $this->loggedInUser;
        $locked = isset($userRec['locked']) && $userRec['locked'];
        $option = '';
        if ($this->config->admin_userAllowSelfAdmin && !$locked) {
            $option = "\t\t\t<li><a href='$pageUrl?admin=edit-profile'>{{ Your Profile }}</a></li>\n";
        }
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] && isset($_SESSION["lizzy"]["loginEmail"])) {
            $displayName = $_SESSION["lizzy"]["loginEmail"];
        }

        $logInVar = <<<EOT
<div class="lzy-login-link-menu"> <a href="#" title="{{ Logged in as }} $displayName">{{ lzy-login-icon }}</a>
    <div class="lzy-login-menu" style="display:none;">
        <div>{{ User account }} <strong>$displayName</strong></div>
        <ol>
            <li><a href='$pageUrl?logout'>{{ Logout }}</a></li>$option
        </ol>
    </div>
</div>

EOT;
        return $logInVar;
    }




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
                $this->page->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
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

}
