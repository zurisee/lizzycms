<?php


class UserEditProfileBase extends UserAdminBase
{
    public function __construct($lzy, $hash = false)
    {
        parent::__construct($lzy);

        $user = $this->auth->loggedInUser;
        if (!$user || !$this->lzy->config->admin_userAllowSelfAdmin) {
            return '';
        }

        return $this->handleUserEditProfileRequests($hash);
    } // __construct



    private function handleChangePasswordRequest()
    {
        $user = $this->auth->loggedInUser;
        $password = getPostData('lzy-change-password', false, true);
        $password2 = getPostData('lzy-change-password2', false, true);
        $res = $this->auth->isValidPassword($password, $password2);
        if ($res === '') {
            $str = $this->doChangePassword($user, $password);
            $res = [false, $str, 'Message'];

        } else {
            $res = [false, $res, 'Error', '#lizzy-lzy-profile-edit-1'];
        }
        return $res;
    } // handleChangePasswordRequest



    private function handleChangeUsernameRequest()
    {
        return $this->doChangeUsername();
    } // handleChangeUsernameRequest



    public function handleChangeEMailRequest( $email )
    {
        $this->trans->readTransvarsFromFile('~sys/'.LOCALES_PATH.'/admin.yaml', false, true);
        if ($this->auth->findUserRecKey($email, '*')) {
            $str = "{{ lzy-email-changed-email-in-use }}: $email";
            $res = [false, $str, 'Error', '#lzy-profile-edit-3'];
        } else {
            $str = $this->sendChangeMailAddress_Mail($email);
            $res = [false, $str, 'Overlay'];
        }
        return $res;
    } // handleChangeEMailRequest



    public function handleChangeEMailConfirm( $tickRec )
    {
        $email = @$tickRec['email'];
        $requestingUser = @$tickRec['username'];
        if ($requestingUser !== $this->loggedInUser) {
            mylog("Error or hacking attempt...");
            return false;
        }
        return $this->doChangeEMail( $email );
    } // handleChangeEMailConfirm



    public function handleCreateAccesslinkRequest()
    {
        if ($this->lzy->config->admin_userAllowSelfAccessLink) {
            $user = $this->loggedInUser;
            if ($user) {
                $userRec = $this->auth->getLoggedInUser(true);
                $hash = createHash();
                $userRec['accessCode'] = $hash;
                parent::updateDbUserRec($user, $userRec);
                $link = $GLOBALS['lizzy']['pageUrl'].$hash;
                $link = "<div><a href='#' id='lzy-invoke-access-link'>$link</a></div>";
                $msg = $this->trans->translateVariable('lzy-create-accesslink-response1');
                $msg2 = $this->trans->translateVariable('lzy-create-accesslink-response2');
                $msg = "<div>$msg</div>$link<div>$msg2</div>";
            } else {
                $msg = "Error";
            }
            exit($msg);
        }
    } // handleCreateAccesslinkRequest



    public function handleDeletelinkRequest()
    {
        if ($this->lzy->config->admin_userAllowSelfAccessLink) {
            $user = $this->loggedInUser;
            if ($user) {
                $userRecs = $this->auth->getKnownUsers();
                if (!isset($userRecs[$user])) {
                    return false;
                }
                $rec = $userRecs[$user];
                if (isset($rec['accessCode'])) {
                    unset($rec['accessCode']);
                    $res = parent::updateDbUserRec( $user, $rec, true );
                }
                if ($res) {
                    $msg = $this->trans->translateVariable('lzy-create-accesslink-deleted-response');
                } else {
                    $msg = $this->trans->translateVariable('lzy-create-accesslink-delete-failed-response');
                }
            } else {
                $msg = "Error";
            }
            exit($msg);
        }
    } // handleDeletelinkRequest



