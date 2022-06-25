<?php
/*
 *	Lizzy - main class
 *
 *	Main Class *
*/

define('CONFIG_PATH',           'config/');
define('USER_CODE_PATH',        'code/');
define('SERVICE_CODE_PATH',     USER_CODE_PATH.'service/');
define('PATH_TO_APP_ROOT',      '');
define('SYSTEM_PATH',           basename(dirname(__FILE__)).'/'); // _lizzy/
define('ADMIN_PATH',            SYSTEM_PATH.'admin/');
define('DEFAULT_CONFIG_FILE',   CONFIG_PATH.'config.yaml');
define('DEV_MODE_CONFIG_FILE',  CONFIG_PATH.'dev-mode-config.yaml');

define('PAGES_PATH',            'pages/');
define('HOMEPAGE_PATH',         'home/');
define('DATA_PATH',             'data/');
define('SYSTEM_CACHE_PATH',     '.#sys-cache/');
define('CACHE_PATH',            SYSTEM_CACHE_PATH . 'misc/');
define('MODULES_CACHE_PATH',    '.cache/files/');
define('PAGE_CACHE_PATH',       CACHE_PATH.'pages/');
define('LOGS_PATH',             '.#logs/');
define('MACROS_PATH',           SYSTEM_PATH.'macros/');
define('SYSTEM_STYLESHEET',     SYSTEM_PATH.'css/__lizzy.css');
define('SYSTEM_STYLESHEET_LATE_LOAD', SYSTEM_PATH.'css/__lizzy-async.css');
define('EXTENSIONS_PATH',       SYSTEM_PATH.'extensions/');
define('LOCALES_PATH',          'locales/');
define('USER_INIT_CODE_FILE',   USER_CODE_PATH.'init-code.php');
define('USER_FINAL_CODE_FILE',  USER_CODE_PATH.'final-code.php');
define('USER_VAR_DEF_FILE',     USER_CODE_PATH.'var-definitions.php');
define('ICS_PATH',              'ics/'); // where .ics files are located

define('DEFAULT_TICKETS_PATH', SYSTEM_CACHE_PATH);
define('DAILY_PURGE_FILE',      CONFIG_PATH.'daily-purge.txt');
define('USER_DAILY_CODE_FILE',  USER_CODE_PATH.'@daily-task.php');
define('CACHE_DEPENDENCY_FILE', '.#page-cache.dependency.txt');
define('CACHE_FILENAME',        '.#page-cache.dat');

define('RECYCLE_BIN',           '.#recycleBin/');
define('SYSTEM_RECYCLE_BIN_PATH','~/'.RECYCLE_BIN);
define('RECYCLE_BIN_PATH',      '~page/'.RECYCLE_BIN);

define('LOG_FILE',              LOGS_PATH.'log.txt');
define('ERROR_LOG',             LOGS_PATH.'errlog.txt');
define('ERROR_LOG_ARCHIVE',     LOGS_PATH.'errlog_archive.txt');
define('BROWSER_SIGNATURES_FILE', LOGS_PATH.'browser-signatures.txt');
define('UNKNOWN_BROWSER_SIGNATURES_FILE',     LOGS_PATH.'unknown-browser-signatures.txt');
define('LOGIN_LOG_FILENAME',    LOGS_PATH.'login-log.txt');

define('VERSION_CODE_FILE',     CACHE_PATH.'version-code.txt');
define('UNDEFINED_VARS_FILE',   CACHE_PATH.'undefinedVariables.yaml');
define('FAILED_LOGIN_FILE',     CACHE_PATH.'_failed-logins.yaml');
define('HACK_MONITORING_FILE',  CACHE_PATH.'_hack_monitoring.yaml');
define('ONETIME_PASSCODE_FILE', CACHE_PATH.'_onetime-passcodes.yaml');
define('HACKING_THRESHOLD',     10);
define('HOUSEKEEPING_FILE',     SYSTEM_CACHE_PATH.'_housekeeping.txt');
define('IMG_DEFAULT_MAX_DIM',   '1920x1024');
define('MIN_SITEMAP_INDENTATION', 4);
define('REC_KEY_ID', 	        '_key');
define('TIMESTAMP_KEY_ID', 	    '_timestamp');
define('PASSWORD_PLACEHOLDER', 	'●●●●');
define('TRANSVAR_ARG_QUOTES', 	'!@#$%&:?');    // Special quotes to enclose transvar args: e.g. %% xy %%
 // Note: special case '!!' -> skips translation to HTML-quotes

define('MKDIR_MASK',            0700); // permissions for file access by Lizzy
define('MKDIR_MASK_WEBACCESS',  0755); // permissions for files cache

define('PAGED_POLYFILL_SCRIPT', '~sys/third-party/paged.polyfill/paged.polyfill.min.js');

$localeFiles = ['config/user_variables.yaml', SYSTEM_PATH.LOCALES_PATH.'*'];


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DomCrawler\Crawler;


require_once SYSTEM_PATH.'auxiliary.php';

$lizzy = array(
                                    // example: URL='xy/', folder='pages/xy/'
	'pathToRoot' => null,			// ../
	'filepathToRoot' => null,		// ../../
	'pagePath' => null,				// xy/
	'pathToPage' => null,			// pages/xy/
    'path_logPath' => null,
    'activityLoggingEnabled' => null,
    'errorLoggingEnabled' => null,
    'errorLogFile' => LOG_FILE,
    'cachingActive' => true,
);


class Lizzy
{
	private $systemPath = SYSTEM_PATH;
	public  $pathToRoot;
	public  $pagePath;
    public  $reqPagePath;
	public  $siteStructure;
	public  $trans;
	public  $page;
	private $editingMode = false;
	private $debugLogBuffer = '';
	private $cspHeader = '';
	private $loginFormRendered = false;
	private $loginFormRequired = false;
	public  $loginFormRequiredOverride = true; // to suppress login form in case of onetime code sent
    public  $ticketHash = [];
    public  $keepAccessCode = false;




    public function __construct()
    {
        startTimer();

        session_start();
        $GLOBALS['lizzy'] = [];
        if (isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']) {
            $user = $_SESSION['lizzy']['user'];
            $GLOBALS['lizzy']['user'] = $user;
        } else {
            $_SESSION['lizzy']['user'] = '';
            $GLOBALS['lizzy']['user'] = '';
            $user = 'anon';
        }

        $this->debugLogBuffer = "REQUEST_URI: {$_SERVER['REQUEST_URI']}  FROM: [$user]\n";
        if ($_REQUEST) {
            $this->debugLogBuffer .= "REQUEST: ".var_r($_REQUEST, 'REQUEST', true)."\n";
        }

        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            die("Error: file not found: ".$configFile);
        }

        $this->setTimezone(); // i.e. timezone of host

        // Check cache and render if available:
        $this->checkAndRenderCachePage();    // exits immediately, never returns

        $this->loadRequired();

        $this->checkInstallation();

        $srv = new ServiceTasks($this);
        $srv->runServiceTasks();

		$this->init();
		$this->setupErrorHandling();

        $this->initializeSiteInfrastructure();
        $this->keepAccessCode = false;

        $this->trans->addVariable('debug_class', '');   // just for compatibility

