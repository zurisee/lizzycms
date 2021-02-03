<?php
/*
 *	Lizzy - main class
 *
 *	Main Class *
*/

define('CONFIG_PATH',           'config/');
define('USER_CODE_PATH',        'code/');
define('PATH_TO_APP_ROOT',      '');
define('SYSTEM_PATH',           basename(dirname(__FILE__)).'/'); // _lizzy/
define('DEFAULT_CONFIG_FILE',   CONFIG_PATH.'config.yaml');
define('DEV_MODE_CONFIG_FILE',  CONFIG_PATH.'dev-mode-config.yaml');

define('PAGES_PATH',            'pages/');
define('DATA_PATH',            'data/');
define('CACHE_PATH',            '.cache/');
define('MODULES_CACHE_PATH',    '.cache/files/');
define('PAGE_CACHE_PATH',       CACHE_PATH.'pages/');
define('LOGS_PATH',             '.#logs/');
define('MACROS_PATH',           SYSTEM_PATH.'macros/');
define('EXTENSIONS_PATH',       SYSTEM_PATH.'extensions/');
define('USER_INIT_CODE_FILE',   USER_CODE_PATH.'init-code.php');
define('USER_FINAL_CODE_FILE',  USER_CODE_PATH.'final-code.php');
define('USER_VAR_DEF_FILE',     USER_CODE_PATH.'var-definitions.php');
define('ICS_PATH',              'ics/'); // where .ics files are located

define('DEFAULT_TICKETS_PATH', '.#tickets/');
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
define('HOUSEKEEPING_FILE',     CACHE_PATH.'_housekeeping.txt');
define('MIN_SITEMAP_INDENTATION', 4);
define('REC_KEY_ID', 	        '_key');
define('TIMESTAMP_KEY_ID', 	    '_timestamp');
define('PASSWORD_PLACEHOLDER', 	'●●●●');

define('MKDIR_MASK',            0700); // permissions for file access by Lizzy
define('MKDIR_MASK_WEBACCESS',  0755); // permissions for files cache

$files = ['config/user_variables.yaml', '_lizzy/config/*', '_lizzy/macros/transvars/*'];


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DomCrawler\Crawler;


require_once SYSTEM_PATH.'auxiliary.php';