    private function deleteUserAccount($user = false)
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
        parent::deleteDbUserRec($user);
        $this->auth->logout();
        $msg = $this->trans->translateVariable('lzy-delete-account-success-response');
        reloadAgent('',$msg);
    } // deleteUserAccount



    public function doChangeEMail( $email )
    {
        if ($err = parent::isInvalidEmailAddress($email)) {
            return $err;
        }
        $email = strtolower($email);
        $userRec = $this->auth->getLoggedInUser( true );
        $oldEmail = isset($userRec['email']) ? $userRec['email'] : '';
        $user = $userRec['username'];
        $userRec['email'] = $email;
        if (is_legal_email_address($user) && ($oldEmail === $user)) {
            parent::deleteDbUserRec($user);
            $userRec['username'] = $user = $email;
            parent::addUserToDB($user, $userRec);
        } else {
            parent::updateDbUserRec($user, $userRec);
        }
        $this->auth->loadKnownUsers();
        $this->auth->setUserAsLoggedIn($user, true);
        writeLogStr("email for user changed: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
        return false;
    } // doChangeEMail



    private function doChangePassword($user, $password)
    {
        $str = '';
        $knownUsers = $this->auth->getKnownUsers();
        if (isset($knownUsers[$user])) {
            parent::updateDbUserRec($user, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-password-changed-response }}</div>";
        }
        return $str;
    } // doChangePassword



    public function doChangeUsername()
    {
        $newUsername = getPostData('lzy-change-username', false, true);
        $rec = $this->auth->getLoggedInUser( true );
        $user = $rec['username'];

        $displayName = getPostData('lzy-change-displayname', false, true);

        if (is_legal_email_address($user) && !isset($rec['email'])) {
            $rec['email'] = $user;
        }
        if (!$newUsername && !$displayName) {
            return [false, '{{ lzy-username-change-no-change-response }}', 'Error', '#lzy-profile-edit-2'];
        }
        if ($user === $newUsername) {
            if (!$displayName) {
                return [false, '{{ lzy-username-change-no-change-response }}', 'Error', '#lzy-profile-edit-2'];
            }
            $newUsername = '';
        }

        if (!$newUsername) {
            $newUsername = $user;
            $err = false;
        } else {
            $err = parent::isInvalidUsername($newUsername);
        }
        if ($err) { // user name already in use or invalid!
            return [false, $err, 'Error', '#lzy-profile-edit-2'];
        }
        if ($user !== $rec['username']) {
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-change-illegal-name-response }}</div>";

        } else {
            if ($displayName) {
                if (($dn = $this->auth->findUserRecKey($displayName, 'displayName')) && ($dn !== $newUsername)) {
                    return [false, '{{ lzy-username-change-illegal-displayname-response }}', 'Error', '#lzy-profile-edit-2'];
                } else {
                    $rec['displayName'] = $displayName;
                }
            }
            parent::deleteDbUserRec($user);
            $rec['username'] = $newUsername;
            parent::addUserToDB($newUsername, $rec);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-changed-response }}: $newUsername</div>";
            $res = [false, $str, 'Message'];
            $this->auth->loadKnownUsers();
            $this->auth->setUserAsLoggedIn($newUsername, true);
        }
        return $res;
    } // doChangeUsername



    public function render( $notification = '')
    {
        if (!$this->lzy->config->admin_userAllowSelfAdmin) {
            return '';
        }

        $userRec = $this->auth->getLoggedInUser(true);
        $html = $this->renderEditProfileForm($userRec, $notification);
        $html = "<div class='lzy-edit-profile-wrapper'>\n$html</div>\n";
        $this->page->addModules('PANELS');
        $jq = "initLzyPanel('.lzy-panels-widget', 1);";
        $this->page->addJq( $jq );
        $this->page->addOverlay($html, true, false, 'reload');
        return '';
    } // render



    private function renderEditProfileForm($userRec)
    {
        $loggedIn = $this->auth->getLoggedInUser();
        if (!$loggedIn) {
            return '';
        }

        $user = isset($userRec['username']) ? $userRec['username'] : '';
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] &&
            isset($_SESSION['lizzy']['loginEmail'])) {
            $user = $_SESSION['lizzy']['loginEmail'];
        }
        $email = isset($userRec['email']) ? $userRec['email'] : '';

        $changePwForm = $this->createChangePwForm();
        $changeUnForm = $this->createChangeUsernameForm( $user );
        $changeEmailForm = $this->createChangeEmailForm( $email );
        $deleteProfileForm = $this->createDeleteProfileForm();
        $createAccessLinkForm = $this->createCreateAccessLinkForm();

        $html = <<<EOT

        <h2>{{ lzy-edit-profile }} &laquo;$user&raquo;</h2>
        <div class="lzy-panels-widget lzy-tilted accordion one-open-only lzy-account-form lzy-login-multi-mode">
            <div>
                <h1>{{ lzy-change-password }}</h1>
                <h2>{{ lzy-change-password }}</h2>
