<?php


class UserLoginBase extends UserAdminBase
{
    private $un_preset = '';

    public function __construct($lzy)
    {
        $lzy->trans->readTransvarsFromFile('~sys/'.LOCALES_PATH.'/admin.yaml', false, true);
        $lzy->page->addModules('USER_ADMIN, POPUPS');
        parent::__construct($lzy);
    } // __construct



    public function render($returnRaw = false, $message = '', $preOpenPanel = 1)
    {
        $this->un_preset = '{{^ lzy-username-preset }}';
        $mode = getUrlArg('byemail')? 'byemail': (getUrlArg('multimode')? 'multimode': '');
        if (!$mode && isset($this->lzy->siteStructure->currPageRec['loginMode'])) {
            $mode = $this->lzy->siteStructure->currPageRec['loginMode'];
        }

        if (stripos($mode, 'email') !== false) { // email or byemail
            $html = $this->createEmailLoginForm($message);
        } elseif (($mode === 'multimode') && ($this->config->admin_enableAccessLink)) {
            $html = $this->createMultimodeLoginForm($message, $preOpenPanel);
        } else {
            $html = $this->createPWLoginForm($message);
        }
        if (!$this->config->admin_enableAccessLink) {
            $this->page->addCss('.lzy-accesscode-enabled { display:none; }');
        }

        // avoid leave-page warning when submitting login
        //  (which could happen, if current page contains a form)
        $jq = <<<EOT
$('.lzy-login-form').submit(function(e) {
    lzyFormUnsaved = false;
});

EOT;
        $this->page->addJq($jq);
        if ($returnRaw) {
            return $html;
        }

        $this->page->addModules('TOOLTIPSTER');
        $this->page->addOverride($html);
        $this->page->setOverrideMdCompile(false);

        return $this->page;
    } // render



    public function createMultimodeLoginForm($message = '', $preOpenPanel = 1)
    {
        parent::init();
        $this->page->addModules('PANELS');
        $inx = $this->inx;
        $message = parent::wrapTag(MSG, $message);

        $html = <<<EOT
$message
    <div id='lzy-login-panels-widget' class="lzy-panels-widget lzy-tilted one-open-only lzy-account-form lzy-login-multi-mode">
		<div  class="lzy-login-without-password">
            <h1>{{ lzy-login-without-password }}</h1>
            <div class='lzy-form-wrapper lzy-form-colored'>
              <form id='lzy-login-form-$inx-1' class='lzy-form  lzy-login-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
              
                <!-- [input] -->
                <div class='lzy-form-field-wrapper lzy-form-field-type-email'>
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-onetimelogin-request-email-$inx'>{{ lzy-onetimelogin-request-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-login-info-text-$inx-1" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-login-info-text-$inx-1" class="lzy-formelem-info-text">{{ lzy-onetimelogin-request-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    <input type='email' id='fld_lzy-onetimelogin-request-email-$inx' name='lzy-onetimelogin-request-email' class='lzy-form-input-elem' aria-describedby='lzy-login-info-text-$inx-1' />
                </div><!-- /field-wrapper -->
                <!-- [submit] -->
                <div class='lzy-form-field-type-buttons'>
                <input type='submit' value='{{ lzy-onetime-link-send }}'  class='lzy-form-button lzy-form-button-submit' />
                </div><!-- /field-wrapper -->
        
              </form>
            </div><!-- /lzy-form-wrapper -->
		</div><!-- /lzy-login-without-password -->

        <div  class="lzy-login-with-password">
            <h1>{{ lzy-login-with-password }}</h1>
            <div class='lzy-form-wrapper lzy-form-colored'>
              <form id='lzy-login-form-$inx-2' class='lzy-form lzy-login-form lzy-form-labels-above lzy-encapsulated' method='post'>
        
                <!-- [lzy-login-username-prompt] -->
                <div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-login-username-$inx'>{{ lzy-login-username-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-login-info-text-$inx-2" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-login-info-text-$inx-2" class="lzy-formelem-info-text">{{ lzy-login-mm-username-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    <input type='text' id='fld_lzy-login-username-$inx' name='lzy-login-username' class='lzy-form-input-elem' aria-describedby='lzy-login-info-text-$inx-2' />
                </div><!-- /field-wrapper -->
        
        
                <!-- [lzy-login-password-prompt:] -->
                <div class='lzy-form-field-wrapper lzy-form-field-type-password'>
                    <div class='lzy-form-pw-wrapper'>
                        <div class='lzy-form-input-elem'>
                            <input type='password' class='lzy-form-password' id='fld_lzy-login-password-$inx' name='lzy-login-password' aria-invalid='false' aria-describedby='lzy-formelem-info-text-$inx-3' />
                            <label class='lzy-form-pw-toggle' for="lzy-form-show-pw-$inx">
                                <input type="checkbox" id="lzy-form-show-pw-$inx" class="lzy-form-show-pw">
                                <img src="~sys/rsc/show.png" class="lzy-form-show-pw-icon" alt="{{ lzy-login-show-password }}" title="{{ lzy-login-show-password }}" />
                            </label>
                        </div><!-- /lzy-form-input-elem -->
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-login-password-$inx'>{{ lzy-login-password-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-formelem-info-text-$inx-3" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-formelem-info-text-$inx-3" class="lzy-formelem-info-text">{{ lzy-login-mm-password-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    </div><!-- /lzy-form-pw-wrapper -->
                </div><!-- /field-wrapper -->
        
        
                <!-- [lzy-login-submit] -->
                <div class='lzy-form-field-type-buttons'>
                <input type='submit' value='{{ lzy-login-submit }}'  class='lzy-form-button lzy-form-button-submit' />
                </div><!-- /field-wrapper -->
        
              </form>
            </div><!-- /lzy-form-wrapper -->
        </div><!-- /.lzy-login-with-password -->
    </div><!-- /#lzy-login-widget -->

EOT;

        return $html;
    } // createMultimodeLoginForm