$globalParams = array(
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
    private $lzyDb = null;  // -> SQL DB for caching DataStorage data-files
	private $currPage = false;
	private $configPath = CONFIG_PATH;
	private $systemPath = SYSTEM_PATH;
	private $autoAttrDef = [];
	public  $pathToRoot;
	public  $pagePath;
	private $reqPagePath;
	public  $siteStructure;
	public  $trans;
	public  $page;
	private $editingMode = false;
	private $timer = false;
	private $debugLogBuffer = '';




    public function __construct()
    {
        session_start();
        $user = @$_SESSION['lizzy']['user']? $_SESSION['lizzy']['user']: 'anon';
        $this->debugLogBuffer = "REQUEST_URI: {$_SERVER["REQUEST_URI"]}  FROM: [$user]\n";
        if ($_REQUEST) {
            $this->debugLogBuffer .= "REQUEST: ".var_r($_REQUEST, 'REQUEST', true)."\n";
        }

        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            die("Error: file not found: ".$configFile);
        }

        $this->setDefaultTimezone(); // i.e. timezone of host

        // Check cache and render if available:
        $this->checkAndRenderCachePage();    // exits immediately, never returns

        $this->loadRequired();

        $this->checkInstallation();

        $srv = new ServiceTasks($this);
        $srv->runServiceTasks();

		$this->init();
		$this->setupErrorHandling();

        if ($this->config->site_sitemapFile || $this->config->feature_sitemapFromFolders) {
            $this->initializeSiteInfrastructure();

        } else {
            $this->initializeAsOnePager();
        }
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
    require_once SYSTEM_PATH.'user-account-form.class.php';
    require_once SYSTEM_PATH.'ticketing.class.php';
    require_once SYSTEM_PATH.'service-tasks.class.php';
} // loadRequired




    private function init()
    {
        $this->sessionId = session_id();

        $this->getConfigValues(); // from config/config.yaml

        // get info about browser
        list($ua, $this->isLegacyBrowser) = $this->getBrowser();
        $globalParams['userAgent'] = $ua;
        $_SESSION['lizzy']['userAgent'] = $ua;
        $globalParams['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

        if ($this->config->debug_debugLogging && $this->debugLogBuffer) {
            writeLog( $this->debugLogBuffer . "  ($ua)" );
        }

        $this->setLocale();

        $this->localCall = $this->config->localCall;

        register_shutdown_function('handleFatalPhpError');

        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));

        $GLOBALS['globalParams']['isAdmin'] = false;
        $GLOBALS['globalParams']['activityLoggingEnabled'] = $this->config->admin_activityLogging;
        $GLOBALS['globalParams']['errorLoggingEnabled'] = $this->config->debug_errorLogging;

        $this->trans = new Transvar($this);
        $this->setTransvars0();
        $this->page = new Page($this);

        $this->setDataPath();

        $this->trans->readTransvarsFromFiles([ SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml' ]);

        $this->auth = new Authentication($this);

        $this->analyzeHttpRequest();

        $this->auth->authenticate();

        $this->handleAdminRequests(); // form-responses e.g. change profile etc.

        $GLOBALS['globalParams']['auth-message'] = $this->auth->message;

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
        $GLOBALS['globalParams']['cachingActive'] = $this->config->site_enableCaching;
        $GLOBALS['globalParams']['site_title'] = $this->trans->translateVariable('site_title');
    } // init





    public function render()
    {
		if ($this->timer) {
			startTimer();
		}

		$this->selectLanguage();

		$accessGranted = $this->checkAdmissionToCurrentPage();   // override page with login-form if required

        $this->injectAdminCss();
        $this->setTransvars1();
        $this->addStandardModules();

        if ($accessGranted) {

            // enable caching of compiled MD pages:
            // Note: in most cases MD-caching will not become active since the page caching
            //  will kick in before we get to this point.
            //  ToDo: figure out whether MD-caching is still required at all
            if ($this->config->mdCachingActive && $this->page->readFromCache()) {
                $html = $this->page->render(true);
                $html = $this->resolveAllPaths($html);
                if ($this->timer) {
                    $timerMsg = 'Page rendering time: '.readTimer();
                    $html = $this->page->lateApplyMessage($html, $timerMsg);
                }
                $html .= "\n<!-- cached page -->";
                return $html;
            }

            $this->loadFile();        // get content file
        }

        $this->scss->compile();

        $this->injectPageSwitcher();

        $this->warnOnErrors();

        $this->setTransvars2();

        if ($accessGranted) {
            $this->runUserRenderingStartCode();
        }
        $this->loadTemplate();

        if ($accessGranted) {
            $this->injectEditor();

            $this->trans->loadUserComputedVariables();
        }

        $this->appendLoginForm($accessGranted);   // sleeping code for popup population
        $this->handleAdminRequests2();
        $this->handleUrlArgs2();

        $this->handleConfigFeatures();


        // now, compile the page from all its components:
        $html = $this->page->render();

        $this->prepareImages($html);

        $this->applyForcedBrowserCacheUpdate($html);

        $html = $this->resolveAllPaths($html);

        if ($this->timer) {
            $timerMsg = 'Page rendering time: '.readTimer();
            $html = $this->page->lateApplyMessage($html, $timerMsg);
		}

        $this->runUserFinalCode($html );   // optional custom code to operate on final HTML output

        $this->storeToCache( $html );

        // translate variables shielded from cache:
        if (strpos($html, '{|{|') !== false) {
            $html = $this->trans->translate($html, true);
        }

        return $html;
    } // render




    private function handleAdminRequests()
    {
        if ($un = getUrlArg('lzy-check-username', true)) {
            $exists = $this->auth->findUserRecKey( $un );
            if ($exists) {
                $msg = 'lzy-signup-username-not-available';
                $msg = $this->trans->translateVariable($msg, true);
            } else {
                $msg = 'ok';
            }
            exit( $msg );
        }

        if (isset($_REQUEST['lzy-user-admin'])) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this);
            $adm->handleAdminRequests( $_REQUEST['lzy-user-admin'] );
        }
    } // handleAdminRequests



    private function handleAdminRequests2()
    {
        if ($adminTask = getUrlArg('admin', true)) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $admTsk = new AdminTasks($this);
            $overridePage = $admTsk->handleAdminRequests2($adminTask);
            $this->page->merge($overridePage, 'override');
            $this->page->setOverrideMdCompile(false);
        }
    } // handleAdminRequests2




    private function resolveAllPaths( $html )
    {
        global $globalParams;
        $appRoot = $globalParams['appRootUrl'];
        $pagePath = $globalParams['pagePath'];

        if (!$this->config->admin_useRequestRewrite) {
            resolveHrefs($html);
        }

        // Handle resource accesses first: src='~page/...' -> local to page but need full path:
        $p = $appRoot.$this->pathToRoot;
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
            $appRoot.$globalParams['dataPath'],
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

        } elseif ($this->config->debug_forceBrowserCacheUpdate) {
            $forceUpdate = getVersionCode( true );

        } else {
            return;
        }
        if ($forceUpdate) {
            $html = preg_replace('/(\<link\s+href=(["])[^"]+)"/m', "$1$forceUpdate\"", $html);
            $html = preg_replace("/(\<link\s+href=(['])[^']+)'/m", "$1$forceUpdate'", $html);

            $html = preg_replace('/(\<script\s+src=(["])[^"]+)"/m', "$1$forceUpdate\"", $html);
            $html = preg_replace("/(\<script\s+src=(['])[^']+)'/m", "$1$forceUpdate'", $html);
        }
    } // applyForcedBrowserCacheUpdate





    private function setupErrorHandling()
    {
        global $globalParams;
        $globalParams['errorLogFile'] = '';
        if ($this->auth->checkGroupMembership('editors') || $this->localCall) {     // set displaying errors on screen:
            $old = ini_set('display_errors', '1');  // on
            error_reporting(E_ALL);

        } elseif (file_exists(HOUSEKEEPING_FILE)) {
            $old = ini_set('display_errors', '0');  // off
            error_reporting(0);
        }
        if ($old === false) {
            fatalError("Error setting up error handling... (no kidding)", 'File: '.__FILE__.' Line: '.__LINE__);
        }

    //        if ($this->config->debug_errorLogging && !file_exists(ERROR_LOG_ARCHIVE)) {
    //            $errorLogPath = dirname(ERROR_LOG_ARCHIVE).'/';
    //            $errorLogFile = ERROR_LOG_ARCHIVE;
    //
    //            // make error log folder:
    //            preparePath($errorLogPath);
    //            if (!is_writable($errorLogPath)) {
    //                die("Error: no write permission to create error log folder '$errorLogPath'");
    //            }
    //
    //            // make error archtive file and check
    //            touch($errorLogFile);
    //            if (!file_exists($errorLogFile) || !is_writable($errorLogPath)) {
    //                die("Error: unable to create error log file '$errorLogPath' - probably access rights are not ");
    //            }
    //
    //            // make error log file, check and delete immediately
    //            touch(ERROR_LOG);
    //            if (!file_exists(ERROR_LOG) || !is_writable(ERROR_LOG)) {
    //                die("Error: unable to create error log file '".ERROR_LOG."' - probably access rights are not ");
    //            }
    //            unlink(ERROR_LOG);
    //
    //            ini_set("log_errors", 1);
    //            ini_set("error_log", $errorLogFile);
    //            //error_log( "Error-logging started" );
    //
    //            $globalParams['errorLogFile'] = ERROR_LOG;
    //        }
    } // setupErrorHandling





    private function checkAdmissionToCurrentPage()
    {
        if ($reqGroups = $this->isRestrictedPage()) {     // handle case of restricted page
            if ($reqGroups === 'privileged') {
                $ok = $this->auth->isPrivileged();
            } elseif ($reqGroups === 'loggedin') {
                $ok = $this->auth->isLoggedIn();
            } else {
                $ok = $this->auth->checkGroupMembership( $reqGroups );
            }
            if (!$ok) {
                $this->renderLoginForm( false );
                return false;
            }
            setStaticVariable('isRestrictedPage', $this->auth->getLoggedInUser());
        } else {
            setStaticVariable('isRestrictedPage', false);
        }
        return true;
    } // checkAdmissionToCurrentPage





    private function appendLoginForm($accessGranted)
    {
        if ( !$this->auth->getKnownUsers() ) { // don't bother with login if there are no users
            return;
        }

        if (($user = getUrlArg('login', true)) !== null) {
            $this->renderLoginForm( true, $user );

        } elseif (!$accessGranted) {
            $loginForm = $this->renderLoginForm( false );
            $this->page->addContent($loginForm);
            $this->page->addBodyClasses('lzy-page-override');
            $jq = "initLzyPanel('.lzy-panels-widget', 1);";
            $this->page->addJq( $jq );
        }

        if ($this->auth->isLoggedIn()) {   // signal in body tag class whether user is logged in
            $this->page->addBodyClasses('lzy-user-logged-in');  // if user is logged in, there's no need for login form
            return;
        }
    } // appendLoginForm




    private function analyzeHttpRequest()
    {
        global $globalParams;

        $requestUri         = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        $requestedPath      = $requestUri;
        $urlArgs               = '';
        if (preg_match('/^ (.*?) [\?#&] (.*)/x', $requestUri, $m)) {
            $requestedPath = $m[1];
            $urlArgs = $m[2];
        }

        // extract access code, e.g. folder/ABCDEF/ (i.e. at least 4 letters/digits all uppercase)
        $accessCode         = '';
        if (preg_match('|(.*)/([A-Z][A-Z0-9]{4,})(\.?)/?$|', $requestedPath, $m)) {
            $requestedPath = $m[1].'/';
            $accessCode = $m[2];
            $this->keepAccessCode = ($m[3] !== '');
        }

        $requestScheme      = $_SERVER['REQUEST_SCHEME'];               // https
        $domainName         = $_SERVER['HTTP_HOST'];                    // domain.net
        $docRootUrl         = "$requestScheme://$domainName/";          // https://domain.net/
        $absAppRootPath     = dirname($_SERVER['SCRIPT_FILENAME']).'/'; // /home/httpdocs/approot/
        $appRoot            = dirname($_SERVER['SCRIPT_NAME']).'/';     // /approot/
        $absDocRootPath     = substr($absAppRootPath, 0, -strlen($appRoot)+1); // /home/httpdocs/
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
        $globalParams['host'] = $docRootUrl;
        $globalParams['requestedUrl'] = $requestUri;
        $globalParams['pageFolder'] = null;
        $globalParams['pagePath'] = null;
        $globalParams['pathToPage'] = null; // needs to be set after determining actually requested page
        $globalParams['pageUrl'] = $pageUrl;
        $globalParams['pagesFolder'] = PAGES_PATH;
        $globalParams['filepathToRoot'] = $pathToAppRoot;
        $globalParams['absAppRoot'] = $absAppRootPath;  // path from FS root to base folder of app, e.g. /Volumes/...
        $globalParams['absAppRootUrl'] = $globalParams["host"] . substr($appRoot, 1);  // path from FS root to base folder of app, e.g. /Volumes/...
        $globalParams['appRootUrl'] = $appRootUrl;  //
        $globalParams['appRoot'] = $appRoot;  // path from docRoot to base folder of app, e.g. 'on/'
        $globalParams['redirectedAppRootUrl'] = $redirectedAppRootUrl;  // the part that has been skipped by .htaccess

        $globalParams['filepathToDocroot'] = preg_replace('|[^/]+|', '..', $appRoot);;

        $globalParams['localCall'] = $this->localCall;
        $globalParams['isLocalhost'] = $this->localCall;
        $globalParams['pagePath'] = $pagePath;   // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed
        $globalParams['urlArgs'] = $urlArgs;     // all url-args received

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
        $ip = $_SERVER["HTTP_HOST"];
        if (stripos($ip, 'localhost') !== false) {  // case of localhost, not executed on host
            $ifconfig = shell_exec('ifconfig');
            $p = strpos($ifconfig, 'inet 192.168');
            $ip = substr($ifconfig, $p+5);
            if (preg_match('/([\d\.]+)/', $ip, $match)) {
                $ip = $match[1];
            }
        }
        $this->serverIP = $ip;

        // check whether to support legacy browsers -> load jQ version 1
        if ($this->config->feature_supportLegacyBrowsers) {
            $this->config->isLegacyBrowser = true;
            $globalParams['legacyBrowser'] = true;
            writeLog("Legacy-Browser Support activated.");

        } else {
            $overrideLegacy = getUrlArgStatic('legacy');
            if ($overrideLegacy === null) {
                $this->config->isLegacyBrowser = $this->isLegacyBrowser;
            } else {
                $this->config->isLegacyBrowser = $overrideLegacy;
            }
        }
        $globalParams['legacyBrowser'] = $this->config->isLegacyBrowser;

        if ($accessCode) {
            $this->auth->handleAccessCodeInUrl($accessCode);
        }

    } // analyzeHttpRequest






    private function getConfigValues()
    {
        global $globalParams;

        $this->config = new Defaults( $this );
        $this->config->pathToRoot = $this->pathToRoot;

        $globalParams['path_logPath'] = $this->config->path_logPath;

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





	private function isRestrictedPage()
	{
		if (isset($this->siteStructure->currPageRec['restricted!'])) {
			$lockProfile = $this->siteStructure->currPageRec['restricted!'];
			return $lockProfile;
		}
		return false;
	} // isRestrictedPage





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
		require_once SYSTEM_PATH.'editor.class.php';
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
                    $this->page->addJqFiles(["HAMMERJQ", "TOUCH_DETECTOR", "PAGE_SWITCHER", "JQUERY"]);
                } else {
                    $this->page->addJqFiles(["HAMMERJQ", "PAGE_SWITCHER", "JQUERY"]);
                }
            }
        }
	} // injectPageSwitcher





    private function injectAdminCss()
    {
        if ($this->auth->checkGroupMembership('admins') ||
            $this->auth->checkGroupMembership('editors') ||
            $this->auth->checkGroupMembership('fileadmins')) {
                $this->page->addCssFiles('~sys/css/_admin.css');
        }

    } // injectAdminCss




    private function addStandardModules()
    {
        $this->page->addModules('TOOLTIPSTER');
        $this->page->addJq('$(\'.tooltip\').tooltipster({contentAsHTML: true});');
    } // addStandardModules




	private function setTransvars0()
	{
        $requestScheme = ((isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'].'://' : 'HTTP://';
        $requestUri     = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        $appRoot        = fixPath(commonSubstr( dir_name($_SERVER['SCRIPT_NAME']), dir_name($requestUri), '/'));
        $appRootUrl = $requestScheme.$_SERVER['HTTP_HOST'] . $appRoot;
        $this->trans->addVariable('appRootUrl', $appRootUrl);
        $this->trans->addVariable('lzy-password-min-length', $this->config->admin_minPasswordLength);

    } // setTransvars0



	private function setTransvars1()
	{
        $loginMenu = $login = $userName = $groups = '';
        if (!$this->auth->getKnownUsers()) {    // case when no users defined yet:
            $login = <<<EOT
    <span class="lzy-tooltip-arrow tooltip" title='{{ lzy-no-users-defined-warning }}'>
        <span class='lzy-icon-error'></span>
    </span>

EOT;
        } else {
	        if ($this->auth->isLoggedIn()) {
                $userAcc = new UserAccountForm($this);
                $rec = $this->auth->getLoggedInUser(true);
                $login = $userAcc->renderLoginLink($rec);
                $loginMenu = $userAcc->renderLoginMenu($rec);
                $userName = @$rec['username']? $rec['username'] : '{{ lzy-anon }}';
                $groups = @$rec['groups'];
            } else {
	            // login icon when not logged in:
	            $login = <<<EOT
<div class='lzy-login-link'><a href='{$GLOBALS['globalParams']['pageUrl']}?login' class='lzy-login-link'>{{ lzy-login-icon }}</a></div>

EOT;

            }
        }

        $this->trans->addVariable('lzy-login-menu', $loginMenu);
        $this->trans->addVariable('lzy-login-button', $login);
        $this->trans->addVariable('user', $userName, false);
        $this->trans->addVariable('groups', $groups, false);

        $configBtn = '';
        if ($this->auth->isAdmin()) {
            $url = $GLOBALS["globalParams"]["pageUrl"];
            $configBtn = "<a class='lzy-config-button' href='$url?config'>{{ lzy-config-button }}</a>";
        }
        $this->trans->addVariable('lzy-config--open-button', $configBtn, false);

        if ($this->config->admin_enableFileManager && $this->auth->checkGroupMembership('fileadmins')) {
            $this->trans->addVariable('lzy-fileadmin-button', "<button class='lzy-fileadmin-button' title='{{ lzy-fileadmin-button-tooltip }}'><span class='lzy-icon-docs'></span>{{^ lzy-fileadmin-button-text }}</button>", false);
//ToDo: injectUploader creates Hash -> avoid?
            $uploader = $this->injectUploader($this->pagePath);
            $this->page->addBodyEndInjections($uploader);
        } else {
            $this->trans->addVariable('lzy-fileadmin-button', "", false);
        }

        $this->trans->addVariable('pageUrl', $this->pageUrl);
        $this->trans->addVariable('appRoot', $this->pathToRoot);			// e.g. '../'
        $this->trans->addVariable('systemPath', $this->systemPath);		// -> file access path
        $this->trans->addVariable('lang', $this->config->lang);


		if  (getUrlArgStatic('debug') || $this->config->debug_forceDebugMode) {
            if  (!$this->localCall) {   // log only on non-local host
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
        global $globalParams;
        $page = &$this->page;
		if (isset($page->title)) {                                  // page_title
			$this->trans->addVariable('page_title', $page->title, false);
		} else {
			$title = $this->trans->getVariable('page_title');
			$pageName = $this->siteStructure->currPageRec['name'];
			if ($this->siteStructure->currPageRec["folder"] === '') {   // Homepage: just show site title
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
        require_once SYSTEM_PATH.'file_upload.class.php';

        $rec = [
            'uploadPath' => PAGES_PATH.$filePath,
            'pagePath' => $GLOBALS['globalParams']['pageFolder'],
            'pathToPage' => $GLOBALS['globalParams']['pathToPage'],
            'appRootUrl' => $GLOBALS['globalParams']['absAppRootUrl'],
            'user'      => $_SESSION["lizzy"]["user"],
        ];
        $tick = new Ticketing();
        $ticket = $tick->createTicket($rec, 25);

        $uploader = new FileUpload($this, $ticket);
        $uploaderStr = $uploader->render($filePath);
        return $uploaderStr;
    }




	private function warnOnErrors()
    {
        global $globalParams;
        if ($this->config->admin_enableEditing && ($this->auth->checkGroupMembership('editors'))) {
            if ($globalParams['errorLogFile'] && file_exists($globalParams['errorLogFile'])) {
                $logFileName = $globalParams['errorLogFile'];
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
        $codeFile = USER_CODE_PATH . "-$codeFile.php";
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
        $codeFile = USER_CODE_PATH . "-$codeFile.php";
        if ($codeFile && file_exists($codeFile)) {
            require_once $codeFile;
            if (function_exists('finalRenderingOperation')) {
                $html = finalRenderingOperation( $html, $this );
            }
        }
    } // runUserFinalCode





	private function getTemplate()
	{
		if ($tpl = $this->page->get('template')) {
			$template = basename($tpl);
		} elseif (isset($this->siteStructure->currPageRec['template'])) {
			$template = $this->siteStructure->currPageRec['template'];
		} else {
			$template = $this->config->site_pageTemplateFile;
		}
		$tmplStr = getFile($this->config->configPath.$template);
		if ($tmplStr === false) {
			$this->page->addOverlay("Error: templage file not found: '$template'");
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
        global $globalParams;
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
            registerFileDateDependencies($currRec['file']);
			return $this->loadHtmlFile($folder, $currRec['file']);
		}

        $folder = PAGES_PATH.resolvePath($folder);
		$this->handleMissingFolder($folder);

		$mdFiles = getDir($folder.'*.{md,html,txt}');
        registerFileDateDependencies($mdFiles);

		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir(PAGES_PATH.$folder.'*.{md,html,txt}');
            registerFileDateDependencies($mdFiles);
		}
		
        $handleEditions = false;
        if (getUrlArg('ed', true) && $this->auth->checkGroupMembership('editors')) {
            require_once SYSTEM_PATH.'page-source.class.php';
            $handleEditions = true;
        }

        $md = new LizzyMarkdown($this->trans);
		$md->html5 = true;
		$langPatt = '.'.$this->config->lang.'.';

		foreach($mdFiles as $f) {
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

            $globalParams['lastLoadedFile'] = $f;
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

			$wrapperId = "{$wrapperTag}_$id";
			$wrapperCls = "{$wrapperTag}_$cls";
			$str = "\n\t\t    <$wrapperTag id='$wrapperId' class='$editingClass$wrapperCls'$dataFilename>\n$str\t\t    </$wrapperTag><!-- /lzy-src-wrapper -->\n";
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
        if ($scssStr) {
            $cssStr .= $this->scss->compileStr($scssStr);
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
        $GLOBALS['globalParams']['cachingActive'] = false;
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
        <h1>New Hash Value</h1>
        <p>$hash</p>
    </div>
EOT;
            $this->page->addOverride($html);
        }

        $this->timer = getUrlArgStatic('timer');				// timer

        if (($this->config->localCall) && getUrlArg('purge')) {     // empty recycleBins and caches
            purgeAll( $this );
        }

        //====================== the following is restricted to editors and admins:
        $userAdminInitialized = file_exists(CONFIG_PATH.$this->config->admin_usersFile);
        $editingPermitted = $this->auth->checkGroupMembership('editors');
        if ($editingPermitted || !$userAdminInitialized) {
            if (isset($_GET['convert'])) {                                  // convert (pw to hash)
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

            $url = './?print';
            unset($_GET['print-preview']);
            foreach ($_GET as $k => $v) {   // make sure all other url-args are preserved:
                $url .= "&$k=$v";
            }
            $jq = <<<EOT
	setTimeout(function() {
	    console.log('now running paged.polyfill.js'); 
	    $.getScript( "~sys/third-party/paged.polyfill/paged.polyfill.js"); 
	}, 1000);
	setTimeout(function() {
	    console.log('now adding buttons'); 
        $('body').append( "<div class='lzy-print-btns'><a href='$url' class='lzy-button' >{{ lzy-print-now }}</a><a href='./' onclick='window.close();' class='lzy-button' >{{ lzy-close }}</a></div>" ).addClass('lzy-print-preview');
	}, 1200);

EOT;
            $this->page->addJq($jq);
        }
        if (getUrlArg('print')) {              // activate Print-supprt and start printing dialog

            $jq = <<<EOT
	setTimeout(function() {
	    console.log('now running paged.polyfill.js'); 
	    $.getScript( "~sys/third-party/paged.polyfill/paged.polyfill.js");
	}, 1000);
    setTimeout(function() {
        window.print();
    }, 2000);

EOT;

            $this->page->addJq($jq);
        }

        //=== beyond this point only localhost or logged in as editor/admin group
        if (!$this->auth->checkGroupMembership('editors')) {
            $this->trans->addVariable('lzy-toggle-edit-mode', "");
            $cmds = ['help','unused','reset-unused','remove-unused','log','info','list','mobile','touch','notouch','auto','config'];
            foreach ($cmds as $cmd) {
                if (isset($_GET[$cmd])) {
                    $this->page->addMessage("Insufficient privilege for option '?$cmd'");
                    break;
                }
            }
            return;
        }

        if (getUrlArg('iframe')) {                                          // iframe
            if (!$this->config->feature_enableIFrameResizing) {
                $msg = "Warning: to use ?iframe request you need to enable 'feature_enableIFrameResizing'";
                $this->page->addMessage($msg);
            }
        }

        if (getUrlArg('access-link')) {                                    // access-link
            $user = getUrlArg('access-link', true);
            $this->createAccessLink($user);
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
                $this->trans->reset($GLOBALS['files']);
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


    } // handleUrlArgs2





    private function createAccessLink($user)
    {
        if (!$user) {
            $msg = "# Access Link\n\nPlease supply a user-name.\n\nE.g. ?access-code=user1";
        } else {
            $userRec = $this->auth->getUserRec($user);
            if (!$this->auth->getUserRec($user)) {
                die("Create Access Link: user unknown: '$user");
            }
            $tick = new Ticketing();
            $code = $tick->createTicket($userRec, 100);
            $msg = "# Access Link\n\n{$GLOBALS["globalParams"]["pageUrl"]}$code";
        }
        $this->page->addOverlay(['text' => $msg, 'closable' => 'reload', 'mdCompile' => true]);
    } // createAccessLink





    private function reorganizeCss($filename)
    {
        require_once SYSTEM_PATH.'reorg_css.class.php';
        $reo = new ReorgCss($filename);
        $reo->execute();
    }





    private function selectLanguage()
    {
        global $globalParams;
        // check sitemap for language option:
        if (isset($this->siteStructure->currPageRec['lang'])) {
            $lang = $this->siteStructure->currPageRec['lang'];

            if (($l = getUrlArg('lang', true)) !== null) { // override if explicitly supplied
                if ($l) {
                    $lang = $l;
                    setStaticVariable('lang', $lang);
                } else {
                    setStaticVariable('lang', null);
                }
            }

        // no preference in sitemap -> use previously activated lang or default, unless overriden by url-arg:
        } else {
            $lang = getUrlArgStatic('lang', true);
            if (!$lang) {   // no url-arg found
                if ($lang !== null) {   // special case: empty lang -> remove static value
                    setStaticVariable('lang', null);
                }
                $lang = $this->config->site_defaultLanguage;
            }
        }

        // check that selected language is among supported ones:
        if (strpos(",{$this->config->site_supportedLanguages},", ",$lang,") === false) {
            $lang = $this->config->site_defaultLanguage;
        }

        $this->config->lang = $lang;
        $globalParams['lang'] = $lang;
        $_SESSION['lizzy']['lang'] = $lang;
        return $lang;
    } // selectLanguage






    public function sendMail($to, $subject, $message, $from = false, $options = null, $exitOnError = true)
    {
        if (!$from) {
            $from = $this->trans->getVariable('webmaster_email');
        }

        if ($this->localCall) {
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
        $ua = new UaDetector( $this->config->debug_collectBrowserSignatures );
        return [$ua->get(), $ua->isLegacyBrowser()];
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





    public function postprocess($html)
    {
        $note = $this->trans->postprocess();
        if ($note) {
            $p = strpos($html, '</body>');
            $html = substr($html, 0, $p).createWarning($note).substr($html,$p);
        }
        return $html;
    } // postprocess





    public function getEditingMode()
    {
        return $this->editingMode;
    } // getEditingMode





    private function setDataPath()
    {
        $onairDataPath = $this->config->site_dataPath;
        $devDataPath = $this->config->site_devDataPath;
        if (!$devDataPath) {
            $GLOBALS["globalParams"]["dataPath"] = $onairDataPath;
            $_SESSION["lizzy"]["dataPath"] = $onairDataPath;
            $this->trans->addVariable('dataPath', $onairDataPath);
            return;

        } elseif ($devDataPath === true) {
            $devDataPath = 'data/';
        }

        $rootPath = dirname($_SERVER["SCRIPT_NAME"]);
        $pat = '#'. $this->config->site_devDataPathPattern .'#i';
        $isDev = preg_match($pat, $rootPath);      // dev folder?
        $isDev &= !getUrlArg('forceOnair');
        $testMode = getUrlArgStatic('debug');

        if ($testMode || $isDev) {
            if ($testMode) {
                $this->page->addDebugMsg("\"&#126;data/\" points to \"$devDataPath\" for debugging.");
            }
            $this->config->site_dataPath = $devDataPath;
            $GLOBALS["globalParams"]["dataPath"] = $devDataPath;
            $_SESSION["lizzy"]["dataPath"] = $devDataPath;
            $this->trans->addVariable('dataPath', $devDataPath);
            return;

        } else {
            if ($this->config->localCall) {
                $this->page->addDebugMsg("\"&#126;data/\" points to productive path \"$onairDataPath\"!.");
            }
            $this->config->site_dataPath = $onairDataPath;
            $GLOBALS["globalParams"]["dataPath"] = $onairDataPath;
            $_SESSION["lizzy"]["dataPath"] = $onairDataPath;
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

        $timeZone = ($this->config->site_timeZone === 'auto') ? $this->setDefaultTimezone() : $this->config->site_timeZone;
        setStaticVariable('systemTimeZone', $timeZone);
    } // setLocale




    private function setDefaultTimezone()
    {
        exec('date +%Z',$systemTimeZone, $res);
        if ($res || !isset($systemTimeZone[0])) {
            $systemTimeZone = 'UTC';
        } else {
            $systemTimeZone = $systemTimeZone[0];
        }
        if ($systemTimeZone === 'CEST') {    // workaround: CEST not supported
            $systemTimeZone = 'CET';
        }
        date_default_timezone_set($systemTimeZone);
        setStaticVariable('systemTimeZone', $systemTimeZone);
        return $systemTimeZone;
    } // setDefaultTimezone




    private function renderLoginForm($asPopup = true, $presetUser = false)
    {
        $accForm = new UserAccountForm($this);
        $html = $accForm->renderLoginForm($this->auth->message, false, true);
        $jq = '';
        $this->page->addModules('EVENT_UE,PANELS');
        if ($presetUser) {    // preset username if known
            $jq .= "$('.lzy-login-username').val('$presetUser');\nsetTimeout(function() { $('.lzy-login-email').val('$presetUser').focus(); },500);";
        }
        $jq .= "initLzyPanel('.lzy-panels-widget', 1);";
        $this->page->addJq( $jq );

        if ($asPopup) {
            $this->page->addModules('POPUPS');
            $this->page->addJq("lzyPopup({ 
                contentRef: '#lzy-login-form',
                closeOnBgClick: false, 
                closeButton: true, 
                wrapperClass: 'lzy-login',
                draggable: true,
                header: '{{ lzy-login-header }}',
            });");

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
        <h2>{{ lzy-login-with-choice }}</h2>
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

            if (isset($_GET['iframe'])) {
                $pgUrl = $GLOBALS["globalParams"]["pageUrl"];
                $host = $GLOBALS["globalParams"]["host"];
                $jsUrl = $host . $GLOBALS["globalParams"]["appRoot"];
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
                $this->page->addOverride($html, false, false);
            }
        }
    } // handleConfigFeatures




    private function definePageSwitchLinks()
    {
        $nextLabel = $this->trans->getVariable('lzy-next-page-link-label');
        if (strpos($nextLabel, '$nextLabel') !== false) {
            $nextLabelChar = $this->config->isLegacyBrowser ? '&gt;' : '&#9002;';
            $nextLabel = str_replace('$nextLabel', $nextLabelChar, $nextLabel);
        }
        $prevLabel = $this->trans->getVariable('lzy-prev-page-link-label');
        if (strpos($prevLabel, '$prevLabel') !== false) {
            $prevLabelChar = $this->config->isLegacyBrowser ? '&lt;' : '&#9001;';
            $prevLabel = str_replace('$prevLabel', $prevLabelChar, $prevLabel);
        }
        $nextTitle = $this->trans->getVariable('lzy-next-page-link-title');
        if ($nextTitle) {
            $nextTitle = " title='$nextTitle' aria-label='$nextTitle'";
        }
        $prevTitle = $this->trans->getVariable('lzy-prev-page-link-title');
        if ($prevTitle) {
            $prevTitle = " title='$prevTitle' aria-label='$prevTitle'";
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




    public function getLzyDb()
    {
        return $this->lzyDb;
    }




    private function renderUrlArgHelp()
    {
        $overlay = <<<EOT
<h1>Lizzy Help</h1>
<pre class="pre">
Available URL-commands:

<a href='?help'>?help</a>		    this message
<a href='?config'>?config</a>		    list configuration-items in the config-file
<a href='?convert'>?convert</a>	    convert password to hash
<a href='?debug'>?debug</a>		    adds 'debug' class to page on non-local host *)
<a href='?gitstat'>?gitstat</a>		    displays the Lizzy-s GIT-status
<a href='?hash'>?hash</a>		    create a hash value e.g. for accessCodes
<a href='?notranslate'>?notranslate</a>    show untranslated variables
<a href='?edit'>?edit</a>		    start editing mode *)
<a href='?iframe'>?iframe</a>		    show code for embedding as iframe
<a href='?info'>?info</a>		    list debug-info
<a href='?lang=xy'>?lang=</a>	        switch to given language (e.g. '?lang=en')  *)
<a href='?list'>?list</a>		    list <samp>transvars</samp> and <samp>macros()</samp>
<a href='?log'>?log</a>		    displays log files in overlay
<a href='?login'>?login</a>		    login
<a href='?logout'>?logout</a>		    logout
<a href='?mobile'>?mobile</a>,<a href='?touch'>?touch</a>,<a href='?notouch'>?notouch</a>	emulate modes  *)
<a href='?nc'>?nc</a>		        supress caching (?nc=false to enable caching again)  *)
<a href='?print'>?print</a>		    starts printing mode and launches the printing dialog
<a href='?print-preview'>?print-preview</a>  presents the page in print-view mode    
<a href='?timer'>?timer</a>		    switch timer on or off  *)

<a href='?reset'>?reset</a>		    resets all state-defining information: caches, tickets, session-vars.
<a href='?purge-all'>?purge-all</a>		purges all files generated by Lizzy, so they will be recreated from scratch

*) these options are persistent, they keep their value for further page requests. 
Unset individually as ?xy=false or globally as ?reset

</pre>

EOT;
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
    }



    private function initializeSiteInfrastructure()
    {
        global $globalParams;
        $this->siteStructure = new SiteStructure($this, $this->reqPagePath);
        $this->currPage = $this->reqPagePath = $this->siteStructure->currPage;

        $this->pagePath = $this->siteStructure->getPagePath();
        $this->pathToPage = PAGES_PATH . $this->pagePath;   //  includes pages/
        $pageFilePath = PAGES_PATH . $this->siteStructure->getPageFolder();      // excludes pages/, may differ from path if page redirected

        $globalParams['pageFolder'] = $pageFilePath;      // excludes pages/, may differ from path if page redirected
        $globalParams['pageFilePath'] = $pageFilePath;      // excludes pages/, may differ from path if page redirected
        $globalParams['pagePath'] = $this->pagePath;        // excludes pages/, takes not showThis into account
        $globalParams['pathToPage'] = $this->pathToPage;
        $_SESSION['lizzy']['pageFolder'] = $globalParams['pageFolder'];     // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pagePath'] = $globalParams['pagePath']; // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pathToPage'] = $this->pathToPage;


        $this->pageRelativePath = $this->pathToRoot . $this->pagePath;

        $this->trans->loadStandardVariables($this->siteStructure);
        $this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ lzy-next-page-label }}</a>");
        $this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ lzy-prev-page-label }}</a>");
        $this->trans->addVariable('next_page_href', $this->siteStructure->nextPage);
        $this->trans->addVariable('prev_page_href', $this->siteStructure->prevPage);
    } // initializeSiteInfrastructure




    private function initializeAsOnePager()
    {
        global $globalParams;
        $this->siteStructure = new SiteStructure($this, ''); //->list = false;
        $this->currPage = '';
        $globalParams['pageFolder'] = '';
        $globalParams['pagePath'] = '';
        $globalParams['filepathToRoot'] = '';

        $this->pathToPage = PAGES_PATH;
        $globalParams['pathToPage'] = $this->pathToPage;
        $this->pageRelativePath = '';
        $this->pagePath = '';
        $this->trans->addVariable('next_page', "");
        $this->trans->addVariable('prev_page', "");
    } // initializeAsOnePager





    private function storeToCache($html)
    {
        if (!$this->config->site_enableCaching || !$GLOBALS['globalParams']['cachingActive']) {
            return;
        }
        if (isset($_SESSION['lizzy']['nc']) && $_SESSION['lizzy']['nc']) {
            return;
        }

        $requestedPage = $this->getCacheFilename( false );
        preparePath($requestedPage);
        if (preg_match('/^( .* <body.*?class=["\'] .+? ) (["\'] .* )$/xms', $html, $m)) {
            $html = $m[1] . ' lzy-cached' . $m[2];
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
        if (isset($_GET['reset'])) {  // nc = no-caching -> when specified, make sure page cache is cleared
            purgePageCache();
        }
        if (isset($_GET['nc'])) {  // nc = no-caching
            $_SESSION['lizzy']['nc'] = true;
            return;
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

            exit ($html);
        }
    } // checkAndRenderCachePage




    private function getCacheFilename( $verify = true )
    {
        $scriptPath = dir_name($_SERVER['SCRIPT_NAME']);
        // ignore filename part of request:
        $requestUri = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        if (fileExt($requestUri)) {
            $requestUri = dir_name($requestUri);
        }
        $appRoot = fixPath(commonSubstr( $scriptPath, dir_name($requestUri), '/'));
        $ru = preg_replace('/\?.*/', '', $requestUri); // remove opt. '?arg'
        $requestedpageHttpPath = dir_name(substr($ru, strlen($appRoot)));
        if ($requestedpageHttpPath === '.') {
            $requestedpageHttpPath = '';
        }

        $lang = isset($_SESSION['lizzy']['lang']) ? $_SESSION['lizzy']['lang'] : '';
        if ($lang) {
            $lang = ".$lang";
        }

        $requestedPage = PAGE_CACHE_PATH . fixPath($requestedpageHttpPath) . "index$lang.html";

        if ($verify) {
            if (file_exists($requestedPage)) {
                if (!$requestedpageHttpPath) {
                    $requestedpageHttpPath = 'home/';
                }
                $requestedpageHttpPath = './' . PAGES_PATH . $requestedpageHttpPath;
                $t0 = 0;
                $it = new RecursiveDirectoryIterator( $requestedpageHttpPath );
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

} // class WebPage



function purgePageCache()
{
    rrmdir(PAGE_CACHE_PATH);
} // purgePageCache