$changePwForm      
            
            </div><!-- /lzy-panel-page -->
            
            
            <div>
                <h1>{{ lzy-change-name }}</h1>
                <h2>{{ lzy-change-user-name }}</h2>
$changeUnForm
            </div><!-- /lzy-panel-page -->
    
    
            <div>
                <h1>{{ lzy-change-e-mail }}</h1>
                <h2>{{ lzy-change-e-mail }}</h2>
$changeEmailForm
            </div><!-- /lzy-panel-page -->
$createAccessLinkForm
    
            <div>
                <h1>{{ lzy-delete-profile }}</h1>
                <h2>{{ lzy-delete-profile }}</h2>
$deleteProfileForm
            </div><!-- /lzy-panel-page -->
        </div><!-- / .lzy-panels-widget -->

EOT;

        $this->page->addModules("USER_ADMIN,PANELS,AUXILIARY,TOOLTIPSTER" );

        $jq = <<<'EOT'

			$('.lzy-formelem-show-info').tooltipster({
			    trigger: 'click',
			    contentCloning: true,
			    animation: 'fade',
			    delay: 200,
			    animation: 'grow',
			    maxWidth: 420,
			});

            $('.lzy-profile-edit-form-wrapper .lzy-form-show-pw').click(function(e) {
			    e.preventDefault();
			    let $form = $(this).closest('.lzy-form');
			    let $pw = $('.lzy-form-password', $form);
			    if ($pw.attr('type') === 'text') {
			        $pw.attr('type', 'password');
			        $('.lzy-form-show-pw-icon', $form).attr('src', systemPath+'rsc/show.png');
			    } else {
			        $pw.attr('type', 'text');
			        $('.lzy-form-show-pw-icon', $form).attr('src', systemPath+'rsc/hide.png');
			    }
			});