    private function createEmailLoginForm($message = '')
    {
        parent::init();
        $inx = $this->inx;
        $message = parent::wrapTag(MSG, $message);

        $html = <<<EOT
$message

        <div  class="lzy-login-wrapper lzy-email-login">
            <h1>{{ lzy-login-without-password }}</h1>
            <div class='lzy-form-wrapper lzy-form-colored'>
              <form id='lzy-login-form-$inx-1' class='lzy-form  lzy-login-form lzy-form-labels-above lzy-encapsulated' method='post' novalidate>
              
                <!-- [input] -->
                <div class='lzy-form-field-wrapper lzy-form-field-type-email'>
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-onetimelogin-request-email-$inx'>{{ lzy-onetimelogin-request-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-login-info-text-$inx-1" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-login-info-text-$inx-1" class="lzy-formelem-info-text">{{ lzy-onetimelogin-request-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    <input type='email' id='fld_lzy-onetimelogin-request-email-$inx' name='lzy-onetimelogin-request-email' class='lzy-form-input-elem' aria-describedby='lzy-login-info-text-$inx-1' />
                </div><!-- /field-wrapper -->
                <!-- [submit] -->
                <div class='lzy-form-field-type-buttons'>
                <input type='submit' value='{{ lzy-onetime-link-send }}'  class='lzy-form-button lzy-form-button-submit' />
                </div><!-- /field-wrapper -->
        
              </form>
            </div><!-- /lzy-form-wrapper -->

        </div><!-- /lzy-login-wrapper -->
EOT;

        return $html;
    } // createEmailLoginForm