        $srv->runServiceTasks(2);
    } // __construct



    private function loadRequired()
    {
        require_once SYSTEM_PATH.'vendor/autoload.php';
        require_once SYSTEM_PATH.'page.class.php';
        require_once SYSTEM_PATH.'transvar.class.php';
        require_once SYSTEM_PATH.'lizzy-markdown.class.php';
        require_once SYSTEM_PATH.'scss.class.php';
        require_once SYSTEM_PATH.'defaults.class.php';
        require_once SYSTEM_PATH.'sitestructure.class.php';
        require_once SYSTEM_PATH.'authentication.class.php';
        require_once SYSTEM_PATH.'image-resizer.class.php';
        require_once SYSTEM_PATH.'datastorage2.class.php';
        require_once SYSTEM_PATH.'uadetector.class.php';
        require_once ADMIN_PATH. 'user-admin-base.class.php';
        require_once ADMIN_PATH. 'user-login.class.php';
        require_once SYSTEM_PATH.'ticketing.class.php';
        require_once SYSTEM_PATH.'service-tasks.class.php';
        require_once SYSTEM_PATH.'tree.class.php';
    } // loadRequired



    private function init()
    {
        $this->sessionId = session_id();

        $this->getConfigValues(); // from config/config.yaml

        $this->determineLanguage();

        // get info about browser
        $ua = $this->getBrowser();

        if ($this->config->debug_debugLogging && $this->debugLogBuffer) {
            writeLog( $this->debugLogBuffer . "  ($ua)" );
        }

        $this->setLocale();

        $this->localHost = $this->config->localHost;

        register_shutdown_function('handleFatalPhpError');

        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));

        $GLOBALS['lizzy']['isAdmin'] = false;
        $GLOBALS['lizzy']['activityLoggingEnabled'] = $this->config->admin_activityLogging;
        $GLOBALS['lizzy']['errorLoggingEnabled'] = $this->config->debug_errorLogging;

        $_SESSION['lizzy']['isLocalhost'] = $this->localHost;
        $_SESSION['lizzy']['configDbPermission'] = false;

        $this->trans = new Transvar($this);
        $this->setTransvars0();
        $this->page = new Page($this);

        $this->setDataPath();

        $this->trans->readTransvarsFromFiles([
            SYSTEM_PATH.LOCALES_PATH.'sys_vars.yaml',
            CONFIG_PATH.'user_variables.yaml'
        ]);

        $this->auth = new Authentication($this);

        $this->analyzeHttpRequest();

        $this->auth->authenticate();

        $this->handleTransactionalRequests(); // Entry point for requests from users

        $GLOBALS['lizzy']['auth-message'] = $this->auth->message;

        $this->config->isPrivileged = false;
        if ($this->auth->isPrivileged()) {
            $this->config->isPrivileged = true;

        } elseif (file_exists(HOUSEKEEPING_FILE)) {  // suppress error msg output if not local host or admin or editor
            ini_set('display_errors', '0');
        }

        $this->handleUrlArgs();

        $this->scss = new SCssCompiler($this);

        // check for url args that require caching to be turned off:
        if (isset($_GET)) {
            $urlArgs = ['config', 'list', 'help', 'admin', 'reset', 'login', 'unused', 'reset-unused', 'remove-unused', 'log', 'info', 'touch'];
            foreach ($_GET as $arg => $value) {
                if (in_array($arg, $urlArgs)) {
                    $this->config->site_enableCaching = false;
                    break;
                }
            }
        }
        $GLOBALS['lizzy']['cachingActive'] = $this->config->site_enableCaching;
        $GLOBALS['lizzy']['site_title'] = $this->trans->translateVariable('site_title');
    } // init



    public function render()
    {
		$accessGranted = $this->checkAdmissionToCurrentPage();   // override page with login-form if required

        $this->injectAdminCss();
        $this->setTransvars1();
        // $this->addStandardModules();

        if ($accessGranted) {
            $this->loadFile();        // get content file
        }

        $this->scss->compile();

        $this->injectPageSwitcher();

 //        $this->warnOnErrors();

        $this->setTransvars2();

        if ($accessGranted) {
            $this->runUserRenderingStartCode();
        }
        $this->loadTemplate();

        if ($accessGranted) {
            $this->injectEditor();

            $this->trans->loadUserComputedVariables();
        }

        $this->appendLoginForm();

        $this->handleUrlArgs2();

        $this->handleConfigFeatures();


        // now, compile the page from all its components:
        $html = $this->page->render();

        $this->prepareImages($html);

        $this->applyForcedBrowserCacheUpdate($html);

        $html = $this->resolveAllPaths($html);

        if (getUrlArgStatic('timer')) {
            $timerMsg = 'Page rendering time: '.readTimer( true );
            $html = $this->page->lateApplyMessage($html, $timerMsg);
		}

        $this->runUserFinalCode($html );   // optional custom code to operate on final HTML output

        $this->page->applyContentSecurityPolicy(); // if active, sends CSP via HTTP header

        $this->storeToCache( $html );

        // translate variables shielded from cache:
        if (strpos($html, '{|{|') !== false) {
            $html = $this->trans->translate($html, true);
        }

        header("Server-Timing: total;dur=" . readTimer());

        return $html;
    } // render



    private function handleTransactionalRequests()
    {
        // requests in hashes:
        if ($this->ticketHash) {
            $this->handleHashes();
        }

        // requests in POST / GET:
        $transactionalRequests = [
            'lzy-onetimelogin-request-email',
            'edit-profile',
        ];
        $reqs = array_intersect($transactionalRequests, array_keys($_REQUEST));
        if ($reqs) {
            $this->handleGetAndPostRequests( $reqs );
        }

    //        //??? protect from attacks:
    //        if ($un = getUrlArg('lzy-check-username', true)) {
    //            $exists = $this->auth->findUserRecKey( $un );
    //            if ($exists) {
    //                $msg = 'lzy-signup-username-not-available';
    //                $msg = $this->trans->translateVariable($msg, true);
    //            } else {
    //                $msg = 'ok';
    //            }
    //            exit( $msg );
    //        }

        // handle response of ?convert data url-arg:
        if (@$_POST['_lzy-form-cmd'] === 'convert-data') {
            preparePath('tmp/');
            $srcFile = 'tmp/'.$_FILES['Input_File']['name'];
            $rawFile = $_FILES['Input_File']['tmp_name'];
            move_uploaded_file($rawFile, $srcFile);
            $dstFormat = $_POST['Output_Format'];
            if (file_exists($srcFile)) {
                $dstFile = fileExt($srcFile, true) . ".$dstFormat";
                $ds = new DataStorage2($srcFile);
                $data = $ds->read();

                $ds2 = new DataStorage2($dstFile);
                $ds2->write($data);
            }
            $this->convertDataResponse = "{{ lzy-data-converted to }} $dstFile";

        // Add First User:
        } elseif (@$_POST['_lzy-form-cmd'] === 'lzy-adduser') {
            $uadm = new UserAdminBase( $this );
            $res = $uadm->handleRequests();
            return $res;
        }
    } // handleTransactionalRequests



    private function handleGetAndPostRequests( $reqs )
    {
        foreach ($reqs as $key) {
            switch ($key) {
                case 'lzy-onetimelogin-request-email':
                    $accForm = new UserLoginBase($this);
                    $res = $accForm->handleOnetimeLoginRequest( getPostData('lzy-onetimelogin-request-email'));
                    break;

                case 'edit-profile':
                    require_once ADMIN_PATH.'user-edit-profile.class.php';
                    $uep = new UserEditProfileBase($this);
                    if (!isset($_REQUEST['lzy-change-email-request'])) {
                        $html = $uep->render();
                    }
                    break;

                default:
                    die("Error in handleTransactionalRequests(): unknown type '$key'");
            }
            $this->initiateInBrowserUserNotification($res);   // inform user about login/logout etc.
        }

    } // handleGetAndPostRequests



    private function handleHashes()
    {
        if (!$this->ticketHash) {
            return;
        }
        $tck = new Ticketing();
        foreach ($this->ticketHash as $category => $hash) {
            $tickRec = $tck->previewTicket( $hash );
            if ($tickRec) {
                $tickType = @$tickRec['_ticketType'];
                $res = '';
                switch ($tickType) {
                    case 'lzy-ot-access':
                        $res = $this->auth->validateOnetimeAccessCode( $hash ); // reloads & never returns if login successful
                        break;

                    case 'lzy-change-email-request':
                        require_once ADMIN_PATH.'user-edit-profile.class.php';
                        new UserEditProfileBase($this, $hash);
                        break;

                    case 'landing-page':
                        $this->activateLandingPage( $tickRec );
                        break;

                    case 'invite-user':
                    case 'user-self-signup':
                    case 'lzy-confirm-email':
                        require_once ADMIN_PATH.'user-admin.class.php';
                        new UserAdmin($this, $hash);
                        break;

                    default:
                        mylog("Warning in handleHashes(): unknown tickType '$tickType'");
                        return;
                }
                $this->initiateInBrowserUserNotification($res);   // inform user about login/logout etc.

            } else {
                // if it wasn't a ticketHash, check whether it's a user's accessCode
                if ($this->auth->validateAccessCode( $hash )) {
                    if (strpos('lzy-onetimelogin-request-email,lzy-login-username,lzy-login-password', $category) !== false) {
                        // user must have entered accessCode in one of the login fields, so unset POST var:
                        unset($_REQUEST[$category]);
                    }
                }
            }
        }
    } // handleTransactionalRequests



    private function activateLandingPage( $tickRec )
    {
        $reqPage = $tickRec['landingPage'];
        $this->reqPagePath = $reqPage;
        $user = $tickRec['user'];
        $this->auth->setUserAsLoggedIn($user, true);
        $this->landingPageTickRec = $tickRec;
    } // activateLandingPage



    public function initiateInBrowserUserNotification($res)
    {
        if (is_array($res) && isset($res[2])) { // [login/false, message, communication-channel]
            if ($res[2] === 'Overlay') {
                $this->page->addOverlay($res[1], false, false);

            } elseif ($res[2] === 'Override') {
                $this->page->addOverride($res[1], false, false);

            } elseif ($res[2] === 'LoginForm') {
                $accForm = new UserAccountForm($this);
                $form = $accForm->renderLoginForm($this->message, $res[1], true);
                $this->page->addOverlay($form, true, false);

            } else {
                $this->page->addMessage($res[1], false, false);
            }
        }
    } // initiateInBrowserUserNotification



    public function resolveAllPaths( $html )
    {
        global $lizzy;
        $appRoot = $lizzy['appRootUrl'];
        $pagePath = $lizzy['pagePath'];

        if (!$this->config->admin_useRequestRewrite) {
            resolveHrefs($html);
        }

        // Handle resource accesses first: src='~page/...' -> local to page but need full path:
        $p = $appRoot.$this->pathToPage;
        $html = preg_replace(['|(src=[\'"])(?<!\\\\)~page/|', '|(srcset=[\'"])(?<!\\\\)~page/|'], "$1$p", $html);

        // Handle all other special links:
        if ($this->siteStructure->isPageDislocated()) {
            $p = $appRoot.$pagePath;
        } else {
            $p = '';
        }
        $from = [
            '|(?<!\\\\)~/|',
            '|(?<!\\\\)~data/|',
            '|(?<!\\\\)~sys/|',
            '|(?<!\\\\)~ext/|',
            '|(?<!\\\\)~page/|',
        ];
        $to = [
            $appRoot,
            $appRoot.$lizzy['dataPath'],
            $appRoot.SYSTEM_PATH,
            $appRoot.EXTENSIONS_PATH,
            $p,   // for page accesses
        ];

        $html = preg_replace($from, $to, $html);

        // remove shields: e.g. \~page
        $html = preg_replace('|(?<!\\\\)\\\\~|', "~", $html);

        return $html;
    } // resolveAllPaths



    private function applyForcedBrowserCacheUpdate( &$html )
    {
        // forceUpdate adds some url-arg to css and js files to force browsers to reload them
        // Config-param 'debug_forceBrowserCacheUpdate' forces this for every request
        // 'debug_autoForceBrowserCache' only forces reload when Lizzy detected changes in those files

        if (isset($_SESSION['lizzy']['reset']) && $_SESSION['lizzy']['reset']) {  // Lizzy has been reset, now force browser to update as well
            $forceUpdate = getVersionCode( true );
            unset($_SESSION['lizzy']['reset']);

        } elseif ($this->config->debug_forceBrowserCacheUpdate === 'mobile') {
            if (!$this->isMobile) {
                return;
            }
            $forceUpdate = getVersionCode( true );

        } elseif (!$this->config->debug_forceBrowserCacheUpdate ||
            ($this->config->debug_forceBrowserCacheUpdate !== 'false') ||
            getUrlArg('fup')) {
            $forceUpdate = getVersionCode( true );

        } else {
            return;
        }
        if ($forceUpdate) {
            // append fup to css links:
            $html = preg_replace("/(<link\s+href= (['\"]) .+? ) ['\"]/mx", "$1$forceUpdate$2", $html);

            // append fup to js file refs:
            $html = preg_replace("/(<script\s+src= (['\"]) .+? ) ['\"]/mx", "$1$forceUpdate$2", $html);
        }
    } // applyForcedBrowserCacheUpdate



    private function setupErrorHandling()
    {
        global $lizzy;
        if ($this->config->debug_errorLogging) {
            $lizzy['errorLogFile'] = LOG_FILE;
        } else {
            $lizzy['errorLogFile'] = '';
        }

        if ($this->auth->checkGroupMembership('editors') || $this->localHost) {     // set displaying errors on screen:
            $old = ini_set('display_errors', '1');  // on
            error_reporting(E_ALL);

        } else {
            $old = ini_set('display_errors', '0');  // off
            error_reporting(0);
        }
        if ($old === false) {
            fatalError("Error while setting up error handling... (no kidding)", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    } // setupErrorHandling



    private function isRestrictedPage()
    {
        if (isset($this->siteStructure->currPageRec['restricted!'])) {
            $lockProfile = $this->siteStructure->currPageRec['restricted!'];
            return $lockProfile;
        }
        return false;
    } // isRestrictedPage



    private function checkAdmissionToCurrentPage()
    {
        if ($reqGroups = $this->isRestrictedPage()) {     // handle case of restricted page
            $ok = checkPermission($reqGroups, $this );
            if (!$ok) {
                $this->loginFormRequired = true; // login form rendered later in appendLoginForm()
                return false;
            }
            setStaticVariable('isRestrictedPage', $this->auth->getLoggedInUser());
        } else {
            setStaticVariable('isRestrictedPage', false);
        }
        return true;
    } // checkAdmissionToCurrentPage



    private function appendLoginForm()
    {
        $presetUser = getUrlArg('login', true);
        $loginFormRequired = $this->loginFormRequiredOverride && ($this->loginFormRequired || ($presetUser !== null));
        if (!$loginFormRequired) {
            return '';
        }
        if ( !$this->auth->getKnownUsers() ) { // don't bother with login if there are no users
            return;
        }

        if ($presetUser !== null) { // explicit url-arg request to present login form
            mylog('rendering login form as popup triggered by url-arg', false);
            $this->renderLoginForm( true, $presetUser );

        } else {    // login form required (user tries to access restricted area)
            if ($loginForm = $this->renderLoginForm( false )) {
                $this->page->addOverride($loginForm);
            }
        }
    } // appendLoginForm



    private function analyzeHttpRequest()
    {
        global $lizzy;

        $requestUri         = (isset($_SERVER['REQUEST_URI'])) ? rawurldecode($_SERVER['REQUEST_URI']) : '';
        $requestedPath      = $requestUri;
        $urlArgs               = '';
        if (preg_match('/^ (.*?) [?#&] (.*)/x', $requestUri, $m)) {
            $requestedPath = $m[1];
            $urlArgs = $m[2];
        }

        // extract ticketHash from URL:
        $requestedPath = $this->extractTicketHashes( $requestedPath );

        $requestScheme      = $_SERVER['REQUEST_SCHEME'];               // https
        $domainName         = $_SERVER['HTTP_HOST'];                    // domain.net
        $docRootUrl         = "$requestScheme://$domainName/";          // https://domain.net/
        $absAppRootPath     = dirname($_SERVER['SCRIPT_FILENAME']).'/'; // /home/httpdocs/approot/
        $appRoot            = dirname($_SERVER['SCRIPT_NAME']).'/';     // /approot/
        //$absDocRootPath     = substr($absAppRootPath, 0, -strlen($appRoot)+1); // /home/httpdocs/
        $appRootUrl         = fixPath(commonSubstr( $appRoot, $requestUri, '/'));
        $redirectedAppRootUrl = substr($appRoot, strlen($appRootUrl));

        $s = substr($requestedPath, strlen($appRootUrl));
        $pagePath           = preg_replace('|^'.preg_quote($appRoot).'|', '', $s); // p1/p2/
        $pagePath			= ltrim($pagePath, '/');
        $pathToPage         = "pages/$pagePath";                        // pages/p1/p2/

        $absAppRootUrl      = $docRootUrl.$appRootUrl;                    // https://domain.net/approot/
        $urlToAppRoot       = preg_replace('|[^/]+|', '..', $pagePath); // ../../
        $pathToAppRoot      = preg_replace('|[^/]+|', '..', $pathToPage); // ../../../

        $pageUrl            = rtrim($docRootUrl, '/') . $appRoot . $pagePath;

        /*
        // verification output:
        $check = <<<EOT
Basics:
===========
\$requestUri        $requestUri         // /approot/p1/p2/?x
\$requestedPath     $requestedPath      // /approot/p1/p2/
\$requestScheme     $requestScheme      // https
\$domainName        $domainName         // domain.net

DocRoot:
============
\$docRootUrl        $docRootUrl         // https://domain.net/
\$absDocRootPath    $absDocRootPath     // /home/httpdocs/

AppRoot:
============
\$absAppRootPath    $absAppRootPath     // /home/httpdocs/approot/
\$appRoot           $appRoot            // /approot/
\$absAppRootUrl     $absAppRootUrl      // https://domain.net/ or https://domain.net/path
\$appRootUrl        '$appRootUrl'         // '' or /approot/

Page:
============
\$pagePath          $pagePath           // p1/p2/
\$pathToPage        $pathToPage         // pages/p1/p2/

Up:
============
\$urlToAppRoot      $urlToAppRoot       // ../../
\$pathToAppRoot     $pathToAppRoot      // ../../../

AccessCode:
============
\$accessCode        '$accessCode'       // ABCDEF

Url Args:
============
\$urlArgs           '$urlArgs'       // ?x

EOT;
        file_put_contents('check.txt', $check);
        */

        // set global variables:
        $lizzy['host'] = $docRootUrl;
        $lizzy['requestedUrl'] = $requestUri;
        $lizzy['pageFolder'] = null;
        $lizzy['pagePath'] = null;
        $lizzy['pathToPage'] = null; // needs to be set after determining actually requested page
        $lizzy['pageUrl'] = $pageUrl;
        $lizzy['pagesFolder'] = PAGES_PATH;
        $lizzy['filepathToRoot'] = $pathToAppRoot;
        $lizzy['absAppRoot'] = $absAppRootPath;  // path from FS root to base folder of app, e.g. /Volumes/...
        $lizzy['absAppRootUrl'] = $lizzy['host'] . substr($appRoot, 1);  // path from FS root to base folder of app, e.g. /Volumes/...
        $lizzy['appRootUrl'] = $appRootUrl;  //
        $lizzy['appRoot'] = $appRoot;  // path from docRoot to base folder of app, e.g. 'on/'
        $lizzy['redirectedAppRootUrl'] = $redirectedAppRootUrl;  // the part that has been skipped by .htaccess

        $lizzy['filepathToDocroot'] = preg_replace('|[^/]+|', '..', $appRoot);;

        $lizzy['localHost'] = $this->localHost;
        $lizzy['isLocalhost'] = $this->localHost;
        $lizzy['pagePath'] = $pagePath;   // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed
        $lizzy['urlArgs'] = $urlArgs;     // all url-args received

        // security option: permit only regular text in requests, discard Special characters:
        if ($this->config->feature_filterRequestString) {
            // Example: abc[2]/
            if (preg_match('|[^a-z0-9_/-]|ix', $pagePath)) {
                writeLogStr("Warning: feature_filterRequestString caught suspicious path: '$pagePath'.");
                $pagePath = preg_replace('|[^a-z0-9_/-]*|ix', '', $pagePath);
            }
        }

        // set session variables:
        $_SESSION['lizzy']['pagePath'] = $pagePath;     // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed

        $_SESSION['lizzy']['pathToPage'] = $pathToPage;
        $_SESSION['lizzy']['appRootUrl'] = $absAppRootUrl; // https://domain.net/...
        $_SESSION['lizzy']['absAppRoot'] = $absAppRootPath;

        // set properties:
        $this->pagePath = $pagePath;     // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed
        $this->reqPagePath = $pagePath;
        $this->pageUrl = $pageUrl;
        $this->pathToRoot = $urlToAppRoot;

        // get IP-address
        $this->serverIP = getHostByName( getHostName() );

        // check whether to support legacy browsers -> load jQ version 1
        if ($this->config->feature_supportLegacyBrowsers) {
            $this->config->isLegacyBrowser = true;
            $lizzy['legacyBrowser'] = true;
            writeLog("Legacy-Browser Support activated.");

        } else {
            $overrideLegacy = getUrlArgStatic('legacy');
            if ($overrideLegacy === null) {
                $this->config->isLegacyBrowser = $this->isLegacyBrowser;
            } else {
                $this->config->isLegacyBrowser = $overrideLegacy;
            }
        }
        $lizzy['legacyBrowser'] = $this->config->isLegacyBrowser;
    } // analyzeHttpRequest



    private function getConfigValues()
    {
        $this->config = new Defaults( $this );
        $this->config->pathToRoot = $this->pathToRoot;

        $GLOBALS['lizzy']['logPath'] = $this->config->path_logPath;

        if (!isset($_SESSION['lizzy']['lang'])) {
            if ($this->config->site_multiLanguageSupport) {
                $_SESSION['lizzy']['lang'] = $this->config->site_defaultLanguage;
            } else {
                $_SESSION['lizzy']['lang'] = '';
            }
        }
    } // getConfigValues



    private function loadTemplate()
    {
        $template = $this->getTemplate();

        // shield those variables that need to be replaced at the very end of rendering:
        $template = $this->page->shieldVariablesForLateTranslation($template, [
            'body_classes',
            'body_tag_attributes',
            'head_injections',
            'body_tag_injections',
            'body_top_injections',
            'content',
            'body_end_injections',
        ]);

        $this->page->addTemplate($template);
    } // loadTemplate



	private function injectEditor()
	{
        $admission = $this->auth->checkGroupMembership('editors');
        if (!$admission) {
            return;
        }

		if (!$this->config->admin_enableEditing || !$this->editingMode) {
			return;
		}
        $this->page->addModules('POPUPS');
		require_once SYSTEM_PATH.'content-editor.class.php';
        require_once SYSTEM_PATH.'page-source.class.php';

        $this->config->editingMode = $this->editingMode;

        $ed = new ContentEditor($this);
		$ed->injectEditor($this->pagePath);
	} // injectEditor



	private function injectPageSwitcher()
	{
        if ($this->config->feature_pageSwitcher) {
            if (!$this->config->isLegacyBrowser) {
                $this->page->addJsFiles("HAMMERJS");
                if ($this->config->feature_touchDeviceSupport) {
                    $this->page->addJqFiles(['HAMMERJQ', 'TOUCH_DETECTOR', 'PAGE_SWITCHER', 'JQUERY']);
                } else {
                    $this->page->addJqFiles(['HAMMERJQ', 'PAGE_SWITCHER', 'JQUERY']);
                }
            }
        }
	} // injectPageSwitcher



    private function injectAdminCss()
    {
        if ($this->auth->checkGroupMembership('admins') ||
            $this->auth->checkGroupMembership('editors') ||
            $this->auth->checkGroupMembership('fileadmins')) {
                $this->page->addModules('USER_ADMIN');
        }
    } // injectAdminCss



	private function setTransvars0()
	{
        $requestScheme  = ((isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'].'://' : 'HTTP://';
        $requestUri     = (isset($_SERVER['REQUEST_URI'])) ? rawurldecode($_SERVER['REQUEST_URI']) : '';
        $appRoot        = fixPath(commonSubstr( dir_name($_SERVER['SCRIPT_NAME']), dir_name($requestUri), '/'));
        $appRootUrl     = $requestScheme.$_SERVER['HTTP_HOST'] . $appRoot;
        $this->trans->addVariable('appRootUrl', $appRootUrl);
        $this->trans->addVariable('lzy-password-min-length', $this->config->admin_minPasswordLength);
        $this->trans->addVariable('php-version', phpversion() );
    } // setTransvars0



	private function setTransvars1()
	{
        $loginMenu = $login = $userName = $groups = '';
        if (!$this->auth->getKnownUsers()) {    // case when no users defined yet:
            $login = <<<EOT
    <span class="lzy-tooltip-arrow lzy-tooltip" title='{{ lzy-no-users-defined-warning }}'>
        <span class='lzy-icon lzy-icon-error'></span>
    </span>

EOT;
            $this->page->addModules('TOOLTIPSTER');
            $this->page->addJq('$(\'.lzy-tooltip\').tooltipster({contentAsHTML: true});');
        } else {
	        if ($this->auth->isLoggedIn()) {
                $accForm = new UserLoginBase($this);
                $rec = $this->auth->getLoggedInUser(true);
                $login = $accForm->renderLoginLink($rec);
                $loginMenu = $accForm->renderLoginMenu($rec);
                $groups = @$rec['groups'];
            } else {
	            // login icon when not logged in:
	            $login = <<<EOT
<div class='lzy-login-link'><a href='{$GLOBALS['lizzy']['pageUrl']}?login' class='lzy-login-link'>{{ lzy-login-icon }}<span class='lzy-invisible'>{{ lzy-login-button-label }}</span></a></div>

EOT;
            }
        }
        if ($GLOBALS['lizzy']['user']) {
            $userName = $GLOBALS['lizzy']['user'];
            // Note: 'lzy-logged-in-as' defined in sys_vars.yaml

        } elseif ($this->localHost && $this->config->admin_autoAdminOnLocalhost) {
            $userName = 'autoadmin';
            // Note: 'lzy-logged-in-as' defined in sys_vars.yaml

        } else {
            $userName = '{{ lzy-anon }}';
            // override 'lzy-logged-in-as' from sys_vars.yaml:
            $this->trans->addVariable('lzy-logged-in-as-user', $this->trans->getVariable('lzy-not-logged-in'), false);
        }

        $this->trans->addVariable('lzy-login-menu', $loginMenu, false);
        $this->trans->addVariable('lzy-login-button', $login, false);
        $this->trans->addVariable('user', $userName, false);
        $this->trans->addVariable('groups', $groups, false);

        $configBtn = '';
        if ($this->auth->isAdmin()) {
            $url = $GLOBALS['lizzy']['pageUrl'];
            $configBtn = "<a class='lzy-config-button' href='$url?config'>{{ lzy-config-button }}</a>";
        }
        $this->trans->addVariable('lzy-config--open-button', $configBtn, false);

        if ($this->config->admin_enableFileManager && $this->auth->checkGroupMembership('fileadmins')) {
            $this->trans->addVariable('lzy-fileadmin-button', "<button class='lzy-fileadmin-button' title='{{ lzy-fileadmin-button-tooltip }}'><span class='lzy-icon lzy-icon-docs'></span>{{^ lzy-fileadmin-button-text }}</button>", false);
            $uploader = $this->injectUploader($this->pagePath);
            $this->page->addBodyEndInjections($uploader);
        } else {
            $this->trans->addVariable('lzy-fileadmin-button', "", false);
        }

        $this->trans->addVariable('pagePath', $this->reqPagePath);
        $this->trans->addVariable('pageUrl', $this->pageUrl);
        $this->trans->addVariable('appRoot', $this->pathToRoot);			// e.g. '../'
        $this->trans->addVariable('absAppRoot', $GLOBALS['lizzy']['appRoot']);
        $this->trans->addVariable('systemPath', $this->systemPath);		// -> file access path
        $this->trans->addVariable('lang', $this->config->lang);

        if ($this->config->debug_forceDebugMode) {
            setStaticVariable('debug', true);
        }

		if  (getUrlArgStatic('debug')) {
            if  (!$this->localHost) {   // log only on non-local host
                writeLog('starting debug mode');
            }
        	$this->page->addBodyClasses('debug');
		}


		if ($this->config->isLegacyBrowser) {
            $this->trans->addVariable('debug_class', ' legacy');
            $this->page->addBodyClasses('legacy');
        }

		if ($this->config->site_multiLanguageSupport) {
            $supportedLanguages = explode(',', str_replace(' ', '', $this->config->site_supportedLanguages ));
            $out = '';
            foreach ($supportedLanguages as $lang) {
                if (preg_match('/\w+\d/', $lang)) {
                    continue;
                }
                if ($lang === $this->config->lang) {
                    $out .= "<span class='lzy-lang-elem lzy-active-lang $lang'>{{ lzy-lang-select $lang }}</span>";
                } else {
                    $out .= "<span class='lzy-lang-elem $lang'><a href='?lang=$lang'>{{ lzy-lang-select $lang }}</a></span>";
                }
            }
            $out = "<div class='lzy-lang-selection'>$out</div>";
            $this->trans->addVariable('lzy-lang-selection', $out);
        } else {
            $this->trans->addVariable('lzy-lang-selection', '');
        }
        $this->trans->addVariable('lzy-lang-current', $this->trans->translateVariable("lzy-lang-{$this->config->lang}"));

        $this->trans->addVariable('lzy-version', getGitTag());

		if ($this->config->feature_pageSwitcher) {
            $this->definePageSwitchLinks();
        }
    } // setTransvars1



	private function setTransvars2()
	{
        $page = &$this->page;
		if (isset($page->title)) {                                  // page_title
			$this->trans->addVariable('page_title', $page->title, false);
		} else {
			$title = $this->trans->getVariable('page_title');
			$pageName = $this->siteStructure->currPageRec['name'];
			if ($this->siteStructure->currPageRec['folder'] === '') {   // Homepage: just show site title
                $title = $this->trans->getVariable('site_title');
            } else {
                $title = preg_replace('/\$page_name/', $pageName, $title);
            }
			$this->trans->addVariable('page_title', $title, false);
			$this->trans->addVariable('page_name', $pageName, false);
		}

		if ($this->siteStructure) {                                 // page_name_class
            $page->pageName = $pageName = translateToIdentifier($this->siteStructure->currPageRec['name']);
            $pagePathClass = rtrim(str_replace('/', '--', $this->pagePath), '--');
            $this->trans->addVariable('page_name_class', 'page_'.$pageName);        // just for compatibility
            $this->trans->addVariable('page_path_class', 'path_'.$pagePathClass);   // just for compatibility
            $this->page->addBodyClasses("page_$pageName path_$pagePathClass lzy-large-screen");
            if ($this->config->isPrivileged) {
                $this->page->addBodyClasses("lzy-privileged");
            }
            if ($this->auth->isLoggedIn()) {
                $this->page->addBodyClasses("lzy-loggedin");
            }
            if ($this->auth->isAdmin()) {
                $this->page->addBodyClasses("lzy-admin");
            }
            if ($this->auth->checkGroupMembership('editors')) {
                $this->page->addBodyClasses("lzy-editor");
            }
            if ($this->auth->checkGroupMembership('fileadmins')) {
                $this->page->addBodyClasses("lzy-fileadmin");
            }
		}
        setStaticVariable('pageName', $pageName);
    }// setTransvars2



    private function injectUploader($filePath)
    {
        //ToDo: injectUploader creates Hash -> avoid?
        require_once SYSTEM_PATH.'file_upload.class.php';

        $rec = [
            'uploadPath' => PAGES_PATH.$filePath,
            'pagePath' => $GLOBALS['lizzy']['pageFolder'],
            'pathToPage' => $GLOBALS['lizzy']['pathToPage'],
            'appRootUrl' => $GLOBALS['lizzy']['absAppRootUrl'],
            'user'      => $_SESSION['lizzy']['user'],
        ];
        $tick = new Ticketing();
        $ticket = $tick->createTicket($rec, 25);

        $uploader = new FileUpload($this, $ticket);
        $uploaderStr = $uploader->render($filePath);
        return $uploaderStr;
    }



	private function warnOnErrors()
    {
        global $lizzy;
        if ($this->config->admin_enableEditing && ($this->auth->checkGroupMembership('editors'))) {
            if ($lizzy['errorLogFile'] && file_exists($lizzy['errorLogFile'])) {
                $logFileName = $lizzy['errorLogFile'];
                $logMsg = file_get_contents($logFileName);
                $logArchiveFileName = str_replace('.txt', '', $logFileName)."_archive.txt";
                file_put_contents($logArchiveFileName, $logMsg, FILE_APPEND);
                unlink($logFileName);
                $logMsg = shieldMD($logMsg);
                $this->page->addMessage("Attention: Errors occured, see error-log file!\n$logMsg");
            }
        }
    } // warnOnErrors



	private function runUserRenderingStartCode()
	{
        $codeFile = $this->config->admin_serviceTasks['onPageRenderingStart'];
        if (!$codeFile) {
            return;
        }

        $codeFile = ltrim(base_name($codeFile, true), '-');
        $codeFile = fileExt($codeFile, true);
        $codeFile = SERVICE_CODE_PATH . "$codeFile.php";
        if ($codeFile && file_exists($codeFile)) {
            require_once $codeFile;
            if (function_exists('renderingStartOperation')) {
                renderingStartOperation( $this );
            }
        }
	} // runUserRenderingStartCode
	


    private function runUserFinalCode( &$html )
    {
        $codeFile = $this->config->admin_serviceTasks['onPageRendered'];
        if (!$codeFile) {
            return;
        }

        $codeFile = ltrim(base_name($codeFile, true), '-');
        $codeFile = fileExt($codeFile, true);
        $codeFile = SERVICE_CODE_PATH . "$codeFile.php";
        if ($codeFile && file_exists($codeFile)) {
            require_once $codeFile;
            if (function_exists('finalRenderingOperation')) {
                $html = finalRenderingOperation( $html, $this );
            }
        }
    } // runUserFinalCode



	private function getTemplate()
	{
		if ($template = $this->page->get('template')) {
		} elseif (isset($this->siteStructure->currPageRec['template'])) {
			$template = $this->siteStructure->currPageRec['template'];
		} else {
			$template = $this->config->site_pageTemplateFile;
		}
        $template = base_name($template, false). '.html';
		$tmplStr = getFile($this->config->configPath.$template);
		if ($tmplStr === false) {
			$this->page->addPageSubstitution("Error: template file not found: '$template'");
			return '';
		}
		return $tmplStr;
	} //getTemplate
	


    private function loadHtmlFile($folder, $file)
	{
		$page = &$this->page;
		if (strpos($file, '~/') === 0) {
			$file = substr($file, 2);
		} else {
			$file = $folder.$file;
		}
		$file = PAGES_PATH.$file;
		if (file_exists($file)) {
			$html = file_get_contents($file);
			$page->addContent( $this->page->extractHtmlBody($html), true);
		} else {
			$page->addOverride("Requested file not found: '$file'");
		}
		return $page;
	} // loadHtmlFile



    private function loadFile()
	{
        global $lizzy;
		$page = &$this->page;

		if (!$this->siteStructure->currPageRec) {
			$currRec['folder'] = '';
			$currRec['name'] = 'New Page';
		} else {
			$currRec = &$this->siteStructure->currPageRec;
		}
		if (isset($currRec['showthis']) && $currRec['showthis']) {
			$folder = fixPath($currRec['showthis']);
		} else {
			$folder = $currRec['folder'];
		}
		if (isset($currRec['file'])) {
			return $this->loadHtmlFile($folder, $currRec['file']);
		}

        $folder = PAGES_PATH.resolvePath($folder);
		$this->handleMissingFolder($folder);

		$mdFiles = getDir($folder.'*.{md,html,txt}');

		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir(PAGES_PATH.$folder.'*.{md,html,txt}');
		}
		
        $handleEditions = false;
        if (getUrlArg('ed', true) && $this->auth->checkGroupMembership('editors')) {
            require_once SYSTEM_PATH.'page-source.class.php';
            $handleEditions = true;
        }

        $md = new LizzyMarkdown( $this );
		$md->html5 = true;
		$langPatt = '.'.$this->config->lang.'.';

		foreach($mdFiles as $inx => $f) {
			$newPage = new Page($this);
			if ($this->config->site_multiLanguageSupport) {
				if (preg_match('/\.\w\w\./', $f) && (strpos($f, $langPatt) === false)) {
					continue;
				}
			}

			// exclude file-names starting with '-':
            if (basename($f)[0] === '-') {
                continue;
            }

            $lizzy['lastLoadedFile'] = $f;
			$ext = fileExt($f);

            if ($handleEditions) {
                $mdStr = PageSource::getFileOfRequestedEdition($f);
            } else {
                $mdStr = getFile($f, true);
            }

			$mdStr = $newPage->extractFrontmatter($mdStr);

            // frontmatter option 'visibility' -> reveal only to logged in users:
            $visibility = $newPage->get('frontmatter.visibility', true);
            if ($visibility !== null) {
                if ((!$visibility) || !$this->auth->checkPrivilege($visibility)) {
                    continue;
                }
            }
            
            // frontmatter options 'showFrom' and 'showTill':
            $showFrom = $newPage->get('frontmatter.showFrom', true);
            if (is_string($showFrom)) {
                $showFrom = strtotime($showFrom);
            }
            if ($showFrom && ($showFrom > time())) {
                continue;
            }
            $showTill = $newPage->get('frontmatter.showTill', true);
            if (is_string($showTill)) {
                $showTill = strtotime($showTill);
                if ($showTill && ($showTill < time())) {
                    continue;
                }
            } elseif ($showTill && (($showTill + 86400) < time())) {
                continue;
            }


            if ($ext === 'md') {             // it's an MD file, convert it

                $eop = strpos($mdStr, '__EOP__');           // check for 'end of page' marker, if found exclude all following (also following mdFiles)
                if (($eop !== false) && ($mdStr[$eop-1] !== '\\')) {
                    $mdStr = preg_replace('/__EOP__.*/sm', '', $mdStr);
                    $eop = true;
                }

                $md->compile($mdStr, $newPage);
            } elseif ($ext === 'html') {   // it's a HTML file
                $newPage->addContent("{{ include( '~/$f' ) }}\n");

            } elseif ($mdStr && $this->config->feature_renderTxtFiles) {   // it's a TXT file
                $newPage->addContent("<div class='lzy-pre'>$mdStr\n</div>\n");
            } else {
                continue;
            }
			
			$id = translateToIdentifier(base_name($f, false));
			$id = $cls = preg_replace('/^\d{1,3}[_\s]*/', '', $id); // remove leading sorting number

			$dataFilename = '';
			$editingClass = '';
			if ($this->editingMode) {
				$dataFilename = " data-lzy-filename='$f'";
                $editingClass = 'lzy-src-wrapper ';
			}
			if ($wrapperClass = $newPage->get('frontmatter.wrapperClass')) {
				$cls .= ' '.$wrapperClass;
			}
			$cls = trim($cls);
			$str = $newPage->get('content');

			if (!$wrapperTag = $newPage->get('frontmatter.wrapperTag')) {
                $wrapperTag = $this->config->custom_wrapperTag;
            }

			// extract <aside> and append it after </section>
            $aside = '';
            if (preg_match('|^ (.*) (<aside .* aside>) (.*) $|xms', $str, $m)) {
                if (preg_match('|^ (<!-- .*? -->\s*) (.*) |xms', $m[3], $mm)) {
                    $m[2] .= $mm[1];
                    $m[3] = $mm[2];
                }
                $str = $m[1].$m[3];
                $aside = $m[2];
            }

            if ($inx === 0) {
                $inx = '';
            } else {
                $inx = '-' . ($inx+1);
            }
			$wrapperId = "{$wrapperTag}_$id$inx";
			$wrapperCls = "{$wrapperTag}_$cls";
			$str = "\n\t\t    <$wrapperTag id='$wrapperId' class='lzy-section $editingClass$wrapperCls'$dataFilename>\n$str\t\t    </$wrapperTag><!-- /lzy-src-wrapper -->\n";
			if ($aside) {
                $str .= "\t$aside\n";
            }
			$newPage->addContent($str, true);

            $this->compileLocalCss($newPage, $wrapperId, $wrapperCls);

            $this->page->merge($newPage);

			if ($eop) {
			    break;
            }
		} // md-files

		$html = $page->get('content');
		if ((isset($this->siteStructure->currPageRec['backTickVariables'])) &&
			($this->siteStructure->currPageRec['backTickVariables'] === 'no')) {
			$html = str_replace('`', '&#96;', $html);
			$html = $this->page->extractHtmlBody($html);
		}
		$page->addContent($html, true);
        return $page;
	} // loadFile



    private function compileLocalCss($newPage, $id, $class)
    {
        $scssStr = $newPage->get('scss');
        $cssStr = $newPage->get('css');
        if ($this->config->feature_frontmatterCssLocalToSection) {  // option: generally prefix local CSS with section class
            $scssStr .= $cssStr;
            $cssStr = '';
            if ($scssStr) {
                $scssStr = ".$class { $scssStr }";
            }
        }

        // if tree notation for CSS is enabled, compile first:
        if ($cssStr && $this->config->feature_enableScssTreeNotation) {
            $this->treeParser = new Tree();
            $cssStr = $this->treeParser->toScss($cssStr);
        }

        // if SCSS found, compile it:
        if ($scssStr) {
            $cssStr .= $this->scss->compileStr($scssStr); // handles optional tree notation inside
        }

        $class = preg_replace('/\s+/', '.', $class);
        $cssStr = str_replace(['#this', '.this'], ["#$id", ".$class"], $cssStr); // '#this', '.this' are short-hands for section class/id
        $newPage->addCss($cssStr, true);
    } // compileLocalCss



    private function handleMissingFolder($folder)
	{
        if (file_exists($folder)) {
            return;
        }
        $mdFile = $folder . basename(substr($folder, 0, -1)) . '.md';
        mkdir($folder, MKDIR_MASK, true);
        $name = $this->siteStructure->currPageRec['name'];
        file_put_contents($mdFile, "---\n// Frontmatter:\ncss: |\n---\n\n# $name\n");
    } // handleMissingFolder



    private function prepareImages($html)
	{
        $resizer = new ImageResizer($this->config->feature_ImgDefaultMaxDim);
        $resizer->provideImages($html);
    } // prepareImages



    private function disableCaching()
    {
        $this->config->site_enableCaching = false;
        $GLOBALS['lizzy']['cachingActive'] = false;
    } // disableCaching



	private function handleUrlArgs()
	{
        if ($arg = getNotificationMsg()) {
            $arg = $this->trans->translateVariable($arg, true);
            $this->page->addMessage( $arg );
        }


        if ($nc = getStaticVariable('nc')) {		// nc
            if ($nc) {
                $this->disableCaching();
            }
        }

        if (getUrlArg('hash')) {		// nc
            $hash = createHash();
            $html = <<<EOT
    <div class='lzy-create-hash-wrapper'>
        <h2>New Hash Value</h2>
        <p>$hash</p>
    </div>
EOT;
            $this->page->addOverlay($html, true, false, true);
        }

        if (($this->config->localHost) && getUrlArg('purge')) {     // empty recycleBins and caches
            purgeAll( $this );
        }

        if (isset($_REQUEST) && $_REQUEST && $this->auth->isLoggedIn()) {
            $uadm = new UserAdminBase( $this );
            $uadm->handleRequests();
        }

        //====================== the following is restricted to editors and admins:
        $userAdminInitialized = file_exists(CONFIG_PATH.$this->config->admin_usersFile);
        $editingPermitted = $this->auth->checkGroupMembership('editors');
        if ($editingPermitted || !$userAdminInitialized) {
            if (isset($_GET['convert'])) {                                  // convert between data formats
                $this->renderDataConverter();
            }

            if (isset($_GET['pw'])) {                                       // convert (pw to hash)
                $this->renderPasswordConverter();
            }

            $editingMode = getUrlArgStatic('edit', false, 'editingMode');// edit
            if ($editingMode) {
                $this->editingMode = true;
                $this->config->feature_pageSwitcher = false;
                $this->disableCaching();
                setStaticVariable('nc', true);
            }

            if (getUrlArg('purge-all')) {                        // empty recycleBins and caches
                $srv = new ServiceTasks( $this );
                $srv->purgeAll( $this );
            }

            if (getUrlArg('lang', true) === 'none') {                  // force language
                $this->config->debug_showVariablesUnreplaced = true;
                unset($_GET['lang']);
            }

        } else {                    // no privileged permission: reset modes:
            if (getUrlArg('edit')) {
                $this->disableCaching();
                $this->page->addMessage('{{ need to login to edit }}');
            }
            setStaticVariable('editingMode', false);
            $this->editingMode = false;
		}

	} // handleUrlArgs



    private function renderPasswordConverter()
    {
        $html = <<<EOT
<h1>{{ Convert Password }}</h1>
<form class='lzy-password-converter'>
    <div>
        <label for='fld_password'>{{ Password }}</label>
        <input type='text' id='fld_password' name='password' value='' placeholder='{{ Password }}' />
        <button id='convert' class='lzy-form-form-button lzy-button'>{{ Convert }}</button>
    </div>
</form>
<p>{{ Hashed Password }}:</p>
<div id="lzy-hashed-password"></div>
<div id="lzy-password-converter-help" style="display: none;">&rarr; {{ Copy-paste the selected line }}</div>

EOT;

        $jq = <<<'EOT'
    setTimeout(function() { 
        $('#fld_password').focus();
    }, 200);
    $('#convert').click(function(e) {
        e.preventDefault();
        calcHash();
    });
    function calcHash() {
        var bcrypt = dcodeIO.bcrypt;
        var salt = bcrypt.genSaltSync(10);
        var pw = $('#fld_password').val();
        var hashed = bcrypt.hashSync(pw, salt);
        $('#lzy-hashed-password').text( 'password: ' + hashed ).selText();
        $('#lzy-password-converter-help').show();
    }
EOT;

        $css = <<<EOT
    #lzy-hashed-password {
        line-height: 2.5em;
        border: 1px solid gray;
        height: 2.5em;
        line-height: 2.5em;
        padding-left: 0.5em;
        width: 46em;
    }
    .lzy-password-converter button {
        height: 1.8em;
        padding: 0 1em;
    }
    .lzy-password-converter label {
        position: absolute;
        left: -1000vw;
    }
    .lzy-password-converter input {
        height: 1.4em;
        padding-left: 0.5em;
        margin-right: 0.5em;
        width: 20em;
    }
    #lzy-password-converter-help {
        margin-top: 2em;
        font-weight: bold;
    }

EOT;

        $this->page->addCss( $css );
        $this->page->addJq( $jq );
        $this->page->addModules( '~sys/third-party/bcrypt/bcrypt.min.js' );
        $this->page->addOverlay( ['text' => $html, 'closable' => 'reload'] );
        $this->page->setOverlayMdCompile( false );

    } // renderPasswordConverter



    private function renderDataConverter()
    {
        $response = '';
        if (@$this->convertDataResponse) {
            $response = "\t\t<div class='lzy-convert-data-response'>$this->convertDataResponse</div>\n";
        }
        $html = <<<EOT

<h1>{{ Convert Between Data Formats }}</h1>

	<div class='lzy-form-wrapper lzy-form-colored'>
	  <form id='lizzy-form1' class='lizzy-form1 lzy-form lzy-encapsulated' method='post' enctype="multipart/form-data">
		<input type='hidden' name='_lzy-form-cmd' value='convert-data' class='lzy-form-cmd' />
		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-1 lzy-form-field-type-text'>
                <span class='lzy-label-wrapper'>
                    <label for='fld_input_file_1'>Input File</label>
                </span>
			<input type='file' id='fld_input_file_1' name='Input_File' accept='.yaml,.json,.csv' />
		</div>
		<div class='lzy-form-field-wrapper lzy-form-field-wrapper-2 lzy-form-field-type-dropdown'>
                <span class='lzy-label-wrapper'>
                    <label for='fld_output_format_1'>Output Format</label>
                </span>
			<select id='fld_output_format_1' name='Output_Format'>
				<option value='yaml'>yaml</option>
				<option value='json'>json</option>
				<option value='csv'>csv</option>
			</select>
		</div>
		<div class='lzy-form-field-type-buttons'>
		<input type='reset' id='btn_lizzy-form1_cancel' value='Cancel'  class='lzy-form-button lzy-form-button-cancel' />
		<input type='submit' id='btn_lizzy-form1_submit' value='Convert'  class='lzy-form-button lzy-form-button-submit' />
		</div>
	  </form>
$response	</div><!-- /lzy-form-wrapper -->



EOT;

        $this->page->addOverlay( ['text' => $html, 'closable' => 'reload'] );
        $this->page->setOverlayMdCompile( false );
    } // renderDataConverter



	private function handleUrlArgs2()
	{
        if (getUrlArg('reset')) {			            // reset (cache)
            $srv = new ServiceTasks( $this );
            $srv->resetLizzy(); // reloads, never returns
        }

        // user wants to login in and is not already logged in:
		if (getUrlArg('login')) {                                               // login
		    if (getStaticVariable('user')) {    // already logged in
                $this->userRec = false;
                setStaticVariable('user',false);
            }
		}

		// printing support:
        if (getUrlArg('print-preview')) {              // activate Print-Preview

            $this->config->site_ContentSecurityPolicy = false; // need to disable CSP because of paged.polyfill.js

            $url = './?print';
            unset($_GET['print-preview']);
            foreach ($_GET as $k => $v) {   // make sure all other url-args are preserved:
                $url .= "&$k=$v";
            }
            $pagedPolyfillScript = PAGED_POLYFILL_SCRIPT;
            $jq = <<<EOT
	setTimeout(function() {
	    console.log('now running paged.polyfill.js'); 
	    $.getScript( '$pagedPolyfillScript' );
	}, 1000);
	setTimeout(function() {
	    console.log('now adding buttons'); 
        $('body').append( "<div class='lzy-print-btns'><a href='$url' class='lzy-button' >{{ lzy-print-now }}</a><a href='./' onclick='window.close();' class='lzy-button' >{{ lzy-close }}</a></div>" ).addClass('lzy-print-preview');
	}, 1200);

EOT;
            $this->page->addJq($jq);
        }
        if (getUrlArg('print')) {              // activate Print-supprt and start printing dialog
            $pagedPolyfillScript = PAGED_POLYFILL_SCRIPT;

            $jq = <<<EOT
	setTimeout(function() {
	    console.log('now running paged.polyfill.js'); 
	    $.getScript( '$pagedPolyfillScript' );
	}, 1000);
    setTimeout(function() {
        window.print();
    }, 2000);

EOT;

            $this->page->addJq($jq);
        }

        //=== beyond this point only localhost or logged in as editor/admin group
        if (!$_GET) {
            return;
        }
        $requestedCmd = false;
        $cmds = ',help,unused,reset-unused,remove-unused,log,info,list,mobile,touch,notouch,auto,config,localhost,';
        foreach ($_GET as $cmd => $value) {
            if (stripos($cmds, ",$cmd,") !== false) {
                $requestedCmd = $cmd;
                break;
            }
        }
        if (!$requestedCmd) {
            return;
        }

        $permisson = $this->auth->checkGroupMembership('editors');
        $warning = false;
        if (!$permisson && $this->config->determineIsLocalhost()) {
            $permisson = true;
            $warning = "<strong>Warning</strong>:<br>URL-command '$requestedCmd' granted on Localhost.<br>Admin privileges would be required on remote host.";
        }
        if (!$permisson) {
            $this->trans->addVariable('lzy-toggle-edit-mode', "");
            $this->page->addMessage("{{ lzy-insufficient-privilege }} '?$requestedCmd'");
            return;
        }
        if ($warning) {
            $this->page->addMessage( $warning );
        }

        if ($filename = getUrlArg('reorg-css', true)) {         // reorg-css
            $this->reorganizeCss($filename);
        }

        if (getUrlArg('gitstat')) {                                    // git-status
            $this->renderGitStatus();
        }

        if (getUrlArg('unused')) {							        // unused
            $str = $this->trans->renderUnusedVariables();
            $str = "<h1>Unused Variables</h1>\n$str";
            $this->page->addOverlay($str);
        }

        if (getUrlArg('reset-unused')) {                           // restart monitoring of unused variables
            if ($this->config->debug_monitorUnusedVariables && $this->auth->isAdmin()) {
                $this->trans->reset($GLOBALS['localeFiles']);
            }
        }

        if (getUrlArg('remove-unused')) {							// remove-unused
            $str = $this->trans->removeUnusedVariables();
            $str = "<h1>Removed Variables</h1>\n$str";
            $this->page->addOverlay($str);
        }


        if (getUrlArg('notranslate')) {							// render untranslated variables
            $this->trans->notranslate = true;
        }

        // TODO:
        //        if ($n = getUrlArg('printall', true)) {			// printall pages
        //            exit( $this->printall($n) );
        //        }


        if (getUrlArg('log')) {    // log
            $str = '';
            if (file_exists(LOG_FILE)) {
                $str .= "<h1>".basename(LOG_FILE).":</h1>\n";
                $log = file_get_contents(LOG_FILE);
                $log .= str_replace('{', '&#123;', $log);
                $str .= "<pre>$log</pre>\n";
            }
            if (file_exists(ERROR_LOG)) {
                $str .= "<h1>".basename(ERROR_LOG).":</h1>\n";
                $log = file_get_contents(ERROR_LOG);
                $log .= str_replace('{', '&#123;', $log);
                $str .= "<pre>$log</pre>\n";
            }
            if (file_exists(LOGIN_LOG_FILENAME)) {
                $str .= "<h1>".basename(LOGIN_LOG_FILENAME).":</h1>\n";
                $log = file_get_contents(LOGIN_LOG_FILENAME);
                $log .= str_replace('{', '&#123;', $log);
                $str .= "<pre>$log</pre>\n";
            }
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']);
        }



        if (getUrlArg('info')) {    // info
            $str = $this->page->renderDebugInfo();
            $str = "<h1>Lizzy System Info</h1>\n".$str;
            $str .= "<div style='margin-bottom:5em;'></div>\n";
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']);
		}



        if (getUrlArg('list')) {    // list
            $str = $this->trans->renderAllTranslationObjects();
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']);
		}



        if (getUrlArg('help')) {                              // help
            $this->renderUrlArgHelp();
        }



        if (getStaticVariable('editingMode')) {
            $this->trans->addVariable('lzy-toggle-edit-mode', "<a class='lzy-toggle-edit-mode' href='?edit=false'>{{ lzy-turn-edit-mode-off }}</a>");
        } else {
            $this->trans->addVariable('lzy-toggle-edit-mode', "<a class='lzy-toggle-edit-mode' href='?edit'>{{ lzy-turn-edit-mode-on }}</a>");
        }


		
		if (getUrlArgStatic('mobile')) {			                    // mobile
			$this->trans->addVariable('debug_class', ' mobile');
            $this->page->addBodyClasses('mobile');
            $this->isMobile = true;
		}
		if (getUrlArgStatic('touch')) {			                        // touch
			$this->trans->addVariable('debug_class', ' touch');
            $this->page->addBodyClasses('touch');
		} elseif (getUrlArgStatic('notouch')) {		                    // notouch
            $this->trans->addVariable('debug_class', ' notouch');
            $this->page->addBodyClasses('notouch');
        }

        if (getUrlArg('config')) {                              // config
            if (!$this->auth->checkGroupMembership('admins')) {
                $this->page->addMessage("Insufficient privilege for option '?config'");
                return;
            }

            $str = $this->config->renderConfigOverlay();
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']); // close shall reload page to remove url-arg
        }

        if (getUrlArg('ticket')) {
            $uadm = new UserAdminBase( $this );
            $uadm->handleCreateTicketRequest();
        }

        if (getUrlArg('accesscode')) {                                    // access-code
            $user = getUrlArg('accesscode', true);
            $uadm = new UserAdminBase( $this );
            $uadm->createAccessCodeForUser($user);
        }

    } // handleUrlArgs2



    private function reorganizeCss($filename)
    {
        require_once SYSTEM_PATH.'reorg_css.class.php';
        $reo = new ReorgCss($filename);
        $reo->execute();
    }



    private function determineLanguage()
    {
        $lang = getStaticVariable('lang');

        // check sitemap for language option:
        if (isset($this->siteStructure->currPageRec['lang'])) {
            $lang = $this->siteStructure->currPageRec['lang'];
        }

        // next check '?lang' override from URL:
        $lang1 = getUrlArgStatic('lang', true);
        if ($lang1) {
            $p = strpos($this->config->site_supportedLanguages, $lang1);
            if ($p !== false) {
                $lang = substr($this->config->site_supportedLanguages, $p);
                $lang = preg_replace('/,.*/', '', $lang);
            }
        }

        // if still not defined, fall back on default language setting:
        if (!$lang) {
            $lang = $this->config->site_defaultLanguage;
        }

        // make sure that selected language is among supported ones, fall back on default if not:
        if (strpos(",{$this->config->site_supportedLanguages},", ",$lang,") === false) {
            $lang = $this->config->site_defaultLanguage;
        }

        // subLang: permits to define variants of a language, e.g. polite/informal forms (e.g. German "du" vs. "Sie"):
        $subLang = $lang;
        if (preg_match('/(\w+)\d/', $lang, $m)) {
            $lang = $m[1];
        }

        $supportedLanguages = '';
        foreach (explodeTrim(',', $this->config->site_supportedLanguages) as $l) {
            $supportedLanguages .= preg_replace('/\d/', '', $l) . ',';
        }
        $this->config->site_supportedLanguages = rtrim($supportedLanguages, ',');

        // publish resulting lang to rest of system:
        $this->setLanguage( $lang, $subLang );
        return $lang;
    } // determineLanguage



    public function setLanguage( $lang, $subLang = false )
    {
        // publish resulting lang to rest of system:
        if (!$subLang) {
            $subLang = $lang;
        }
        $lang = preg_replace('/\d+/','', $lang);
        $this->config->lang = $lang;
        $GLOBALS['lizzy']['lang'] = $lang;
        setStaticVariable('lang', $lang);
        setStaticVariable('subLang', $subLang);
    } // setLanguage



    public function sendMail($to, $subject, $message, $from = false, $options = null, $exitOnError = true)
    {
        if (!$from) {
            $from = $this->trans->getVariable('webmaster_email');
        }

        if ($this->localHost) {
            writeLog("sendMail to:[$to], from:[$from], subject:[$subject],\nmessage:[$message]");

            $str = <<<EOT
        <div class='lzy-local-mail-sent-overlay'>
            <p><strong>Message sent to "$to" by e-mail when not on localhost:</strong></p>
            <pre class='lzy-debug-mail'>
                <div>Subject: $subject</div>
                <div>$message</div>
            </pre>
        </div> <!-- /lzy-local-mail-sent-overlay -->

EOT;
            $this->page->addOverlay(['text' => $str, 'mdCompile' => false ]);
            return true;

        } else {
            return sendMail($to, $from, $subject, $message, $options, $exitOnError); // in auxiliary.php
        }
    } // sendMail



	private function printall($maxN = true)
	{
        die('Not implemented yet');
	} // printall



    private function getBrowser()
    {
        global $lizzy;
        $ua = new UaDetector( $this->config->debug_collectBrowserSignatures );

        $lizzy['userAgent'] = $ua->get();
        $this->isLegacyBrowser = $ua->isLegacyBrowser();
        $_SESSION['lizzy']['userAgent'] = $lizzy['userAgent'];
        $lizzy['HTTP_USER_AGENT'] = @$_SERVER['HTTP_USER_AGENT'];

        $this->isMobile = $ua->isMobile();

        return  $lizzy['userAgent'];
    } // browserDetection



    private function checkInstallation()
    {
        if (version_compare(PHP_VERSION, '7.1.0') < 0) {
            die("Lizzy requires PHP version 7.1 or higher to run.");
        }

        // If Lizzy is downloaded but installation not finalized:
        if (!file_exists(DEFAULT_CONFIG_FILE)) {
            ob_end_flush();
            echo "<pre>";
            echo shell_exec('/bin/sh _lizzy/_install/install.sh');
            echo "</pre>";
            exit;
        }

        preparePath(DATA_PATH);
        preparePath(CACHE_PATH);
        preparePath(DEFAULT_TICKETS_PATH);
        preparePath(LOGS_PATH);
    } // checkInstallation



    public function getEditingMode()
    {
        return $this->editingMode;
    } // getEditingMode



    private function setDataPath()
    {
        $onairDataPath = $this->config->site_dataPath;
        $devDataPath = $this->config->site_devDataPath;
        if (!$devDataPath) {
            $GLOBALS['lizzy']['dataPath'] = $onairDataPath;
            $_SESSION['lizzy']['dataPath'] = $onairDataPath;
            $this->trans->addVariable('dataPath', $onairDataPath);
            return;

        } elseif ($devDataPath === true) {
            $devDataPath = 'data/';
        }

        $rootPath = dirname($_SERVER['SCRIPT_NAME']);
        $pat = '#'. $this->config->site_devDataPathPattern .'#i';
        $isDev = preg_match($pat, $rootPath);      // dev folder?
        $isDev &= !getUrlArg('forceOnair');
        $testMode = getUrlArgStatic('debug');

        if ($testMode || $isDev) {
            if ($testMode) {
                $this->page->addDebugMsg("\"&#126;data/\" points to \"$devDataPath\" for debugging.");
            }
            $this->config->site_dataPath = $devDataPath;
            $GLOBALS['lizzy']['dataPath'] = $devDataPath;
            $_SESSION['lizzy']['dataPath'] = $devDataPath;
            $this->trans->addVariable('dataPath', $devDataPath);
            return;

        } else {
            if ($this->config->localHost) {
                $this->page->addDebugMsg("\"&#126;data/\" points to productive path \"$onairDataPath\"!.");
            }
            $this->config->site_dataPath = $onairDataPath;
            $GLOBALS['lizzy']['dataPath'] = $onairDataPath;
            $_SESSION['lizzy']['dataPath'] = $onairDataPath;
            $this->trans->addVariable('dataPath', $onairDataPath);
        }
    } // setDataPath



    private function setLocale()
    {
        $lang = $this->config->lang;
        $locale = false;
        foreach (explodeTrim(',', $this->config->site_localeCodes) as $code) {
            if (strpos($code, $lang) === 0) {
                $locale = $code;
                break;
            }
        }
        if (!$locale)  {
            $locale = $lang . '_' . strtoupper($lang);
        }
        $this->config->currLocale = setlocale(LC_TIME, "$locale.utf-8");
    } // setLocale



    private function setTimezone()
    {
        // first try Session variable:
        $systemTimeZone = getStaticVariable('systemTimeZone');

        // if not defined, try to read it from config.yaml:
        if (!$systemTimeZone) {
            $config = file_get_contents($this->configFile);
            $config1 = zapFileEND($config);
            $config1 = removeHashTypeComments($config1);
            if (preg_match('|site_timeZone:\s*([\w/]*)|ms', $config1, $m)) {
                $systemTimeZone = $m[1];
            }
        }
        // if not defined yet, try to obtain it automatically:
        if (!$systemTimeZone) {
            $systemTimeZone = $this->getServerTimezone();
            $config = "\nsite_timeZone: $systemTimeZone     # autmatically set to timezone of webhost\n\n$config";
            file_put_contents($this->configFile, $config);
        }
        if (!$systemTimeZone) {
            die("Error: 'site_timeZone' entry missing in config/config.yaml");
        }

        // activate timezone:
        date_default_timezone_set($systemTimeZone);
        setStaticVariable('systemTimeZone', $systemTimeZone);
        return $systemTimeZone;
    } // setTimezone



    private function getServerTimezone()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ipapi.co/timezone");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    } // getServerTimezone



    private function renderLoginForm($asPopup = true, $presetUser = false)
    {
        if ($this->loginFormRendered) { // already done, don't repeat
            return '';
        }
        $this->loginFormRendered = true;

        $accForm = new UserLoginBase($this);
        $preOpenPanel = $presetUser? 2:1;
        $html = $accForm->render(true, '', $preOpenPanel);

        // inject preset user name:
        if ($presetUser) {    // preset username if known
            $jq = <<<EOT
$('#fld_lzy-login-username-1').val('$presetUser')
setTimeout(function() { 
    $('#fld_lzy-login-password-1').focus(); 
}, 500);
EOT;
            $this->page->addJq( $jq );
        }

        $this->page->addModules('AUXILIARY');
        if ($asPopup) {
            $this->page->addModules('POPUPS');
            $this->page->addJq("lzyPopup({ 
    contentFrom: '#lzy-login-form',
    closeOnBgClick: false, 
    closeButton: true,
    wrapperClass: 'lzy-login',
    draggable: true,
    header: '{{ lzy-login-header }}',
});", 'prepend');

            $html = <<<EOT

    <div id='lzy-login-form' style="display: none;">
        <div class='lzy-login-form-wrapper'>
$html
        </div><!-- /.lzy-login-form -->
    </div><!-- /#lzy-login-form -->

EOT;
            $this->page->addBodyEndInjections( $html );
            return '';

        } else {
            $html = <<<EOT
    <div class='lzy-required-login-wrapper'>
        <div class="lzy-comment">{{ lzy-insufficient-privilege-for-page }}</div>
<!--        <h2>{{ lzy-login-with-choice }}</h2>-->
$html
    </div>
EOT;
            return $html;
        }
    } // renderLoginForm



    private function handleConfigFeatures()
    {
        if ($this->config->feature_touchDeviceSupport) {
            $this->page->addJqFiles("TOUCH_DETECTOR,AUXILIARY,MAC_KEYS");
        } else {
            $this->page->addJqFiles("AUXILIARY,MAC_KEYS");
        }


        if ($this->config->feature_enableIFrameResizing) {
            $this->page->addModules('IFRAME_RESIZER');
            $jq = <<<EOT
    if ( window.location !== window.parent.location ) { // page is being iframe-embedded:
        $('body').addClass('lzy-iframe-resizer-active');
    }
EOT;
            $this->page->addJq($jq);

            if (getUrlArg('iframe')) {
                $pgUrl = $GLOBALS['lizzy']['pageUrl'];
                $host = $GLOBALS['lizzy']['host'];
                $jsUrl = $host . $GLOBALS['lizzy']['appRoot'];
                $html = <<<EOT

<div id="iframe-info">
    <h1>iFrame Embedding</h1>
    <p>Use the following code to embed this page:</p>
    <div style="border: 1px solid #ddd; padding: 15px; overflow: auto">
    <pre>
<code>&lt;iframe id="thisIframe" src="$pgUrl" style="width: 1px; min-width: 100%; border: none;">&lt;/iframe>
&lt;script src='{$jsUrl}_lizzy/third-party/iframe-resizer/iframeResizer.min.js'>&lt;/script>
&lt;script>
  iFrameResize({checkOrigin: '$host'}, '#thisIframe' );
&lt;/script></code></pre>
    </div>
</div>

EOT;
                $this->page->addOverlay($html, false, false, 'reload');
            }
        } elseif (getUrlArg('iframe')) {    // iframe
            $msg = "#iframe\nWarning:\n\nto use a ``?iframe`` request  \nyou need to enable 'feature_enableIFrameResizing'";
            $this->page->addOverlay($msg, false, true, 'reload');
        }
    } // handleConfigFeatures



    private function definePageSwitchLinks()
    {
        $nextLabel = $this->trans->getVariable('lzy-next-page-link-label');
        if (strpos($nextLabel, '%nextLabel%') !== false) {
            $nextLabelChar = $this->config->isLegacyBrowser ? '&gt;' : '〉';
            $nextLabel = str_replace('%nextLabel%', $nextLabelChar, $nextLabel);
        }
        $prevLabel = $this->trans->getVariable('lzy-prev-page-link-label');
        if (strpos($prevLabel, '%prevLabel%') !== false) {
            $prevLabelChar = $this->config->isLegacyBrowser ? '&lt;' : '〈';
            $prevLabel = str_replace('%prevLabel%', $prevLabelChar, $prevLabel);
        }
        $nextTitle = $this->trans->getVariable('lzy-next-page-link-title');
        if ($nextTitle) {
            $nextTitle = " title='$nextTitle'";
        }
        $prevTitle = $this->trans->getVariable('lzy-prev-page-link-title');
        if ($prevTitle) {
            $prevTitle = " title='$prevTitle'";
        }
        $prevLink = '';
        if ($this->siteStructure->prevPage !== false) {
            $prevLink = "\n\t\t<div class='lzy-prev-page-link'><a href='~/{$this->siteStructure->prevPage}'$prevTitle>$prevLabel</a></div>";
        }
        $nextLink = '';
        if ($this->siteStructure->nextPage !== false) {
            $nextLink = "\n\t\t<div class='lzy-next-page-link'><a href='~/{$this->siteStructure->nextPage}'$nextTitle>$nextLabel</a></div>";
        }

        $str = <<<EOT
    <div class='lzy-page-switcher-links'>$prevLink$nextLink
    </div>

EOT;
        $this->trans->addVariable('lzy-page-switcher-links', $str);
    } // definePageSwitchLinks



    private function renderUrlArgHelp()
    {
        $overlay = <<<EOT
<h1>Lizzy Help</h1>
<pre class="pre">
Available URL-commands:

<a href='?help'>?help</a>		    	this message
<a href='?config'>?config</a>		    	list configuration-items in the config-file
<a href='?debug'>?debug</a>		    	activates DevMode and adds 'debug' class to page on non-local host *)
<a href='?fup'>?fup</a>		    	forces the browser to reload all js and css resources (fup = force update)
<a href='?localhost=false'>?localhost=false</a>	For testing: simulates running on remote host
<a href='?gitstat'>?gitstat</a>			displays the Lizzy-s GIT-status
<a href='?pw'>?pw</a>	            	convert password to hash
<a href='?hash'>?hash</a>		    	create a hash value e.g. for accessCodes
<a href='?accesscode'>?accesscode=user</a> 	create an accessCode forgiven user
<a href='?ticket'>?ticket</a>		    	create a user-access-ticket
<a href='?convert'>?convert</a>	    	convert between table-data formats
<a href='?edit'>?edit</a>		    	start editing mode *)
<a href='?iframe'>?iframe</a>		    	show code for embedding as iframe
<a href='?info'>?info</a>		    	list debug-info
<a href='?lang=xy'>?lang=</a>	        	switch to given language (e.g. '?lang=en')  *)
<a href='?list'>?list</a>		    	list <samp>transvars</samp> and <samp>macros()</samp>
<a href='?notranslate'>?notranslate</a>    	show untranslated variables
<a href='?log'>?log</a>		    	displays log files in overlay
<a href='?login'>?login</a>		    	login
<a href='?logout'>?logout</a>		    	logout
<a href='?mobile'>?mobile</a>,<a href='?touch'>?touch</a>,<a href='?notouch'>?notouch</a>	emulate modes  *)
<a href='?nc'>?nc</a>		        	supress caching (?nc=false to enable caching again)  *)
<a href='?print'>?print</a>		    	starts printing mode and launches the printing dialog
<a href='?print-preview'>?print-preview</a>  	presents the page in print-view mode    
<a href='?timer'>?timer</a>		    	switch timer on or off  *)

