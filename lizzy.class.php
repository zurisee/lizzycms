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

define('DATA_PATH',            'data/');
define('CACHE_PATH',            '.#cache/');
define('LOGS_PATH',             '.#logs/');
define('MACROS_PATH',           SYSTEM_PATH.'macros/');
define('EXTENSIONS_PATH',       SYSTEM_PATH.'extensions/');
define('USER_INIT_CODE_FILE',   USER_CODE_PATH.'init-code.php');
define('USER_VAR_DEF_FILE',     USER_CODE_PATH.'var-definitions.php');
define('ICS_PATH',              'ics/'); // where .ics files are located

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
define('VERSION_CODE_FILE',     LOGS_PATH.'version-code.txt');
define('BROWSER_SIGNATURES_FILE', LOGS_PATH.'browser-signatures.txt');
define('UNKNOWN_BROWSER_SIGNATURES_FILE',     LOGS_PATH.'unknown-browser-signatures.txt');
define('LOGIN_LOG_FILENAME',    LOG_FILE);
define('UNDEFINED_VARS_FILE',   CACHE_PATH.'undefinedVariables.yaml');
define('FAILED_LOGIN_FILE',     CACHE_PATH.'_failed-logins.yaml');
define('HACK_MONITORING_FILE',  CACHE_PATH.'_hack_monitoring.yaml');
define('ONETIME_PASSCODE_FILE', CACHE_PATH.'_onetime-passcodes.yaml');
define('HACKING_THRESHOLD',     10);
define('HOUSEKEEPING_FILE',     CACHE_PATH.'_housekeeping.txt');
define('MIN_SITEMAP_INDENTATION', 4);

define('MKDIR_MASK',            0700); // remember to modify _lizzy/_install/install.sh as well
define('MKDIR_MASK2',           0700); // ??? test whether higher priv is necessary

$files = ['config/user_variables.yaml', '_lizzy/config/*', '_lizzy/macros/transvars/*'];


use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DomCrawler\Crawler;


require_once SYSTEM_PATH.'auxiliary.php';
require_once SYSTEM_PATH.'vendor/autoload.php';
require_once SYSTEM_PATH.'page.class.php';
require_once SYSTEM_PATH.'popup.class.php';
require_once SYSTEM_PATH.'transvar.class.php';
require_once SYSTEM_PATH.'lizzy-markdown.class.php';
require_once SYSTEM_PATH.'scss.class.php';
require_once SYSTEM_PATH.'defaults.class.php';
require_once SYSTEM_PATH.'sitestructure.class.php';
require_once SYSTEM_PATH.'authentication.class.php';
require_once SYSTEM_PATH.'image-resizer.class.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
//require_once SYSTEM_PATH.'datastorage.class.php';
require_once SYSTEM_PATH.'sandbox.class.php';
require_once SYSTEM_PATH.'uadetector.class.php';
require_once SYSTEM_PATH.'user-account-form.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';
require_once SYSTEM_PATH.'service-tasks.php';


$globalParams = array(
	'pathToRoot' => null,			// ../../
	'pageHttpPath' => null,				// pages/xy/
	'pagePath' => null,				// pages/xy/    -> may differ from pageHttpPath in case 'showThis' is used
    'path_logPath' => null,
    'activityLoggingEnabled' => false,
    'errorLoggingEnabled' => false,
);


class Lizzy
{
    private $lzyDb = null;  // -> SQL DB for caching DataStorage data-files
	private $currPage = false;
	private $configPath = CONFIG_PATH;
	private $systemPath = SYSTEM_PATH;
	private $autoAttrDef = [];
	public  $pathToRoot;
	public  $pageHttpPath;
	public  $pagePath;
	private $reqPagePath;
	public  $siteStructure;
	public  $trans;
	public  $page;
	private $editingMode = false;
	private $timer = false;



	//....................................................
    public function __construct()
    {
        $this->setDefaultTimezone();
        $this->checkInstallation();

        runServiceTasks($this);
		$this->init();
		$this->setupErrorHandling();

        if ($this->config->site_sitemapFile || $this->config->feature_sitemapFromFolders) {
            $this->initializeSiteInfrastructure();

        } else {
            $this->initializeAsOnePager();
        }

        $this->handleEditSaveRequests();
        $this->restoreEdition();  // if user chose to activate a previous edition of a page

        $this->trans->addVariable('debug_class', '');   // just for compatibility
//Todo:
// - optimize: init1 / init2 -> tasks unnecessary for serviceTasks
// - move runServiceTasks(2) up before handleEditSaveRequests(), initializeSiteInfrastructure()
// - poss. 3rd level at current position, i.e. after initializeSiteInfrastructure()
// - option for serviceTask to define on which level it should be executed
        runServiceTasks($this, 2);
    } // __construct




    //....................................................
    private function init()
    {
        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            die("Error: file not found: ".$configFile);
        }

        session_start();
        $this->sessionId = session_id();

        $this->getConfigValues(); // from config/config.yaml

        $this->setLocale();

        $this->localCall = $this->config->localCall;