    private function createPWLoginForm($message = '')
    {
        parent::init();
        $inx = $this->inx;
        $message = parent::wrapTag(MSG, $message);

        $html = <<<EOT
$message

        <div  class="lzy-login-wrapper lzy-simple-login">
            <div class='lzy-form-wrapper lzy-form-colored'>
              <form id='lzy-login-form-$inx-2' class='lzy-form lzy-login-form lzy-form-labels-above lzy-encapsulated' method='post'>
        
                <h2>{{ lzy-login-with-password }}</h2>
                <div class='lzy-form-field-wrapper lzy-form-field-type-text'>
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-login-username-$inx'>{{ lzy-login-username-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-login-info-text-$inx-2" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-login-info-text-$inx-2" class="lzy-formelem-info-text">{{ lzy-login-username-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    <input type='text' id='fld_lzy-login-username-$inx' name='lzy-login-username' class='lzy-form-input-elem' aria-describedby='lzy-login-info-text-$inx-2' />
                </div><!-- /field-wrapper -->
        
        
                <div class='lzy-form-field-wrapper lzy-form-field-type-password'>
                    <div class='lzy-form-pw-wrapper'>
                        <div class='lzy-form-input-elem'>
                            <input type='password' class='lzy-form-password' id='fld_lzy-login-password-$inx' name='lzy-login-password' aria-invalid='false' aria-describedby='lzy-formelem-info-text-$inx-3' />
                            <label class='lzy-form-pw-toggle' for="lzy-form-show-pw-$inx">
                                <input type="checkbox" id="lzy-form-show-pw-$inx" class="lzy-form-show-pw">
                                <img src="~sys/rsc/show.png" class="lzy-form-show-pw-icon" alt="{{ lzy-login-show-password }}" title="{{ lzy-login-show-password }}" />
                            </label>
                        </div><!-- /lzy-form-input-elem -->
                        <span class='lzy-label-wrapper'>
                            <label for='fld_lzy-login-password-$inx'>{{ lzy-login-password-prompt }}</label>
                            <button class="lzy-formelem-show-info" aria-hidden="true" data-tooltip-content="#lzy-formelem-info-text-$inx-3" type="button">
                                <span class="lzy-icon lzy-icon-info" title="{{ lzy-login-show-info-title }}"></span>
                                <span  class="lzy-invisible">
                                    <span id="lzy-formelem-info-text-$inx-3" class="lzy-formelem-info-text">{{ lzy-login-password-info-text }}</span>
                                </span>
                            </button>
                        </span><!-- /lzy-label-wrapper -->
                    </div><!-- /lzy-form-pw-wrapper -->
                </div><!-- /field-wrapper -->

                <div class="lzy-login-alternative-actions">
                    <ul>
                        <li class="lzy-login-forgot-password"><a href="./?login&byemail">{{ lzy-login-forgot-password }}</a></li>
                        <li class="lzy-login-sign-up"><a href="">{{ lzy-login-sign-up }}</a></li>
                    </ul>
                </div>        
        
                <div class='lzy-form-field-type-buttons'>
                <input type='submit' value='{{ lzy-login-submit }}'  class='lzy-form-button lzy-form-button-submit' />
                </div><!-- /field-wrapper -->
        
              </form>
            </div><!-- /lzy-form-wrapper -->
        </div><!-- /lzy-login-wrapper -->
EOT;

        return $html;
    } // createPWLoginForm



    public function renderLoginLink( $userRec )
    {
        $linkToThisPage = $GLOBALS['lizzy']['pageUrl'];
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
            list($header,$logInVar) = $this->renderLoginAccountMenu( $userRec );
            $jq = <<<EOT
$('.lzy-login-icon-btn').click(function() {
    lzyPopup({
        contentFrom: '.lzy-login-menu',
        closeOnBgClick: false, 
        closeButton: true, 
        wrapperClass: 'lzy-login',
        draggable: true,
        header: '$header',
    });
});
EOT;
            $this->page->addJq( $jq );
            $this->page->addModules( 'EVENT_UE' );
            $this->page->addBodyEndInjections( $logInVar );
        }
        return '';
    } // renderLoginMenu



    private function renderLoginAccountLink( $userRec )
    {
        $displayName = $this->loggedInUser;
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] && isset($_SESSION['lizzy']['loginEmail'])) {
            $displayName = $_SESSION['lizzy']['loginEmail'];
        }

        $logInVar = <<<EOT