<a href='?reset'>?reset</a>		    	resets all state-defining information: caches, tickets, session-vars.
<a href='?purge-all'>?purge-all</a>			purges all files generated by Lizzy, so they will be recreated from scratch

*) these options are persistent, they keep their value for further page requests. 
Unset individually as ?xy=false or globally as ?reset

</pre>

EOT;
        //<a href='?debug'>?debug</a>		    adds 'debug' class to page on non-local host *)
        //<a href='?reorg-css='>?reorg-css={file(s)}</a>take CSS file(s) and convert to SCSS (e.g. "?reorg-css=tmp/*.css")
        // TODO: printall -> add above
        $this->page->addOverlay(['text' => $overlay, 'closable' => 'reload']);
    }



    private function renderGitStatus()
    {
        $status = '';
        if (file_exists('.git')) {
            $status = "Main Project:\n======================\n";
            $status .= shell_exec('git status');
            $status .= "\n\n\n";
        }
        $status .= "Lizzy Project:\n======================\n";
        $status .= shell_exec('cd _lizzy; git status');

        if ($this->config->custom_relatedGitProjects) {
            $gitProjects = explodeTrim(',', $this->config->custom_relatedGitProjects);
            foreach ($gitProjects as $project) {
                if (file_exists($project)) {
                    $name = str_replace('../', '', $project);
                    $status .= "\n\n\n";
                    $status .= "$name Project:\n======================\n";

                    if (file_exists("$project.git")) {
                        $status .= shell_exec("cd $project; git status");
                    } else {
                        $status .= "No git project found\n";
                    }
                }
            }
        }

        print("<pre>$status</pre>");
        exit;
    } // renderGitStatus



    private function initializeSiteInfrastructure()
    {
        global $lizzy;
        $this->siteStructure = new SiteStructure($this, $this->reqPagePath);

        $this->pagePath = $this->siteStructure->getPagePath();
        $this->pathToPage = PAGES_PATH . $this->pagePath;   //  includes pages/
        $pageFilePath = PAGES_PATH . $this->siteStructure->getPageFolder();      // excludes pages/, may differ from path if page redirected

        $lizzy['pageFolder'] = $pageFilePath;      // excludes pages/, may differ from path if page redirected
        $lizzy['pageFilePath'] = $pageFilePath;      // excludes pages/, may differ from path if page redirected
        $lizzy['pagePath'] = $this->pagePath;        // excludes pages/, takes not showThis into account
        $lizzy['pathToPage'] = $this->pathToPage;
        $_SESSION['lizzy']['pageFolder'] = $lizzy['pageFolder'];     // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pagePath'] = $lizzy['pagePath']; // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pathToPage'] = $this->pathToPage;


        $this->pageRelativePath = $this->pathToRoot . $this->pagePath;

        $this->trans->loadStandardVariables($this->siteStructure);
        $this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ lzy-next-page-label }}</a>");
        $this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ lzy-prev-page-label }}</a>");
        $this->trans->addVariable('next_page_href', $this->siteStructure->nextPage);
        $this->trans->addVariable('prev_page_href', $this->siteStructure->prevPage);

        if (isset($this->siteStructure->currPageRec['lang'])) {
            $this->setLanguage( $this->siteStructure->currPageRec['lang'] );
        }
    } // initializeSiteInfrastructure



    public function setCspHeader( $cspStr )
    {
        $this->cspHeader = $cspStr;
    } // setCspHeader



    private function storeToCache($html)
    {
        if (@$GLOBALS['lizzy']['toCache']) {
            $toCache = $GLOBALS['lizzy']['toCache'];
            file_put_contents(SYSTEM_CACHE_PATH.'_cachedParams.json', json_encode($toCache));
        }

        if (!$this->config->site_enableCaching || !$GLOBALS['lizzy']['cachingActive']) {
            return;
        }

        // skip writing to cache as long as there any URL-args:
        if ($_REQUEST) {
            return;
        }
        if ($_REQUEST) {
            return;
        }

        $requestedPage = $this->getCacheFilename( false );
        preparePath($requestedPage);

        // inject body-class 'lzy-cached' to signal page being cached:
        if (preg_match('/^( .* <body.*?class=["\'] .+? ) (["\'] .* )$/xms', $html, $m)) {
            $html = $m[1] . ' lzy-cached' . $m[2];
        }

        // check CSP, inject code at top of cached file:
        if ($this->cspHeader) {
            $html = "<!-- %CSP $this->cspHeader -->\n$html";
        }
        file_put_contents($requestedPage, $html);
    } // storeToCache



    public function unCachePage()
    {
        $requestedPage = $this->getCacheFilename( false );
        if (file_exists($requestedPage)) {
            unlink( $requestedPage );
        }
    } // unCachePage



    private function checkAndRenderCachePage()
    {
        // cached page can be rendered when:
        // - no user is logged in
        // - no GET or POST arguments present
        // - no HASH is present in URL
        // - Session-variable 'nc' is not set
        // - Debug-mode is not active
        // - no daily housekeeping is to be executed

        if (isset($_GET['reset'])) {  // reset page cache
            purgePageCache();
        }

        if (file_exists(SYSTEM_CACHE_PATH.'_cachedParams.json')) {
            $json = file_get_contents(SYSTEM_CACHE_PATH.'_cachedParams.json');
            $params = json_decode( $json, true );
            $GLOBALS['lizzy'] = array_merge($GLOBALS['lizzy'], $params);
        }

        if (isset($_GET['nc'])) {  // nc = no-caching
            if ($_GET['nc'] === 'false') {
                unset($_SESSION['lizzy']['nc']);
                unset($_GET['nc']);
            } else {
                $_SESSION['lizzy']['nc'] = true;
            }
            if ($_SESSION['lizzy']['nc']) {
                return;
            }
        } elseif (@$_SESSION['lizzy']['nc'] || @$_SESSION['lizzy']['debug']) {
            return;
        }
        if ($this->dailyPageCacheReset()) {
            return;
        }
        if (@$_SESSION['lizzy']['refreshPageCache']) {
            $this->unCachePage();
            $_SESSION['lizzy']['refreshPageCache'] = false;
            return;
        }
        if (@$_SESSION['lizzy']['user'] || $_GET || $_POST) {
            return;
        }
        if (preg_match('/[A-Z][A-Z0-9]{4,}/', $_SERVER['REQUEST_URI'])) {
            return;
        }

        // now try to get cached page:
        $requestedPage = $this->getCacheFilename();
        if ( $requestedPage ) { // cached page found
            $html = file_get_contents($requestedPage);

            // check for variables shielded from cache and translate them:
            if (strpos($html, '{|{|') !== false) {
                $this->loadRequired();
                $this->getConfigValues(); // from config/config.yaml
                $trans = new Transvar($this);
                $trans->readTransvarsFromFiles([ SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml' ]);
                $html = $trans->translate($html, true);
            }

            // check for CSP injection:
            if (strpos($html, '<!-- %CSP') === 0) {
                if (preg_match('/^ <!--\s %CSP\s (.*?) -->/x', $html, $m)) {
                    $header = $m[1];
                    header( $header );
                    $html = substr($html, strlen( $m[0] ) + 1);
                }

                // activate Strict-Transport-Security:
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }
            header("Server-Timing: cache, total;dur=" . readTimer());

            exit ($html);
        }
    } // checkAndRenderCachePage



    private function getCacheFilename( $verify = true )
    {
        $scriptPath = dir_name($_SERVER['SCRIPT_NAME']);
        // ignore filename part of request:
        $requestUri = (isset($_SERVER['REQUEST_URI'])) ? rawurldecode($_SERVER['REQUEST_URI']) : '';
        if (fileExt($requestUri)) {
            $requestUri = dir_name($requestUri);
        }
        $appRoot = fixPath(commonSubstr( $scriptPath, dir_name($requestUri), '/'));
        $ru = preg_replace('/\?.*/', '', $requestUri); // remove opt. '?arg'
        $requestedPageHttpPath = dir_name(substr($ru, strlen($appRoot)));
        if ($requestedPageHttpPath === '.') {
            $requestedPageHttpPath = '';
        }

        $lang = isset($_SESSION['lizzy']['lang']) ? $_SESSION['lizzy']['lang'] : '';
        if ($lang) {
            $lang = ".$lang";
        }

        $requestedPage = PAGE_CACHE_PATH . fixPath($requestedPageHttpPath) . "index$lang.html";

        if ($verify) {
            if (file_exists($requestedPage)) {
                if (!$requestedPageHttpPath && file_exists(HOMEPAGE_PATH)) {
                    $requestedPageHttpPath = HOMEPAGE_PATH;
                }
                $requestedPageHttpPath = './' . PAGES_PATH . $requestedPageHttpPath;
                $t0 = 0;
                if (!file_exists($requestedPageHttpPath)) {
                    die("Error: folder not found: '$requestedPageHttpPath'");
                }
                $it = new \RecursiveDirectoryIterator( $requestedPageHttpPath );
                foreach (new RecursiveIteratorIterator($it) as $fileRec) {
                    // ignore files starting with . or # or _
                    $f = $fileRec->getFilename();
                    if (preg_match('|^[._#]|', $f)) {
                        continue;
                    }
                    if (pathinfo($f, PATHINFO_EXTENSION) !== 'md') {
                        continue;
                    }
                    $t0 = max($t0, $fileRec->getMTime());
                }

                $t1 = filemtime($requestedPage);
                if ($t0 > $t1) {
                    $requestedPage = false;
                }
            } else {
                $requestedPage = false;
            }
        }

        return $requestedPage;
    } // getCacheFilename



    private function dailyPageCacheReset()
    {
        if (file_exists(HOUSEKEEPING_FILE)) {
            $intervall = intval(file_get_contents(HOUSEKEEPING_FILE));
            if ($intervall) {
                $fileTime = intval(filemtime(HOUSEKEEPING_FILE) / $intervall);
                $today = intval(time() / $intervall);
                if ($fileTime === $today) {
                    return false;   // ok to use cache
                }
            }
        }
        purgePageCache();
        return true;
    } // dailyPageCacheReset



    private function extractTicketHashes( $requestedPath )
    {
        // extract ticketHash from URL:
        if (preg_match('|(.*)/([A-Z][A-Z0-9]{4,})(\.?)/?$|', $requestedPath, $m)) {
            $requestedPath = $m[1] . '/';
            $this->ticketHash['url'] = $m[2];
            $this->keepAccessCode = ($m[3] !== ''); // -> true if trailing dot is set
        }
        // extract ticketHash from GET or POST arguments:
        if ($_REQUEST) {
            foreach ($_REQUEST as $key => $value) {
                if (($key[0] === '_' || ($key === 'accessCode'))) {
                    continue;
                }
                if (is_string($value) && preg_match('/^ ([A-Z][A-Z0-9]{4,}) $/x', $value, $m)) {
                    $this->ticketHash[$key] = $m[1];
                }
            }
        }
        return $requestedPath;
    } // extractTicketHashes

} // class WebPage



function purgePageCache()
{
    rrmdir(PAGE_CACHE_PATH);
} // purgePageCache