EOT;
        $this->page->addJq( $jq );

        return $html;
    } // renderEditProfileForm



    private function createChangePwForm()
    {
        $str = <<<EOT

	<div class='lzy-profile-edit-form-wrapper lzy-form-colored'>
	  <form id='lizzy-lzy-profile-edit-1' class='lzy-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
        <div class="lzy-form-top-error-msg"></div>
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
	    	<input type='submit' id='btn_profile-change-pw-submit' value='{{ lzy-change-password-send }}'  class='lzy-form-button lzy-form-button-submit lzy-admin-submit-button lzy-disabled' />
		</div><!-- /field-wrapper -->

      </form>
    </div>
EOT;

        $this->page->addJQFiles('USER_ADMIN');
        $jq = "\t$('.lzy-change-password-cancel').click(function(){ lzyReload(); });\n";
        $this->page->addJq($jq);
        return $str;
    } // createPWAccessForm



    private function createChangeUsernameForm( $user )
    {
        $prevUsername = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-username }} $user)</span>\n";
        $userRec = $this->lzy->auth->getUserRec();
        $currDisplayName = $userRec['displayName'];
        $prevDisplayName = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-displayname }} $currDisplayName)</span>\n";

        $str = <<<EOT

	<div class='lzy-profile-edit-form-wrapper lzy-form-colored'>
	  <form id='lzy-profile-edit-2' class='lzy-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
        <div class="lzy-form-top-error-msg"></div>
        <div class='lzy-form-field-wrapper lzy-form-field-wrapper-1 lzy-form-field-type-text'>
            <span class='lzy-label-wrapper'>
                <label for='lzy-profile-ed-change-user-username-prompt_1'>{{ lzy-change-user-username-prompt }}</label>
                <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-profile-ed-info-text-2-1" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span  class="lzy-invisible">
                        <span id="lzy-profile-ed-info-text-2-1" class="lzy-formelem-info-text">{{ lzy-change-user-username-login-info }}</span>
                    </span>
                </button>
                $prevUsername
            </span><!-- /lzy-label-wrapper -->
            <input type='text' id='lzy-profile-ed-change-user-username-prompt_1' name='lzy-change-username' class='lzy-form-input-elem' aria-describedby='lzy-profile-ed-info-text-2-1' />
        </div><!-- /field-wrapper -->
        
        <div class='lzy-form-field-wrapper lzy-form-field-wrapper-2 lzy-form-field-type-text'>
            <span class='lzy-label-wrapper'>
                <label for='lzy-profile-ed-change-user-displayname-prompt_1'>{{ lzy-change-user-displayname-prompt }}</label>
                <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-profile-ed-info-text-2-2" type="button">
                    <span class="lzy-icon lzy-icon-info"></span>
                    <span class="lzy-invisible">
                        <span id="lzy-profile-ed-info-text-2-2" class="lzy-formelem-info-text">{{ lzy-change-user-displayname-login-info }}</span>
                    </span>
                </button>
                $prevDisplayName
            </span><!-- /lzy-label-wrapper -->
            <input type='text' id='lzy-profile-ed-change-user-displayname-prompt_1' name='lzy-change-displayname' class='lzy-form-input-elem' aria-describedby='lzy-profile-ed-info-text-2-2' />
        </div><!-- /field-wrapper -->

		<div class='lzy-form-field-type-buttons'>
            <input type='reset' id='btn_profile-change-un-cancel' value='{{ lzy-admin-cancel-button }}'  class='lzy-form-button lzy-form-button-cancel' />
	    	<input type='submit' id='btn_profile-change-un-submit' value='{{ lzy-change-username-send }}'  class='lzy-form-button lzy-form-button-submit' />
		</div><!-- /field-wrapper -->

      </form>
    </div>

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm



    private function createChangeEmailForm( $prev )
    {
        if ($prev) {
            $prev = "<span class='lzy-form-label-hint'>({{ lzy-accound-form-old-email }} $prev)</span>";
        }

        $str = <<<EOT

	<div class='lzy-profile-edit-form-wrapper lzy-form-colored'>
	  <form id='lzy-profile-edit-3' class='lzy-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
        <div class="lzy-form-top-error-msg"></div>
		<div class='lzy-form-field-wrapper lzy-form-field-type-email'>
                <span class='lzy-label-wrapper'>
                    <label for='lzy-profile-ed-change-user-request-prompt_1'>{{ lzy-change-user-request-prompt }}</label>
                    <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-profile-ed-info-text-3-1" type="button">
                        <span class="lzy-icon lzy-icon-info"></span>
                        <span  class="lzy-invisible">
                            <span id="lzy-profile-ed-info-text-3-1" class="lzy-formelem-info-text">{{ lzy-change-user-request-info-text }}</span>
                        </span>
                    </button>
                    $prev
                </span><!-- /lzy-label-wrapper -->
			<input type='email' id='lzy-profile-ed-change-user-request-prompt_1' name='lzy-change-email-request' class='lzy-form-input-elem' aria-describedby='lzy-profile-ed-info-text-3-1' />
		</div><!-- /field-wrapper -->

		<div class='lzy-form-field-type-buttons'>
            <input type='reset' id='btn_lizzy-form1_cancel' value='{{ lzy-admin-cancel-button }}'  class='lzy-form-button lzy-form-button-cancel' />
            <input type='submit' id='btn_lizzy-form1_submit' value='{{ lzy-change-user-email-send  }}'  class='lzy-form-button lzy-form-button-submit' />
		</div><!-- /field-wrapper -->

      </form>
    </div>

EOT;
        return $str;
    } // createChangeEmailForm



    private function createDeleteProfileForm( $message = '')
    {
        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <div>{{ lzy-delete-profile-text }}</div>
                <button class="lzy-button lzy-login-form-button lzy-delete-profile-request-button">{{ lzy-delete-profile-request-button }}</button>

            </div><!-- /account-form -->

EOT;

        $jq = <<<'EOT'

$('.lzy-delete-profile-request-button').click(function() {
    lzyReload("?lzy-delete-account",'', '{{ lzy-delete-profile-confirm-prompt }}');
});

EOT;
        $this->page->addJq($jq);
        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createDeleteProfileForm



    private function createCreateAccessLinkForm()
    {
        if (!$this->config->admin_userAllowSelfAccessLink) {
            return '';
        }

        $accessCode = $this->lzy->auth->getAccessCode();
        if ($accessCode) {
            $delAccessCode = '<button class="lzy-button lzy-login-form-button lzy-accesslink-delete-button">{{ lzy-accesslink-delete-button }}</button>';
        }  else {
            $delAccessCode = '<button class="lzy-button lzy-login-form-button lzy-accesslink-delete-button lzy-disabled" disabled="true">{{ lzy-accesslink-delete-button }}</button>';
        }

        $accessLinkForm = <<<EOT

            <div>
                <h1>{{ lzy-create-accesslink }}</h1>
                <h2>{{ lzy-create-accesslink }}</h2>
                <div class="lzy-account-form-wrapper">
                    <div>{{ lzy-create-accesslink-text }}</div>
                    <button class="lzy-button lzy-login-form-button lzy-accesslink-request-button">{{ lzy-accesslink-request-button }}</button>
                    $delAccessCode
                    <div class="lzy-create-accesslink-response"></div>
                </div><!-- /account-form -->
            </div><!-- /lzy-panel-page -->

EOT;
        $jq = <<<EOT
$('.lzy-accesslink-request-button').click(function() {
    console.log('requesting access-link');
    let parent = this;
    $( this ).prop('disabled', true).addClass('lzy-disabled');
    $.ajax({
          url: appRoot + '?lzy-create-accesslink'
    }).done(function( data ) {
        $( parent ).prop('disabled', false).removeClass('lzy-disabled');
        $( '.lzy-accesslink-delete-button' ).prop('disabled', false).removeClass('lzy-disabled');
        $('.lzy-create-accesslink-response').html( data );
    });
});
$('.lzy-accesslink-delete-button').click(function() {
    console.log('requesting deletion of access-link');
    $( this ).prop('disabled', true).addClass('lzy-disabled');
    $.ajax({
          url: appRoot + '?lzy-delete-accesslink'
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

        return $accessLinkForm;
    } // createCreateAccessLinkForm



    private function sendChangeMailAddress_Mail( $email )
    {
        $accessCodeValidyTime = $this->lzy->config->admin_defaultAccessLinkValidyTime;
        $userRec = $this->auth->getLoggedInUser(true);
        list($message) = parent::sendCodeByMail($email, 'lzy-change-email-request', $accessCodeValidyTime, $userRec);
        return $message;
    } // sendChangeMailAddress_Mail




    private function handleUserEditProfileRequests($hash): string
    {
        if ($hash) {
            $tck = new Ticketing();
            $tickRec = $tck->consumeTicket($hash);
            $tickType = @$tickRec['_ticketType'];
        }

        $res = null;
        if (getPostData('lzy-change-password')) {
            $res = $this->handleChangePasswordRequest();

        } elseif (getPostData('lzy-change-username') !== false) {
            $res = $this->handleChangeUsernameRequest();

        } elseif ($email = getPostData('lzy-change-email-request', false, true)) {
            $res = $this->handleChangeEMailRequest($email);

        } elseif ($tickType === 'lzy-change-email-request') {
            $err = $this->handleChangeEMailConfirm($tickRec);
            if ($err) {
                $res = [false, $err, 'Error', '#lzy-profile-edit-3'];
            } else {
                $res = [false, '{{ lzy-email-change-successful }}', 'Message'];
            }

        } elseif (getUrlArg('lzy-create-accesslink', false, true)) {
            $this->handleCreateAccesslinkRequest(); // ajax-request -> exits immediately

        } elseif (getUrlArg('lzy-delete-accesslink', false, true)) {
            $this->handleDeletelinkRequest(); // ajax-request -> exits immediately

        } elseif (getUrlArg('lzy-delete-account', false, true)) {
            $str = $this->deleteUserAccount();
            $this->auth->logout();
            $res = [false, $str, 'Message'];

        } else {
            return '';
        }

        if ($res) {
            if (isset($res[2]) && ($res[2] === 'Overlay')) {
                $this->page->addOverlay($res[1], false, false);

            } elseif ($res[2] === 'Override') {
                $this->page->addOverride($res[1], false, false);

            } elseif ($res[2] === 'Error') {
                $formSel = @$res[3];
                $this->page->addJq("$('$formSel .lzy-form-top-error-msg').html( '{$res[1]}' );");

            } else {
                $this->page->addMessage($res[1], false, false);
            }
        }
        return '';
    } // handleUserEditProfileRequests

} // UserEditProfile