<div class="lzy-login-link"><button class="lzy-login-icon-btn" title="{{ lzy-logged-in-as }} $displayName">{{ lzy-login-icon }}</button></div>

EOT;
        return $logInVar;
    } // renderLoginAccountLink



    private function renderLoginAccountMenu( $userRec )
    {
        $pageUrl = $GLOBALS['lizzy']['pageUrl'];
        $username = $this->loggedInUser;
        $locked = isset($userRec['locked']) && $userRec['locked'];
        $groups = $userRec['groups'];
        $option = '';

        // user self admin / edit profile option:
        if ($this->config->admin_userAllowSelfAdmin && !$locked) {
            $option = "\t\t\t<li><a href='$pageUrl?edit-profile'>{{ Your Profile }}</a></li>\n";
        }

        // for admins: invite-new-user option:
        if ($GLOBALS['lizzy']['isAdmin']) {
            if ($this->config->admin_enableSelfSignUp) {
                $option .= "\t\t\t<li><a href='$pageUrl?admin=invite-new-user'>{{ lzy-adm-invite-new-user }}</a></li>\n";
            } elseif ($GLOBALS['lizzy']['localHost']) {
                $option .= "\t\t\t<li><span class='lzy-inactive tooltipster' title='Modify config option \"admin_enableSelfSignUp\" to enable'>{{ lzy-adm-invite-new-user }}</span></li>\n";
            }
        }
        if (isset($userRec['groupAccount']) && $userRec['groupAccount'] && isset($_SESSION['lizzy']['loginEmail'])) {
            $username = $_SESSION['lizzy']['loginEmail'];
        }

        $header = "{{ lzy-user-account }} <strong>$username</strong> [$groups]";
        $logInVar = <<<EOT
<div class="lzy-login-link-menu">
    <div class="lzy-login-menu" style="display:none;">
        <ol>
            <li><a href='$pageUrl?logout'>{{ Logout }}</a></li>$option
        </ol>
    </div>
</div>

EOT;
        return [$header, $logInVar];
    } // renderLoginAccountMenu



    public function handleOnetimeLoginRequest( $emailRequest, $rec = null )
    {
        if (!$rec) {
            list($emailRequest, $rec) = $this->lzy->auth->findEmailMatchingUserRec($emailRequest, true);
        }
        if ($emailRequest) {
            if (isset($rec['inactive']) && $rec['inactive']) {  // account set to inactive?
                writeLogStr("Account '{$rec['username']}' is inactive: $emailRequest", LOGIN_LOG_FILENAME);
                $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];

            } elseif (!is_legal_email_address($emailRequest)) { // valid email address?
                writeLogStr("invalid email address in rec '{$rec['username']}': $emailRequest", LOGIN_LOG_FILENAME);
                $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];   //

            } else {
                list( $message ) = $this->sendOneTimeCode($emailRequest, $rec);
                $res = [false, $message, 'Override'];   // if successful, a mail with a link has been sent and user will be authenticated on using that link
                $this->lzy->loginFormRequiredOverride = false;
            }
        } else {
            $res = [false, "<p>{{ lzy-login-user-unknown }}</p>", 'Message'];   //
        }
        return $res;
    } // handleOnetimeLoginRequest



    private function sendOneTimeCode($submittedEmail, $rec)
    {
        $accessCodeValidyTime = isset($rec['accessCodeValidyTime']) ? $rec['accessCodeValidyTime'] : $this->config->admin_defaultAccessLinkValidyTime;

        $message = parent::sendCodeByMail($submittedEmail, 'lzy-ot-access', $accessCodeValidyTime, $rec);

        return $message;
    } // sendOneTimeCode


} // UserLogin