        register_shutdown_function('handleFatalPhpError');

        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));

        $GLOBALS['globalParams']['isAdmin'] = false;
        $GLOBALS['globalParams']['activityLoggingEnabled'] = $this->config->admin_activityLogging;
        $GLOBALS['globalParams']['errorLoggingEnabled'] = $this->config->debug_errorLogging;

        $this->trans = new Transvar($this);
        $this->page = new Page($this);

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
        $this->scss->compile( $this->config->debug_forceBrowserCacheUpdate );

        // Future: optionally enable Auto-Attribute mechanism
        //        $this->loadAutoAttrDefinition();

        $urlArgs = ['config', 'list', 'help', 'admin', 'reset', 'login', 'unused', 'reset-unused', 'remove-unused', 'log', 'info', 'touch'];
        foreach ($urlArgs as $arg) {
            if (isset($_GET[$arg])) {
                $this->config->site_enableCaching = false;
                break;
            }
        }
        $this->config->cachingActive = $this->config->site_enableCaching;
        $GLOBALS['globalParams']['cachingActive'] = $this->config->site_enableCaching;
    } // init




    //....................................................
    public function render()
    {
		if ($this->timer) {
			startTimer();
		}

		$this->selectLanguage();

		$accessGranted = $this->checkAdmissionToCurrentPage();   // override page with login-form if required

        $this->injectAdminCss();
        $this->setTransvars1();

        if ($accessGranted) {

            // enable caching of compiled MD pages:
            if ($this->config->cachingActive && $this->page->readFromCache()) {
                $html = $this->page->render(true);
                $html = $this->resolveAllPaths($html);
                if ($this->timer) {
                    $timerMsg = 'Page rendering time: '.readTimer();
                    $html = $this->page->lateApplyMessag($html, $timerMsg);
                }
                return $html;
            }

            $this->loadFile();        // get content file
        }
        $this->injectPageSwitcher();

        $this->warnOnErrors();

        $this->setTransvars2();

        if ($accessGranted) {
            $this->runUserInitCode();
        }
        $this->loadTemplate();

        if ($accessGranted) {
            $this->injectEditor();

            $this->trans->loadUserComputedVariables();
        }

        $this->appendLoginForm($accessGranted);   // sleeping code for popup population
        $this->handleAdminRequests2();
        $this->handleUrlArgs2();

        // Future: optionally enable Auto-Attribute mechanism
        //        $html = $this->executeAutoAttr($html);

        $this->handleConfigFeatures();


        // now, compile the page from all its components:
        $html = $this->page->render();

        $this->prepareImages($html);

        $this->applyForcedBrowserCacheUpdate($html);

        $html = $this->resolveAllPaths($html);

        if ($this->timer) {
            $timerMsg = 'Page rendering time: '.readTimer();
            $html = $this->page->lateApplyMessag($html, $timerMsg);
		}
$html .= "\n<!-- test modif -->\n";
        return $html;
    } // render






    private function handleAdminRequests()
    {
        $userAdminInitialized = file_exists(CONFIG_PATH.$this->config->admin_usersFile);
        if (!isset($_REQUEST['lzy-user-admin']) ||
            (!$this->auth->isAdmin() && $userAdminInitialized)) {
            return false;   // nothing to do
        }
        require_once SYSTEM_PATH.'admintasks.class.php';
        $adm = new AdminTasks($this);
        $adm->handleAdminRequests( $_REQUEST['lzy-user-admin'] );

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
        $pathToRoot = $globalParams['appRoot'];

        if (!$this->config->admin_useRequestRewrite) {
            resolveHrefs($html);
        }

        // Handle resource accesses first: src='~page/...' -> local to page but need full path:
        $p = $globalParams["appRoot"].$globalParams["pathToPage"];
        $html = preg_replace(['|(src=[\'"])(?<!\\\\)~page/|', '|(srcset=[\'"])(?<!\\\\)~page/|'], "$1$p", $html);

        // Handle all other special links:
        $from = [
            '|(?<!\\\\)~/|',
            '|(?<!\\\\)~data/|',
            '|(?<!\\\\)~sys/|',
            '|(?<!\\\\)~ext/|',
            '|(?<!\\\\)~page/|',
        ];
        $to = [
            $pathToRoot,
            $pathToRoot.$globalParams['dataPath'],
            $pathToRoot.SYSTEM_PATH,
            $pathToRoot.EXTENSIONS_PATH,
            '',   // for page accesses
        ];

        $html = preg_replace($from, $to, $html);

        // remove shields: e.g. \~page
        $html = preg_replace('|(?<!\\\\)\\\\~|', "~", $html);

        return $html;
    } // resolveAllPaths




    //....................................................
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

        //        } elseif ($this->config->debug_autoForceBrowserCache) {
        //            $forceUpdate = getVersionCode();
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




    //....................................................
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

        if ($this->config->debug_errorLogging && !file_exists(ERROR_LOG_ARCHIVE)) {
            $errorLogPath = dirname(ERROR_LOG_ARCHIVE).'/';
            $errorLogFile = ERROR_LOG_ARCHIVE;

            // make error log folder:
            preparePath($errorLogPath);
            if (!is_writable($errorLogPath)) {
                die("Error: no write permission to create error log folder '$errorLogPath'");
            }

            // make error archtive file and check
            touch($errorLogFile);
            if (!file_exists($errorLogFile) || !is_writable($errorLogPath)) {
                die("Error: unable to create error log file '$errorLogPath' - probably access rights are not ");
            }

            // make error log file, check and delete immediately
            touch(ERROR_LOG);
            if (!file_exists(ERROR_LOG) || !is_writable(ERROR_LOG)) {
                die("Error: unable to create error log file '".ERROR_LOG."' - probably access rights are not ");
            }
            unlink(ERROR_LOG);

            ini_set("log_errors", 1);
            ini_set("error_log", $errorLogFile);
            //error_log( "Error-logging started" );

            $globalParams['errorLogFile'] = ERROR_LOG;
        }
    } // setupErrorHandling




    //....................................................
    private function checkAdmissionToCurrentPage()
    {
        if ($reqGroups = $this->isRestrictedPage()) {     // handle case of restricted page
            if (!$this->auth->checkGroupMembership( $reqGroups )) {
                $this->renderLoginForm();
                return false;
            }
            setStaticVariable('isRestrictedPage', $this->auth->getLoggedInUser());
        } else {
            setStaticVariable('isRestrictedPage', false);
        }
        return true;
    } // checkAdmissionToCurrentPage




    //....................................................
    private function appendLoginForm($accessGranted)
    {
        if ( !$this->auth->getKnownUsers() ) { // don't bother with login if there are no users
            return;
        }

        if ($this->auth->getLoggedInUser(true)) {   // signal in body tag class whether user is logged in
            $this->page->addBodyClasses('lzy-user-logged-in');  // if user is logged in, there's no need for login form
            return;
        }

        if (($user = getUrlArg('login', true)) !== null) {
            $this->page->addPopup(['contentFrom' => '#lzy-login-form', 'triggerSource' => '.lzy-login-link', 'autoOpen' => true]);
            $this->renderLoginForm();
            if ($user) {    // preset username if known
                $jq = "$('.lzy-login-username').val('$user');\nsetTimeout(function() { $('.lzy-login-email').val('$user').focus(); },500);";
                $this->page->addJq($jq, 'append');
            }

        } elseif ($this->config->feature_preloadLoginForm) {    // preload login form if configured
            $this->page->addPopup(['contentFrom' => '#lzy-login-form', 'triggerSource' => '.lzy-login-link']);
            $this->renderLoginForm();

        } elseif (!$accessGranted) {
            $loginForm = $this->renderLoginForm( false );
            $this->page->addContent($loginForm);
        }
    } // appendLoginForm



    //....................................................
    private function loadAutoAttrDefinition($file = false)
    {
        if (!$file) {
            if (!file_exists($this->config->feature_autoAttrFile)) {
                return;
            }
            $file = $this->config->feature_autoAttrFile;
        }
        $autoAttrDef = getYamlFile($file);
        if ($autoAttrDef) {
            $this->autoAttrDef = array_merge($this->autoAttrDef, $autoAttrDef);
        }
        return;
    } // loadAutoAttrDefinition



    //....................................................
    private function analyzeHttpRequest()
    {
    // appRoot:         path from docRoot to base folder of app, mostly = ''; appRoot == '~/'
    // pageHttpPath:        forward-path from appRoot to requested folder, e.g. 'contact/ (-> excludes pages/)
    // pagePath:        forward-path from appRoot to requested folder, e.g. 'contact/ (-> excludes pages/)
    // pathToPage:      filesystem forward-path from appRoot to requested folder, e.g. 'pages/contact/ (-> includes pages/)
    // pathToRoot:      upward-path from requested folder to appRoot, e.g. ../
    // redirectedPath:  if requests get redirected by .htaccess, this is the skipped folder(s), e.g. 'now_active/'

    // $globalParams:   -> pageHttpPath, pagePath, pathToRoot, redirectedPath
    // $_SESSION:       -> userAgent, pageName, currPagePath, lang

        global $globalParams, $pathToRoot;

        $requestUri     = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        $absAppRoot     = dir_name($_SERVER['SCRIPT_FILENAME']);
        $scriptPath     = dir_name($_SERVER['SCRIPT_NAME']);
        // ignore filename part of request:
        if (fileExt($requestUri)) {
            $requestUri     = dir_name($requestUri);
        }
        $appRoot        = fixPath(commonSubstr( $scriptPath, dir_name($requestUri), '/'));
        $redirectedPath = ($h = substr($scriptPath, strlen($appRoot))) ? $h : '';
        $requestedPath  = dir_name($requestUri);
        $ru = preg_replace('/\?.*/', '', $requestUri); // remove opt. '?arg'
        $requestedpageHttpPath = dir_name(substr($ru, strlen($appRoot)));
        if ($requestedpageHttpPath == '.') {
            $requestedpageHttpPath = '';
        }

        // if operating without request-rewrite, we rely on page request being transmitted in url-arg 'lzy':
        if (isset($_GET['lzy'])) {
            $requestedPath = $_GET['lzy'];
        }
        if (strpos($requestedPath, $appRoot) === 0) {
            $pageHttpPath = substr($requestedPath, strlen($appRoot));
        } else {
            $pageHttpPath = $requestedPath;
        }
        $requestScheme = ((isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'])) ? $_SERVER['REQUEST_SCHEME'].'://' : 'HTTP://';
        $requestedUrl = $requestScheme.$_SERVER['HTTP_HOST'].$requestUri;
        $globalParams['requestedUrl'] = $requestedUrl;
        $globalParams['pagePath'] = null;
        $globalParams['pathToPage'] = null; // needs to be set after determining actually requested page

        $pageHttpPath = $this->auth->handleAccessCodeInUrl( $pageHttpPath );

        $pageHttpPath       = strtolower($pageHttpPath);
        if ($this->config->feature_filterRequestString) {
            // Example: abc[2]/
            $pageHttpPath = preg_replace('/[^a-z_-]+ \w* [^a-z_-]+/ix', '', rtrim($pageHttpPath, '/')).'/';
        }
        $pathToRoot = str_repeat('../', sizeof(explode('/', $requestedpageHttpPath)) - 1);

        $globalParams['pageHttpPath'] = $pageHttpPath;
        $globalParams['pagesFolder'] = $this->config->path_pagesPath;


        $globalParams['dataPath'] = $this->config->site_dataPath;

        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $this->pageHttpPath = $pageHttpPath;
        $this->pathToRoot = $pathToRoot;
        $this->config->pathToRoot = $pathToRoot;

        $globalParams['host'] = $requestScheme.$_SERVER['HTTP_HOST'].'/';
        $this->appRootUrl = $requestScheme.$_SERVER['HTTP_HOST'] . $appRoot;
        $this->pageUrl = $requestScheme.$_SERVER['HTTP_HOST'].$requestedPath;
        $globalParams['pageUrl'] = $this->pageUrl;


        $globalParams['absAppRoot'] = $absAppRoot;  // path from FS root to base folder of app, e.g. /Volumes/...
        $globalParams['absAppRootUrl'] = $globalParams["host"] . substr($appRoot, 1);  // path from FS root to base folder of app, e.g. /Volumes/...



        if (!$pageHttpPath) {
            $pageHttpPath = './';
        }


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


        // get info about browser
        list($ua, $isLegacyBrowser) = $this->getBrowser();
        $globalParams['userAgent'] = $ua;
        $_SESSION['lizzy']['userAgent'] = $ua;
        $globalParams['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

        // check whether to support legacy browsers -> load jQ version 1
        if ($this->config->feature_supportLegacyBrowsers) {
            $this->config->isLegacyBrowser = true;
            $globalParams['legacyBrowser'] = true;
            writeLog("Legacy-Browser Support activated.");

        } else {
            $overrideLegacy = getUrlArgStatic('legacy');
            if ($overrideLegacy === null) {
                $this->config->isLegacyBrowser = $isLegacyBrowser;
            } else {
                $this->config->isLegacyBrowser = $overrideLegacy;
            }
        }
        $globalParams['legacyBrowser'] = $this->config->isLegacyBrowser;


        $this->reqPagePath = $pageHttpPath; //???ok
        $globalParams['appRoot'] = $appRoot;  // path from docRoot to base folder of app, e.g. 'on/'
        $globalParams['redirectedPath'] = $redirectedPath;  // the part that is optionally skippped by htaccess
        $globalParams['localCall'] = $this->localCall;


        $_SESSION['lizzy']['pagePath'] = $pageHttpPath;     // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed
        $_SESSION['lizzy']['pageHttpPath'] = $pageHttpPath;     // for _upload_server.php -> temporaty, corrected later in rendering when sitestruct has been analyzed

        $_SESSION['lizzy']['pathToPage'] = null; //???
        $_SESSION['lizzy']['pagesFolder'] = $this->config->path_pagesPath;
        $baseUrl = $requestScheme.$_SERVER['SERVER_NAME'];
        $_SESSION['lizzy']['appRootUrl'] = $baseUrl.$appRoot; // https://domain.net/...
        $_SESSION['lizzy']['absAppRoot'] = $absAppRoot;
        $_SESSION['lizzy']['dataPath'] = $this->config->site_dataPath;

        if ($this->config->debug_logClientAccesses) {
            writeLog('[' . getClientIP(true) . "] $ua" . (($this->config->isLegacyBrowser) ? " (Legacy browser!)" : ''));
        }
    } // analyzeHttpRequest





    //....................................................
    private function getConfigValues()
    {
        global $globalParams;

        $this->config = new Defaults($this->configFile);

        $this->config->pathToRoot = $this->pathToRoot;

        $globalParams['path_logPath'] = $this->config->path_logPath;
    } // getConfigValues




    //....................................................
    private function loadTemplate()
    {
        $template = $this->getTemplate();
        $template = $this->page->shieldVariable($template, 'head_injections');
        $template = $this->page->shieldVariable($template, 'body_tag_injections');
        $template = $this->page->shieldVariable($template, 'body_top_injections');
        $template = $this->page->shieldVariable($template, 'content');
        $template = $this->page->shieldVariable($template, 'body_end_injections');
        $this->page->addTemplate($template);
    } // loadTemplate




	//....................................................
	private function isRestrictedPage()
	{
		if (isset($this->siteStructure->currPageRec['restricted'])) {
			$lockProfile = $this->siteStructure->currPageRec['restricted'];
			return $lockProfile;
		}
		return false;
	} // isRestrictedPage



	//....................................................
	private function restoreEdition()
	{
        $admission = $this->auth->checkGroupMembership('editors');
        if (!$admission) {
            return;
        }

        $edSave = getUrlArg('ed-save', true);
        if ($edSave !== null) {
            require_once SYSTEM_PATH . 'page-source.class.php';
            PageSource::saveEdition();  // if user chose to activate a previous edition of a page

            // need to compile the restored page:
            $this->scss = new SCssCompiler($this);
            $this->scss->compile( $this->config->debug_forceBrowserCacheUpdate );
        }
    } // restoreEdition



	//....................................................
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



	//....................................................
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




    //....................................................
    private function injectAdminCss()
    {
        if ($this->auth->checkGroupMembership('admins') ||
            $this->auth->checkGroupMembership('editors') ||
            $this->auth->checkGroupMembership('fileadmins')) {
                $this->page->addCssFiles('~sys/css/_admin.css');
        }

    } // injectAdminCss




    //....................................................
	private function setTransvars1()
	{
	    if ($this->auth->knownUsers) {
            $userAcc = new UserAccountForm($this);
            $rec = $this->auth->getLoggedInUser(true);
            $login = $userAcc->renderLoginLink($rec);
            $userName = $userAcc->getUsername();
        } else {
            $userName = '';
            $login = <<<EOT
    <span class="lzy-tooltip-arrow" data-lzy-tooltip-from='login-warning' style="border-bottom:none;">
        <span class='lzy-icon-error'></span>
    </span>
    <div id="login-warning" style="display:none;">Warning:<br>no users defined - login mechanism is disabled.<br>&rarr; see config/users.yaml</div>
EOT;
            $this->page->addModules('TOOLTIPS');
        }
        $this->trans->addVariable('lzy-login-button', $login);
        $this->trans->addVariable('user', $userName, false);

        if ($this->auth->isAdmin()) {
            $url = $GLOBALS["globalParams"]["pageUrl"];
            $this->trans->addVariable('lzy-config--open-button', "<a class='lzy-config-button' href='$url?config'>{{ lzy-config-button }}</a>", false);
        }

        if ($this->config->admin_enableFileManager && $this->auth->checkGroupMembership('fileadmins')) {
            $this->trans->addVariable('lzy-fileadmin-button', "<button class='lzy-fileadmin-button' title='{{ lzy-fileadmin-button-tooltip }}'><span class='lzy-icon-docs'></span>{{^ lzy-fileadmin-button-text }}</button>", false);
            $uploader = $this->injectUploader($this->pagePath);
            $this->page->addBodyEndInjections($uploader);
        } else {
            $this->trans->addVariable('lzy-fileadmin-button', "", false);
        }

        $this->trans->addVariable('appRootUrl', $this->appRootUrl);
        $this->trans->addVariable('pageUrl', $this->pageUrl);
        $this->trans->addVariable('appRoot', $this->pathToRoot);			// e.g. '../'
        $this->trans->addVariable('systemPath', $this->systemPath);		// -> file access path
        $this->trans->addVariable('lang', $this->config->lang);


		if  (getUrlArgStatic('debug')) {
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
        $this->trans->addVariable('lzy-version', getGitTag());

		if ($this->config->feature_pageSwitcher) {
            $this->definePageSwitchLinks();
        }

    } // setTransvars1



	//....................................................
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
            $this->page->addBodyClasses("page_$pageName path_$pagePathClass");
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
            'uploadPath' => $this->page->config->path_pagesPath.$filePath,
            'pagePath' => $GLOBALS['globalParams']['pagePath'],
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



    //....................................................
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




	//....................................................
	private function runUserInitCode()
	{
	    if (!$this->config->custom_permitUserInitCode) {   // user-init-code enabled?
	        return;
        }

		if (file_exists($this->config->userInitCodeFile)) {
            require_once $this->config->userInitCodeFile;
		}
	} // runUserInitCode
	



	//....................................................
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
	


	//....................................................
    private function loadHtmlFile($folder, $file)
	{
		$page = &$this->page;
		if (strpos($file, '~/') === 0) {
			$file = substr($file, 2);
		} else {
			$file = $folder.$file;
		}
		$file = $this->config->path_pagesPath.$file;
		if (file_exists($file)) {
			$html = file_get_contents($file);
			$page->addContent($this->extractHtmlBody($html), true);
		} else {
			$page->addOverride("Requested file not found: '$file'");
		}
		return $page;
	} // loadHtmlFile



	//....................................................
    private function loadFile($loadRaw = false)
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

        $folder = $this->config->path_pagesPath.resolvePath($folder);
		$this->handleMissingFolder($folder);

		$mdFiles = getDir($folder.'*.{md,txt}');
        registerFileDateDependencies($mdFiles);

		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir($this->config->path_pagesPath.$folder.'*.{md,txt}');
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

			$mdStr = $this->extractFrontmatter($mdStr, $newPage);

            // frontmatter option 'visibility' -> reveal only to logged in users:
            $visibility = $newPage->get('visibility', true);
            if (($visibility) && !$this->auth->checkPrivilege($visibility)) {
                continue;
            }

            $variables = $newPage->get('variables', true);
            if ($variables) {
                $this->trans->addVariables($variables);
            }

            if ($ext === 'md') {             // it's an MD file, convert it

                $eop = strpos($mdStr, '__EOP__');           // check for 'end of page' marker, if found exclude all following (also following mdFiles)
                if (($eop !== false) && ($mdStr[$eop-1] != '\\')) {
                    $mdStr = preg_replace('/__EOP__.*/sm', '', $mdStr);
                    $eop = true;
                }

                $md->parse($mdStr, $newPage);
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
			if ($wrapperClass = $newPage->get('wrapperClass')) {
				$cls .= ' '.$wrapperClass;
			}
			$cls = trim($cls);
			$str = $newPage->get('content');

			if (!$wrapperTag = $newPage->get('wrapperTag')) {
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
			($this->siteStructure->currPageRec['backTickVariables'] == 'no')) {
			$html = str_replace('`', '&#96;', $html);
			$html = $this->extractHtmlBody($html);
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
        $cssStr = str_replace(['#this', '.this'], ["#$id", ".$class"], $cssStr); // '#this', '.this' are short-hands for section class/id
        $newPage->addCss($cssStr, true);
    } // compileLocalCss




	//....................................................
    private function handleMissingFolder($folder)
	{
	    if ($this->auth->getLoggedInUser() || $this->localCall) {
            if (!file_exists($folder)) {
                $mdFile = $folder . basename(substr($folder, 0, -1)) . '.md';
                mkdir($folder, MKDIR_MASK, true);
                $name = $this->siteStructure->currPageRec['name'];
                file_put_contents($mdFile, "---\n// Frontmatter:\ncss: |\n---\n\n# $name\n");
            }
        }
    } // handleMissingFolder



	//....................................................
    private function prepareImages($html)
	{
        $resizer = new ImageResizer($this->config->feature_ImgDefaultMaxDim);
        $modified = $resizer->provideImages($html);
        if ($modified) {
            checkInstallation1();
        }
    } // prepareImages



	//....................................................
    private function extractFrontmatter($str, $page)
	{
		$lines = explode(PHP_EOL, $str);
		$yaml = '';
		if (!preg_match('/^---/', $lines[0]) || (sizeof($lines) < 2)) {
			return $str;
		}
		$i = 1;
		$l = $lines[$i];
		while (($i < sizeof($lines)-1) && (!preg_match('/---/', $l))) {
			$yaml .= $l."\n";
			$i++;
			$l = $lines[$i];
		}
		if ($i == sizeof($lines)-1) {   // case '---' in first line, but no second instance
		    return $str;
        }

		if (preg_match('/\S/', $yaml)) {
			$yaml = str_replace("\t", '    ', $yaml);
			try {
				$hdr = convertYaml($yaml);
			} catch(Exception $e) {
                fatalError("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n".$e->getMessage(), 'File: '.__FILE__.' Line: '.__LINE__);
			}
            if (isset($hdr['screenSizeBreakpoint'])) {  // special case: screenSizeBreakpoint -> propagate immediately
                $this->config->feature_screenSizeBreakpoint = $hdr['screenSizeBreakpoint'];
            }
            if (isset($hdr['dataPath'])) {  // special case: dataPath -> propagate immediately
                $_SESSION['lizzy']['dataPath'] = $hdr['dataPath'];
                $GLOBALS['globalParams']['dataPath'] = $hdr['dataPath'];
                unset($hdr['dataPath']);
            }
			if (is_array($hdr)) {
			    if ($hdr) {
                    $page->merge($hdr);
                    $frontmatter =  $this->page->get('frontmatter');
                    if ($frontmatter) {
                        $hdr = array_merge($hdr, $this->page->get('frontmatter'));
                    }
                    $this->page->set('frontmatter', $hdr);
                }
			} else {
                fatalError("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n", 'File: '.__FILE__.' Line: '.__LINE__);
            }
		}
		$lines = array_slice($lines, $i+1);
		$str = implode("\n",  $lines);

		return $str;
	} // extractFrontmatter



	//....................................................
    private function extractHtmlBody($html)
    {
		if (((($p1 = strpos($html, "---")) !== false) && (($p1 == 0) || (substr($html,$p1-1,1) == "\n")))) {
			$p1 = strpos($html, "\n", $p1+3);
			if ($p2 = strpos($html, "\n---", $p1+3)) {
				$head = substr($html, $p1+1, $p2-$p1-1);
				$values = convertYaml($head);
				$this->page->merge($values);
				$html = substr($html, $p2+4);
			}
		}
		if (($p1=strpos($html, '<body')) !== false) {
			$p1 = strpos($html, '>', $p1);
			if (($p2=strpos($html, '</body')) !== false) {
				$html = trim(substr($html, $p1+1, $p2-$p1-1));
			}
		}
		return $html;
	} // extractHtmlBody




    private function disableCaching()
    {
        $this->config->site_enableCaching = false;
        $this->config->cachingActive = false;
        $GLOBALS['globalParams']['cachingActive'] = false;
    } // disableCaching




    //....................................................
	private function handleUrlArgs()
	{
        if ($arg = getNotificationMsg()) {
            $arg = $this->trans->translateVariable($arg);
            $this->page->addMessage( $arg );
        }

        if (getUrlArg('reset')) {			            // reset (cache)
            $this->disableCaching();
            clearCaches( $this, false );

        }


		if (getUrlArg('logout')) {	// logout
            $this->userRec = false;
            $this->auth->logout();
            reloadAgent(false, 'lzy-logout-successful'); // reload to get rid of url-arg ?logout
        }


        if ($nc = getStaticVariable('nc')) {		// nc
            if ($nc) {
                $this->disableCaching();
            }
        }

        $this->timer = getUrlArgStatic('timer');				// timer


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

            if (getUrlArg('purge')) {                        // empty recycleBins and caches
                purgeRecyleBins( $this );
                clearCache( $this );
                clearLogs();
                reloadAgent();
            }

            if (getUrlArg('lang', true) == 'none') {                  // force language
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




    //....................................................
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




	//....................................................
	private function handleUrlArgs2()
	{
        if (getUrlArg('reset')) {			            // reset (cache)
            $this->clearCaches(true);
            reloadAgent();  //  reload to get rid of url-arg ?reset
        }

        // user wants to login in and is not already logged in:
		if (getUrlArg('login')) {                                               // login
		    if (getStaticVariable('user')) {    // already logged in -> logout first
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

        if (getUrlArg('access-link')) {                                    // reorg-css
            $user = getUrlArg('access-link', true);
            $this->createAccessLink($user);
        }

        if ($filename = getUrlArg('reorg-css', true)) {                                    // reorg-css
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

        // TODO:
        //        if ($n = getUrlArg('printall', true)) {			// printall pages
        //            exit( $this->printall($n) );
        //        }


        if (getUrlArg('log')) {    // log
            if (file_exists(ERROR_LOG)) {
                $str = file_get_contents(ERROR_LOG);
                $str = str_replace('{', '&#123;', $str);
            } else {
                $str = "Currently no error log available.";
            }
            $str = "<h1>Error Logs:</h1>\n<pre>$str</pre>";
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']);
        }



        if (getUrlArg('info')) {    // info
            $str = $this->page->renderDebugInfo();
            $str = "<h1>Lizzy System Info</h1>\n".$str;
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

        // This feature requires LiveReload (http://livereload.com/) to be running in the background
        if ($this->config->isLocalhost && getUrlArgStatic('auto', false)) {   // auto (liveReload)                        // auto reload
            $this->page->addJqFiles("http://localhost:35729/livereload.js?snipver=1");
        }

        if (getUrlArg('config')) {                              // config
            if (!$this->auth->checkGroupMembership('admins')) {
                $this->page->addMessage("Insufficient privilege for option '?config'");
                return;
            }

            $str = $this->renderConfigOverlay();
            $this->page->addOverlay(['text' => $str, 'closable' => 'reload']); // close shall reload page to remove url-arg
        }


    } // handleUrlArgs2




    //....................................................
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




    //....................................................
    private function reorganizeCss($filename)
    {
        require_once SYSTEM_PATH.'reorg_css.class.php';
        $reo = new ReorgCss($filename);
        $reo->execute();
    }




	//....................................................
	private function renderMD()
	{
        $mdStr = get_post_data('lzy_md', true);
        $mdStr = urldecode($mdStr);

		$md = new LizzyMarkdown();
		$pg = new Page;
		$mdStr = $this->extractFrontmatter($mdStr, $pg);
		$md->parse($mdStr, $pg);

		$out = $pg->get('content');
		if (getUrlArg('html')) {
			$out = "<pre>\n".htmlentities($out)."\n</pre>\n";
		}
		exit($out);
	} // renderMD



    //....................................................
    private function savePageFile()
    {
        $mdStr = get_post_data('lzy_md', true);
        $mdStr = urldecode($mdStr);
        $doSave = getUrlArg('lzy-save');
        if ($doSave && ($filename = get_post_data('lzy_filename'))) {
            $rec = $this->auth->getLoggedInUser(true);
            $user = $rec['username'];
            $group = $rec['groups'];
            $permitted = $this->auth->checkGroupMembership('editors');
            if ($permitted) {
                if (preg_match("|^{$this->config->path_pagesPath}(.*)\.md$|", $filename)) {
                    require_once SYSTEM_PATH . 'page-source.class.php';
                    PageSource::storeFile($filename, $mdStr);
                    writeLog("User '$user' ($group) saved data to file $filename.");

                } else {
                    writeLog("User '$user' ($group) tried to save to illegal file name: '$filename'.");
                    fatalError("illegal file name: '$filename'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
            } else {
                writeLog("User '$user' ($group) had no permission to modify file '$filename' on the server.");
                die("Sorry, you have no permission to modify files on the server.");
            }
        }

    }

    //....................................................
	private function saveSitemapFile($filename)
	{
        $str = get_post_data('lzy_sitemap', true);
        $permitted = $this->auth->checkGroupMembership('editors');
        $rec = $this->auth->getLoggedInUser(true);
        $user = $rec['username'];
        $group = $rec['groups'];
        if ($permitted) {
            require_once SYSTEM_PATH.'page-source.class.php';
            PageSource::storeFile($filename, $str, SYSTEM_RECYCLE_BIN_PATH);
            writeLog("User '$user' ($group) saved data to file $filename.");

        } else {
            writeLog("User '$user' ($group) has no permission to modify files on the server.");
            fatalError("Sorry, you have no permission to modify files on the server.", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    } // saveSitemapFile




	//....................................................
	private function selectLanguage()
	{
	    global $globalParams;
        // check sitemap for language setting
        if (isset($this->siteStructure->currPageRec['lang'])) {
            $lang = $this->siteStructure->currPageRec['lang'];

            if (($l = getUrlArgStatic('lang', true)) !== null) { // override if explicitly supplied
                if ($l) {
                    $lang = $l;
                    setStaticVariable('lang', $lang);
                } else {
                    setStaticVariable('lang', null);
                }
            }

        } elseif ($this->config->site_multiLanguageSupport) {    // no preference in sitemap, use default if not overriden by url-arg
            $lang = getUrlArgStatic('lang', true);
            $supportedLanguages = explode(',', str_replace(' ', '', $this->config->site_supportedLanguages ));
            if (!in_array($lang, $supportedLanguages)) {
                $lang = $this->config->site_defaultLanguage;

            } elseif (!$lang) {   // no url-arg found
                if ($lang !== null) {   // special case: empty lang -> remove static value
                    setStaticVariable('lang', null);
                }
                $lang = $this->config->site_defaultLanguage;
            }

        } else {
            $lang = $this->config->site_defaultLanguage;
        }

        $this->config->lang = $lang;
        $globalParams['lang'] = $lang;
        return $lang;
    } // selectLanguage



	//....................................................
	private function renderConfigOverlay()
	{
	    $configCmd = getUrlArg('config', true);
	    if ($configCmd === 'raw') {
	        return $this->renderRawConfigOverlay();
        }
        $level1Class = $level2Class = $level3Class = '';
        $level = max(1, min(3, intval($configCmd)));
        switch ($level) {
            case 1: $level1Class = ' class="lzy-config-viewer-hl"'; break;
            case 2: $level2Class = ' class="lzy-config-viewer-hl"'; break;
            case 3: $level3Class = ' class="lzy-config-viewer-hl"'; break;
        }
        $url = $GLOBALS["globalParams"]["pageUrl"];

        if (isset($_POST) && $_POST) {
            $this->config->updateConfigValues( $_POST, $this->configFile );
        }


        $configItems = $this->config->getConfigInfo();
        ksort($configItems);
        $out = "<h1>Lizzy Config-Items and their Purpose:</h1>\n";
        $out .= "<p>Settings stored in file <code>{$this->configFile}</code>.<br/>\n";
        $out .= "&rarr; Default values in (), values deviating from defaults are marked <span class='lzy-config-viewer-hl'>red</span>)</p>\n";
        $out .= "<p class='lzy-config-select'>Select: <a href='$url?config=1'$level1Class>Essential</a> | <a href='$url?config=2'$level2Class>Common</a> | <a href='$url?config=3'$level3Class>All</a></p>\n";
        $out .= "  <form class='lzy-config-form' action='$url?config=$level' method='post'>\n";
        $out .= "    <input class='lzy-button' type='submit' value='{{ lzy-config-save }}'>";

        $i = 1;
        foreach ($configItems as $key => $rec) {
            if ($rec[2] > $level) {     // skip elements with lower priority than requested
                continue;
            }
            $currValue = $this->config->$key;
            $displayValue = $currValue;
            $defaultValue = $this->config->getDefaultValue($key);
            $displayDefault = $defaultValue;
            $inputValue = $defaultValue;

            $diff = '';
            if ($currValue !== $defaultValue) {
                $diff = ' class="lzy-config-viewer-hl"';
            }
            $checked = '';

            if (is_bool($defaultValue)) {
                $displayValue = $currValue ? 'true' : 'false';
                $inputValue = 'true';
                $displayDefault = $defaultValue ? 'true' : 'false';
                $inputType = 'checkbox';
                $checked = ($currValue) ? " checked" : '';

            } elseif (is_int($defaultValue)) {
                $inputValue = $displayValue;
                $inputType = 'integer';

            } elseif (is_string($defaultValue)) {
                $inputValue = $displayValue;
                $inputType = 'text';

            } elseif (is_array($defaultValue)) {
                $displayValue = implode(',', $currValue);
                $inputValue = $displayValue;
                $displayDefault = implode(',', $defaultValue);
                $inputType = 'text';
            }

            $comment = $rec[1];

            $id = translateToIdentifier($key).$i++;

            $inputField = "<input id='$id' name='$key' type='$inputType' value='$inputValue'$checked />";
            $out .= "<div class='lzy-config-elem'> $inputField <label for='$id'$diff>$key</label>  &nbsp;&nbsp;&nbsp;($displayDefault)<div class='lzy-config-comment'>$comment</div></div>\n";
        }

        $out .= "    <input class='lzy-button' type='submit' value='{{ lzy-config-save }}'>";
        $out .= "  </form>\n";

        return $out;
    } // renderConfigOverlay




    private function renderRawConfigOverlay()
    {
        $out = '';
        $lastElem = '';
        $configItems = $this->config->getConfigInfo();
        ksort($configItems);
        foreach ($configItems as $item => $rec) {
            if (is_bool($rec[0])) {
                $val = $rec[0]? 'false':'true';
            } else {
                $val = $rec[0];
            }

            $str = str_pad("$item: $val", 45, ' ');
            if (substr($item, 0, 2) !== $lastElem) {
                $str = "\n$str";
                $lastElem = substr($item, 0, 2);
            }
            $out .= "$str# {$rec[1]}\n";
        }
        $this->page->addJq("$('#raw-config-text').selText();");
        return "<pre id='raw-config-text'>$out\n\n</pre>";
    }



    //....................................................
    public function sendMail($to, $subject, $message, $from = false)
    {
        if (!$from) {
            $from = $this->trans->getVariable('webmaster_email');
        }
        $explanation = "<p><strong>Message sent by e-mail when not on localhost:</strong></p>";

        if ($this->localCall) {
            $str = <<<EOT
        <div class='lzy-local-mail-sent-overlay'>
$explanation
            <pre class='debug-mail'>
                <div>Subject: $subject</div>
                <div>$message</div>
            </pre>
        </div> <!-- /lzy-local-mail-sent-overlay -->

EOT;
            $this->page->addOverlay(['text' => $str, 'mdCompile' => false ]);
        } else {
            sendMail($to, $from, $subject, $message);
        }
    } // sendMail




	//....................................................
	private function printall($maxN = true)
	{
        die('Not implemented yet');
	} // printall



    //....................................................
    private function getBrowser()
    {
        $ua = new UaDetector( $this->config->debug_collectBrowserSignatures );
        return [$ua->get(), $ua->isLegacyBrowser()];
    } // browserDetection




    //....................................................
    private function checkInstallation()
    {
        if (version_compare(PHP_VERSION, '7.1.0') < 0) {
            die("Lizzy requires PHP version 7.1 or higher to run.");
        }

        if (!file_exists(DEFAULT_CONFIG_FILE)) {
            ob_end_flush();
            echo "<pre>";
            echo shell_exec('/bin/sh _lizzy/_install/install.sh');
            echo "</pre>";
            exit;
        }
    } // checkInstallation




    //....................................................
    public function postprocess($html)
    {
        $note = $this->trans->postprocess();
        if ($note) {
            $p = strpos($html, '</body>');
            $html = substr($html, 0, $p).createWarning($note).substr($html,$p);
        }
        return $html;
    } // postprocess




    //....................................................
    public function getEditingMode()
    {
        return $this->editingMode;
    } // getEditingMode




    //....................................................
    private function setLocale()
    {
        $locale = $this->config->site_defaultLocale;
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            setlocale(LC_ALL, "$locale.utf-8");
        } else {
            setlocale(LC_ALL, "en_UK.utf-8");
        }

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
        if ($systemTimeZone == 'CEST') {    // workaround: CEST not supported
            $systemTimeZone = 'CET';
        }
        date_default_timezone_set($systemTimeZone);
        setStaticVariable('systemTimeZone', $systemTimeZone);
        return $systemTimeZone;
    } // setDefaultTimezone




    private function renderLoginForm($asPopup = true)
    {
        $accForm = new UserAccountForm($this);
        $html = $accForm->renderLoginForm($this->auth->message, false, true);
        $this->page->addModules('PANELS');
        if ($asPopup) {
            $this->page->addBodyEndInjections("\t<div class='invisible'><div id='lzy-login-form'>\n$html\t  </div>\n\t</div><!-- /login form wrapper -->\n");
            return '';
        } else {
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
        if (!$nextLabel) {
            $nextLabel = $this->config->isLegacyBrowser ? '&gt;' : '&#9002;';
        }
        $prevLabel = $this->trans->getVariable('lzy-prev-page-link-label');
        if (!$prevLabel) {
            $prevLabel = $this->config->isLegacyBrowser ? '&lt;' : '&#9001;';
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
<pre>
Available URL-commands:

<a href='?help'>?help</a>		    this message

<a href='?auto'>?auto</a>		    automatic reload of page when files change 
                    (&rarr; on Mac localhost only, requires external tool <a href="https://livereload.com" target="_blank">livereload.com</a>)  *)
<a href='?config'>?config</a>		    list configuration-items in the config-file
<a href='?convert'>?convert</a>	    convert password to hash
<a href='?debug'>?debug</a>		    adds 'debug' class to page on non-local host *)
<a href='?edit'>?edit</a>		    start editing mode *)
<a href='?iframe'>?iframe</a>		    show code for embedding as iframe
<a href='?info'>?info</a>		    list debug-info
<a href='?lang=xy'>?lang=</a>	            switch to given language (e.g. '?lang=en')  *)
<a href='?list'>?list</a>		    list <samp>transvars</samp> and <samp>macros()</samp>
<a href='?log'>?log</a>		    displays log files in overlay
<a href='?login'>?login</a>		    login
<a href='?logout'>?logout</a>		    logout
<a href='?mobile'>?mobile</a>,<a href='?touch'>?touch</a>,<a href='?notouch'>?notouch</a>	emulate modes  *)
<a href='?nc'>?nc</a>		    supress caching (?nc=false to enable caching again)  *)
<a href='?print'>?print</a>		    starts printing mode and launches the printing dialog
<a href='?print-preview'>?print-preview</a>      presents the page in print-view mode    
<a href='?purge'>?purge</a>		    empty and delete all recycle bins (i.e. copies of modified pages)
<a href='?reorg-css='>?reorg-css={file(s)}</a>take CSS file(s) and convert to SCSS (e.g. "?reorg-css=tmp/*.css")
<a href='?reset'>?reset</a>		    clear cache, session-variables and error-log
<a href='?timer'>?timer</a>		    switch timer on or off  *)

*) these options are persistent, they keep their value for further page requests. 
Unset individually as ?xy=false or globally as ?reset

</pre>
EOT;
        // TODO: printall -> add above
        $this->page->addOverlay(['text' => $overlay, 'closable' => 'reload']);
    }




    private function handleEditSaveRequests()
    {
        $cliarg = getCliArg('lzy-compile');
        if ($cliarg) {
            $this->savePageFile();
            $this->renderMD();  // exits

        }

        $cliarg = getCliArg('lzy-save');
        if ($cliarg) {
            $this->saveSitemapFile($this->config->site_sitemapFile); // exits
        }
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

        if (isset($this->siteStructure->currPageRec['showthis'])) {
            $this->pagePath = $this->siteStructure->currPageRec['showthis'];
        } else {
            $this->pagePath = $this->siteStructure->currPageRec['folder'];
        }
        $this->pathToPage = $this->config->path_pagesPath . $this->pagePath;   //  includes pages/
        $globalParams['pageHttpPath'] = $this->pageHttpPath;            // excludes pages/, takes showThis into account
        $globalParams['pagePath'] = $this->pagePath;                    // excludes pages/, takes not showThis into account
        $globalParams['pathToPage'] = $this->pathToPage;
        $globalParams['filepathToRoot'] = str_repeat('../', substr_count($this->pathToPage, '/'));
        $globalParams['filepathToDocroot'] = str_repeat('../', substr_count($globalParams["appRoot"], '/')-1);
        $_SESSION['lizzy']['pageHttpPath'] = $this->pageHttpPath;               // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pagePath'] = $this->pagePath;               // for _ajax_server.php and _upload_server.php
        $_SESSION['lizzy']['pathToPage'] = $this->config->path_pagesPath . $this->pagePath;


        $this->pageRelativePath = $this->pathToRoot . $this->pagePath;

        $this->trans->loadStandardVariables($this->siteStructure);
        $this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ nextPageLabel }}</a>");
        $this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ prevPageLabel }}</a>");
        $this->trans->addVariable('next_page_href', $this->siteStructure->nextPage);
        $this->trans->addVariable('prev_page_href', $this->siteStructure->prevPage);
    } // initializeSiteInfrastructure




    private function initializeAsOnePager()
    {
        global $globalParams;
        $this->siteStructure = new SiteStructure($this, ''); //->list = false;
        $this->currPage = '';
        $globalParams['pagePath'] = '';
        $globalParams['pageHttpPath'] = '';            // excludes pages/, takes showThis into account
        $globalParams['filepathToRoot'] = '';

        $this->pathToPage = $this->config->path_pagesPath;
        $globalParams['pathToPage'] = $this->pathToPage;
        $this->pageRelativePath = '';
        $this->pagePath = '';
        $this->trans->addVariable('next_page', "");
        $this->trans->addVariable('prev_page', "");
    } // initializeAsOnePager
} // class WebPage


