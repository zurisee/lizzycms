<?php
/*
 *	Lizzy - default and initial settings
 *
 *  Default Values
*/

class Defaults
{

 // User configurable Settings -> config/config.yaml:
private $userConfigurableSettingsAndDefaults      = [
    'admin_activityLogging'             => [true, 'If true, logs activities to file '.LOG_FILE.'.', 3 ],
    'admin_allowDisplaynameForLogin'    => [false, 'If true, users may log in using their "DisplayName" rather than their "UserName".', 3 ],
    'admin_autoAdminOnLocalhost'        => [false, 'If true, on local host user automatically has admin privileges without login.', 1 ],
    'admin_defaultUserCommChannel'      => ['email', '[email,signal] Defines the communication channel, e.g. for one-time access links.', 1 ],
    'admin_enableAccessLink'            => [true, 'Activates one-time-access-link login mechanism.', 3 ],
    'admin_defaultAccessLinkValidyTime' => [900,    'Default Time in seconds during whith an access-link is valid.', 3 ],
    'admin_defaultGuestGroup'           => ['guest', 'Name of default group for self-registration.', 3 ],
    'admin_defaultLoginValidityPeriod'  => [86400, 'Defines how long a user can access the page since the last login.', 3 ],
    'admin_enableEditing'               => [true, 'Enables online editing', 2 ],
    'admin_serviceTasks'                => [[
                                                'daily' => false,
                                                'onPageInit' => false,
                                                'onPageRenderingStart' => false,
                                                'onPageRendered' => false,
                                                'onRequestGetArg' => false,
                                                'dailyFilePurge' => false,
                                            ], 'Enables and defines various service tasks.', 2 ],
    'admin_enableSelfSignUp'            => [false, 'If true, visitors can create a guest account on their own.', 3 ],
    'admin_enforcePasswordQuality'      => [false, 'If true, a minimum password quality is enforced when users create/change their password.', 3 ],
    'admin_useRequestRewrite'           => [true, 'If true, assumes web-server supports request-rewrite (i.e. .htaccess).', 3 ],
    'admin_userAllowSelfAdmin'          => ['', '[false,true,group] If true, user can modify their account after they logged in. '.
                                            'If a group is specified, members of that group can modify.', 3 ],
    'admin_userAllowSelfAccessLink'     => [false, 'If true, user can create an "access-link"', 3 ],
    'admin_enableFileManager'           => [false, 'If true, the file-manager (upload, rename, delete) is enabled for privileged users.', 2 ],
    'admin_minPasswordLength'           => [10, '[integer] Minimum length of passwords if "admin_enforcePasswordQuality" is enabled.', 3 ],
    'admin_configDbPermission'          => [':admins', '[user:group] Defines permission to modify (.yaml) files inside '.
                                            '"config/" folder via Lizzy\'s Datastorage module. Omit "user" to give all members of "group" permission. '.
                                            'Default: ":admins".', 3 ],

    'custom_relatedGitProjects'         => ['', "Git Project(s) to be included in ?gitstat command", 3 ],
    'custom_permitUserCode'             => [false, "Only if true, user-provided code can be executed. And only if located in '".USER_CODE_PATH."'", 1 ],
    'custom_permitUserInitCode'         => [false, "Only if true, user-provided init-code can be executed. And only if located in '".USER_CODE_PATH."'", 1 ],
    'custom_permitUserVarDefs'          => [false, 'Only if true, "_code/user-var-defs.php" will be executed.', 1 ],
    'custom_wrapperTag'                 => ['section', 	'The HTML tag in which MD-files are wrapped (default: section)', 2 ],

    'debug_allowDebugInfo'              => [false, '[false|true] If true, debugging Info can be activated: use "?debug" or set debug_showDebugInfo=true', 2 ],
    'debug_collectBrowserSignatures'    => [false, 'If true, Lizzy records browser signatures of visitors.', 3 ],
    'debug_compileScssWithLineNumbers'  => [false, 'If true, original line numbers are added as comments to compiled CSS."', 1 ],
    'debug_enableDevMode'               => [false, '[false|true] Enables devolepment mode', 1 ],
    'debug_enableDevModeAutoOff'        => [false, '[false|true] If true, devolepment mode is automatically turned off by next morning', 1 ],
    'debug_errorLogging'                => [false, 'Enable or disabling logging.', 1 ],
    'debug_debugLogging'                => [false, 'Enable or disabling logging.', 1 ],
    'debug_forceBrowserCacheUpdate'     => ['false', 'If true, the browser is forced to ignore the cache and reload css and js resources on every time.', 2 ],
    'debug_logClientAccesses'           => [false, 'If true, Lizzy records visits (IP-addresses and browser/os types).', 3 ],
    'debug_showDebugInfo'               => [false, 'If true, debugging info is appended to the page (prerequisite: debug_allowDebugInfo=true and localhost or logged in as editor/admin)', 1 ],
    'debug_showUndefinedVariables'      => [false, 'If true, all undefined static variables (i.e. obtained from yaml-files) are marked.', 2 ],
    'debug_showVariablesUnreplaced'     => [false, 'If true, all static variables (i.e. obtained from yaml-files) are render as &#123;&#123; name }}.', 2 ],
    'debug_monitorUnusedVariables'      => [false, '[false|true] If true, Lizzy keeps track of variable usage. Initialize tracking with url-arg "?reset"', 2 ],
    'debug_forceDebugMode'              => [false, '[false|true] If true, forces debug mode, even if not logged in (debug mode normally activated by"?debug"', 3 ],

    'feature_autoConvertLinks'          => [false, 'If true, automatically converts text that looks like links to HTML-links (i.e. &lt;a> tags).', 1 ],
    'feature_autoLoadClassBasedModules' => [true, 'If true, automatically loads modules that are invoked by applying classes, e.g. .editable', 3 ],
    'feature_autoLoadJQuery'            => [true, 'If true, jQuery will be loaded automatically (even if not initiated explicitly by macros)', 3 ],
    'feature_enableScssTreeNotation'    => [false, 'If true, compilation of CSS and SCSS in &#126;/css/scss/ and frontmatter uses relaxed syntax: curly braces may be omitted - indentation level is used instead.', 3 ],
    'feature_enableScssInPageFolder'    => [false, 'If true, Lizzy checks for .scss files in page folder and compiles them to .css. Alternatively use "enableScssInPageFolder" in Frontmatter of specific pages.', 3 ],
    'feature_enableIFrameResizing'      => [false, 'If true, includes js code required by other pages to iFrame-embed this site', 1 ],
    'feature_externalLinksInNewWin'     => [false, 'If true, automatically makes links open in new window for all external links (i.e. starting with http).', 1 ],
    'feature_filterRequestString'       => [true, 'If true, permits only regular text in requests. Special characters will be discarded.', 3 ],
    'feature_frontmatterCssLocalToSection' => [false, 'If true, all CSS rules in Frontmatter will be modified to apply only to the current section (i.e. md-file content).', 2 ],
    'feature_jQueryModule'              => ['JQUERY', 'Specifies the jQuery Version to be loaded: one of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.', 3 ],
    'feature_pageSwitcher'              => [false, 'If true, code will be added to support page switching (by arrow-keys or swipe gestures)', 2 ],
    'feature_lateImgLoading'            => [false, 'If true, enables general use of lazy-loading of images', 2 ],
    'feature_quickview'                 => [true, 'If true, enables automatic Quickview of images', 2 ],
    'feature_ImgDefaultMaxDim'          => ['1600x1200', 'Defines the max dimensions ("WxH") to which Lizzy automatically converts images which it finds in the pages folders.', 3 ],
    'feature_SrcsetDefaultStepSize'     => [250, 'Defines the step size when Lizzy creates srcsets for images.', 3 ],
    'feature_preloadLoginForm'          => [false, 'If true, code for login popup is preloaded and opens without page load.', 3 ],
    'feature_renderTxtFiles'            => [false, 'If true, all .txt files in the pages folder are rendered (in &lt;pre>-tags, i.e. as is). Otherwise they are ignored.', 2 ],
    'feature_screenSizeBreakpoint'      => [480, '[px] Determines the point where Lizzy switches from small to large screen mode.', 1 ],
    'feature_selflinkAvoid'             => [false, 'If true, the nav-link of the current page is replaced with a local page link (to satisfy a accessibility requirement).', 2 ],
    'feature_sitemapFromFolders'        => [false, 'If true, the sitemap will be derived from the folder structure under pages/, rather than the config/sitemap.yaml file.', 3 ],
    'feature_supportLegacyBrowsers'     => [false, 'If true, jQuery 1 is loaded in case of legacy browsers.', 2 ],
    'feature_touchDeviceSupport'        => [true, 'If true, Lizzy supports swipe gestures etc. on touch devices.', 2 ],
    'feature_replaceNLandTabChars'      => [false, 'If true, "\\n" and "\\t" will be replaced to corresponding control characters.', 3 ],

    'path_logPath'                      => [LOGS_PATH, '[true|Name] Name of folder to which logging output will be sent. Or "false" for disabling logging.', 3 ],
    'path_stylesPath'                   => ['css/', 'Name of folder in which style sheets reside', 3 ],
    'path_userCodePath'                 => [USER_CODE_PATH, 'Name of folder in which user-provided PHP-code must reside.', 3 ],

    'site_defaultStyling'               => [true, 'If true, Lizzy provides basic styling as a starting point. If false, none of Lizzy\'s style-sheets are loaded.', 2 ],
    'site_compiledStylesFilename'       => ['__styles.css', 'Name of style sheet containing collection of compiled user style sheets', 2 ],
    'site_dataPath'                     => [DATA_PATH, 'Path to data/ folder.', 3 ],
    'site_devDataPath'                  => ['', 'Activates a mechanism that, in dev-mode, switches &#126;data/ to given destination. Thus, you can savely develop and test in dev-mode without overwriting hot data. Hint: set site_dataPath to "../db/".', 2 ],
    'site_devDataPathPattern'           => ['/-', 'Regex-pattern to match against appRoot path to identify, whether we are running on a dev site, e.g. "/(dev|-)". Default: "/-"', 3 ],
    'site_enableCaching'                => [false, 'If true, Lizzy\'s caching mechanism is activated.', 1 ],
    'site_enableFilesCaching'           => [false, 'If true, Lizzy\'s module caching mechanism is activated. I.e. CSS and JS files are collected and delivered to browser in just two requests.', 1 ],
    'site_enableMdCaching'              => [false, 'If true, Lizzy\'s MD caching mechanism is activated. (not fully implemented yet)', 3 ],
    'site_extractSelector'              => ['body main', '[selector] Lets an external js-app request an extract of the web-page', 3 ],
    'site_enableRelLinks'               => [true, 'If true, injects "rel links" into header, e.g. "&lt;link rel=\'next\' title=\'Next\' href=\'...\'>"', 3 ],
    'site_allowInsecureConnectionsTo'   => ['192.*', '[domain(s)] Permit login over insecure connections to webhost on stated domain/ip-address.', 1 ],
    'site_pageTemplateFile'             => ['page_template.html', "Name of file that will be used as the template. Must be located in '".CONFIG_PATH."'", 3 ],
    'site_robots'                       => [false, 'If true, Lizzy will add a meta-tag to inform search engines, not to index this site.', 1 ],
    'site_sitemapFile'                  => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchy simply by indenting.', 3 ],
    'site_supportedLanguages'           => ['en', 'Defines which languages will be supported: comma-separated list of language-codes. E.g. "en, de, fr" (first elem => default lang)', 1 ],
    'site_localeCodes'                  => ['en_GB,de_DE,fr_FR,it_IT', 'Defines prefered locale codes for supported languages. If not found, Lizzy assumes "xy_XY".', 3 ],
    'site_timeZone'                     => ['auto', 'Name of timezone, e.g. "UTC" or "CET". If auto, attempts to set it automatically.', 2 ],
    'site_cacheResetIntervall'          => [24, '(number of hours) Defines the interval after which the cache shall be reset. When that happens,'.
                                            'page requests will build up the cache again.', 3 ],
    'site_ContentSecurityPolicy'        => ['report', '[true,report,false] Enables "Content Security Policy (CSP)" for scripts embedded in pages. (Default: report)', 2 ],
    'site_enableAllowOrigin'            => ['false', 'Set to "*" or explicitly to a domain to allow other websites to include pages of this site.', 2 ],
    'site_allowFrameAncestors'          => ['\'self\'', 'Defines who can import pages from this site via &lt;frame>. Default: \'self\'. Add URL to allow others.', 3 ],

    'site_loadStyleSheets'              => [[
        'normalize.min.css' => 1,
        'layout.scss' => 1,
        'lizzy_core.scss' => 1,
        'nav.scss' => 1,
        'forms.scss' => 1,
        'lizzy_misc.scss' => 2,
        'icons.scss' => 2,
        'admin.scss' => 3,
        'htmltables.scss' => 3,
        'panels.scss' => 3,
        'popup.scss' => 3,
        'post-it.scss' => 3,
        'user_admin.scss' => 3,

        'buttons.scss' => 1,
        'debugging.scss' => 2,
        'editing.scss' => 2,
        'images.scss' => 3,
        'language-selection.scss' => 1,
        'links.scss' => 2,
        'lists.scss' => 2,
        'login.scss' => 2,
        'overlays.scss' => 1,
        'pageswitcher.scss' => 2,
        'printing.scss' => 1,
        'reveal.scss' => 1,
        'skiplinks.scss' => 1,
        'tables.scss' => 1,
        'texts.scss' => 1,
    ], 'Defines CSS/SCSS style-sheets to be loaded. 0 = not loaded, 1 = loaded, 2 = late loaded, 3 = compiled to separate css-file.', 2 ],

];


    public function __construct($lzy)
    {
        $this->lzy                      = $lzy;
        $this->macrosPath               = MACROS_PATH;
        $this->extensionsPath           = EXTENSIONS_PATH;
        $this->configPath               = CONFIG_PATH;
        $this->systemPath               = SYSTEM_PATH;
        $this->systemHttpPath           = '~/'.SYSTEM_PATH;

        $this->siteIdententation        = MIN_SITEMAP_INDENTATION;
        $this->configFile               = $lzy->configFile;


        // values not to be modified by config.yaml file:
        $this->admin_usersFile                   = 'users.yaml';
        $this->class_panels_widget               = 'lzy-panels-widget'; // 'Class-name for Lizzy\'s Panels widget that triggers auto-loading of corresponding modules' ],
        $this->class_editable                    = 'lzy-editable'; // 'Class-name for "Editable Fields" that triggers auto-loading of corresponding modules' ],
        $this->class_zoomTarget                  = 'zoomTarget'; // 'Class-name for "ZoomTarget Elements" that triggers auto-loading of corresponding modules' ],
        $this->custom_variables                  = 'variables*.yaml'; // 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],


        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invocation. The higher the earlier.
        $this->jQueryWeight = 200;
        $this->loadModules['JQUERY']                = array('module' => 'third-party/jquery/jquery-3.6.0.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']               = array('module' => 'third-party/jquery/jquery-3.6.0.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']               = array('module' => 'third-party/jquery/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']              = array('module' => 'third-party/jqueryui/jquery-ui.min.js, '.
                                                            'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']          = array('module' => 'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['MOMENT']                = array('module' => 'third-party/moment/moment.min.js', 'weight' => $this->jQueryWeight + 9);

        $this->loadModules['NORMALIZE_CSS']         = array('module' => 'css/normalize.min.css', 'weight' => 150);
        $this->loadModules['TOUCH_DETECTOR']        = array('module' => 'js/touch_detector.js', 'weight' => 149);
        $this->loadModules['EVENT_UE']              = array('module' => 'third-party/jquery.event.ue/jquery.event.ue.min.js', 'weight' => 145);


        $this->loadModules['FONTAWESOME_CSS']       = array('module' => 'https://use.fontawesome.com/releases/v5.3.1/css/all.css', 'weight' => 135);

        $this->loadModules['MD5']                   = array('module' => 'third-party/javascript-md5/md5.min.js', 'weight' => 132);
        $this->loadModules['AUXILIARY']             = array('module' => 'js/auxiliary.js', 'weight' => 130);

        $this->loadModules['REVEAL']                = array('module' => 'js/reveal.js', 'weight' => 128);
        $this->loadModules['TABBABLE']              = array('module' => 'third-party/tabbable/jquery.tabbable.min.js', 'weight' => 126);
        $this->loadModules['NAV']                   = array('module' => 'js/nav.js,css/_nav.css', 'weight' => 125);

        $this->loadModules['FORMS']                 = array('module' => 'js/forms.js', 'weight' => 124);
        $this->loadModules['HTMLTABLE']             = array('module' => 'js/htmltable.js,css/_htmltables.css', 'weight' => 123);

        $this->loadModules['LIVE_DATA']             = array('module' => 'extensions/livedata/js/live_data.js', 'weight' => 121);
        $this->loadModules['EDITABLE']              = array('module' => 'extensions/livedata/js/live_data.js,extensions/editable/js/editable.js,'.
                                                            'extensions/editable/css/editable.css', 'weight' => 120);

        $this->loadModules['EDITOR']                = array('module' => 'js/editor.js, css/_editor.css', 'weight' => 117);
        $this->loadModules['FILE_EDITOR']           = array('module' => 'js/file_editor.js, css/_file_editor.css', 'weight' => 115);

        $this->loadModules['PANELS']                = array('module' => 'js/panels.js, css/_panels.css', 'weight' => 110);

        $this->loadModules['QUICKVIEW']     	    = array('module' => 'js/quickview.js', 'weight' => 92);

        $this->loadModules['POPUPS']                = array('module' => 'third-party/jquery.event.ue/jquery.event.ue.min.js,' .
                                                            'third-party/javascript-md5/md5.min.js, '.
                                                            'js/popup.js, css/_popup.css', 'weight' => 86);

        $this->loadModules['TOOLTIPS']              = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'js/tooltips.js, css/tooltips.css', 'weight' => 84);

        $this->loadModules['TOOLTIPSTER' ]          = array('module' => 'third-party/tooltipster/css/tooltipster.bundle.min.css,'.
                                                                        'third-party/tooltipster/js/tooltipster.bundle.min.js', 'weight' => 83);

        $this->loadModules['QTIP' ]                 = array('module' => 'third-party/qtip/jquery.qtip.min.css,'.
                                                                        'third-party/qtip/jquery.qtip.min.js', 'weight' => 82);

        $this->loadModules['MAC_KEYS']              = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']              = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 71);
        $this->loadModules['HAMMERJQ']              = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['PANZOOM']               = array('module' => 'third-party/panzoom/jquery.panzoom.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES']            = array('module' => 'third-party/datatables/datatables.min.js,'.
                                                                        'third-party/datatables/datatables.min.css', 'weight' => 50);

        $this->loadModules['PAGED_POLYFILL']        = array('module' => 'third-party/paged.polyfill/paged.polyfill.min.js', 'weight' => 46);
        $this->loadModules['ZOOM_TARGET']           = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['PAGE_SWITCHER']         = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']                = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);
        $this->loadModules['IFRAME_RESIZER']        = array('module' => 'third-party/iframe-resizer/iframeResizer.contentWindow.min.js', 'weight' => 19);
        $this->loadModules['USER_ADMIN']            = array('module' => 'js/user_admin.js, css/_user_admin.css', 'weight' => 5);



        // elementes that shall be loaded when corresponding classes are found anywhere in the page:
        //   elements: can be any of cssFiles, css, js, jq etc.
        $this->classBasedModules = [
            'panels_widget' => ['modules' => 'PANELS'],
            'zoomTarget' => ['jsFiles' => 'ZOOM_TARGET'],
        ];

        $this->getConfigValues();

        if  (getUrlArgStatic('debug')) {
            $this->debug_enableDevMode = true;
        }
        if ($this->debug_enableDevMode && file_exists(DEV_MODE_CONFIG_FILE)) {
            $this->getConfigValues(DEV_MODE_CONFIG_FILE);
        }


        // userConfigurableSettingsAndDefaults will be needed if ?config arg was used, so keep it
        if (!getUrlArg('config')) {
            unset($this->userConfigurableSettingsAndDefaults);
        }
        if (!$this->site_defaultStyling) {
            $this->site_loadStyleSheets = [];
        }
        return $this;
    } // __construct



    private function getConfigValues($append = false)
    {
        global $lizzy;

        if (!$append) {
            $configValues = getYamlFile($this->configFile);
        } else {
            $configValues = getYamlFile($append);
        }

        $overridableSettings = array_keys($this->userConfigurableSettingsAndDefaults);
        foreach ($overridableSettings as $key) {
            if (isset($configValues[$key])) {
                $defaultValue = $this->userConfigurableSettingsAndDefaults[$key][0];
                $val = $configValues[$key];

                if (stripos($key, 'Path') !== false) {
                    if (($key !== 'site_dataPath') &&
                        ($key !== 'site_onairDataPath') &&
                        ($key !== 'site_devDataPathPattern')) { // site_dataPath and site_onairDataPath are the only exception allowed to use ../
                        $val = preg_replace('|/\.\.+|', '', $val);  // disallow ../
                        $val = fixPath(str_replace('/', '', $val));
                    }
                } elseif (stripos($key, 'File') !== false) {
                    $val = str_replace('/', '', $val);
                }

                // make sure it gets the right type:
                if (is_bool($defaultValue)) {
                    $this->$key = (bool)$val;

                } elseif (is_int($defaultValue)) {
                    $this->$key = intval( $val );

                } elseif (is_string($defaultValue)) {
                    if ($val === 'true') {
                        $this->$key = true;
                    } elseif ($val === 'true') {
                        $this->$key = false;
                    } else {
                        $this->$key = (string)$val;
                    }

                } elseif (is_array($defaultValue)) {
                    if (is_array($val)) {
                        $this->$key = array_merge($defaultValue, $val);
                    } else {
                        $this->$key = explode(',', str_replace(' ', '', (string)$val));
                    }

                } else {
                    $this->$key = $val;
                }

            } elseif (!$append) {
                $this->$key = $this->userConfigurableSettingsAndDefaults[$key][0];
            }
        }

        foreach ($configValues as $key => $val) {
            if (strpos($key, 'my_') === 0) {
                $this->$key = $val;
                $lizzy[$key] = $val;
            }
        }

        // === fix some values:

        if ($this->path_logPath === '1/') {
            $this->path_logPath = LOGS_PATH;
        }

        if ($append) {
            return;
        }

        if (!$this->site_supportedLanguages) {
            fatalError('Error: no value(s) defined for config item "site_supportedLanguages".');
        }
        $this->site_multiLanguageSupport = true;
        $this->site_supportedLanguages = str_replace(' ', '', $this->site_supportedLanguages );
        $supportedLanguages = explode(',', $this->site_supportedLanguages);
        $n = ($supportedLanguages) ? sizeof($supportedLanguages) : 0;
        if ($n === 1) {
            $this->site_multiLanguageSupport = false;
        }
        $this->site_defaultLanguage = $supportedLanguages[0];
        $this->lang = $this->site_defaultLanguage;


        if ($this->site_sitemapFile) {
            $sitemapFile = $this->configPath . $this->site_sitemapFile;

            if (file_exists($sitemapFile)) {
                $this->site_sitemapFile = $sitemapFile;
            } else {
                $this->site_sitemapFile = false;
            }
        }

        if ($this->determineIsLocalhost()) {
            if (($lc = getUrlArgStatic('localHost')) !== null) {
                $localHost = $lc;
            } else {
                $localHost = true;
            }
        } else {
            $localHost = false;
            setStaticVariable('localHost', false);
        }

        $this->isLocalhost = $this->localHost = $localHost;
    } // getConfigValues



    public function determineIsLocalhost()
    {
        $serverName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
        if (($serverName === 'localhost') || ($remoteAddress === '::1')) {
            // url-arg 'localhost=false' may override result:
            if (getUrlArg('localhost') === false) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    } // determineIsLocalhost



    public function getConfigInfo()
    {
        return $this->userConfigurableSettingsAndDefaults;
    }



    public function getConfigProperties( $category = '')
    {
        $pattern = $category? $category : '\w+_';
        $array = (array) $this;
        $array = array_filter($array, function () use (&$array, $pattern) {
            $k = key($array);
            next($array);
            return (bool) preg_match("/^$pattern/", $k);
        });
        return $array;
    } // getConfigProperties



    public function setConfigValue($key, $value) {
        if (isset($this->$key)) {
            $this->$key = $value;
        }
    }



    public function getDefaultValue($key) {
        if (isset($this->userConfigurableSettingsAndDefaults[$key][0])) {
            return $this->userConfigurableSettingsAndDefaults[$key][0];
        } else {
            return null;
        }
    }



    public function updateConfigValues($post, $configFile)
    {
        $level = intval(getUrlArg('config', true));
        if (!$level) {
            $level = 1;
        }

        $configItems = $this->getConfigInfo();
        $overridableSettings = array_keys($this->userConfigurableSettingsAndDefaults);
        $out = <<<EOT
# Lizzy Settings:
#   see https://getlizzy.net/site/site_configuration/ for documentation
#--------------------------------------------------------------------------


EOT;
        $out2 = '';

        foreach ($overridableSettings as $key) {
            $defaultValue = $this->userConfigurableSettingsAndDefaults[$key][0];
            $value = $defaultValue;

            if (isset($post[$key])) {
                if (is_bool($defaultValue)) {
                    $value = 'true';
                    $this->$key = true;
                } elseif (is_int($defaultValue)) {
                    $value = intval($post[$key]);
                    $this->$key = $value;
                } else {
                    $value = trim($post[$key], ', ');
                    $this->$key = $value;
                    $value = "'$value'";
                }

                if ($defaultValue !== $this->$key) {
                    $out .= "$key: $value\n";
                }

            } elseif ($defaultValue === true) {
                if ($configItems[$key][2] <= $level) {     // skip elements with lower priority than requested
                    $out .= "$key: false\n";
                    $this->$key = false;
                }
            } elseif ($key === 'admin_autoAdminOnLocalhost') {
                $out .= "$key: false\n";
            }
            $out2 .= $this->getConfigLine($key, $value, $defaultValue);
        }
        $out .= "\n\n\n__END__\n#=== List of available configuration items ===========================\n\n";
        $out .= $out2;
        file_put_contents($configFile, $out);
    }



    private function getConfigLine($key, $value, $default)
    {
        if (is_bool($value)) {
            $value = ($value) ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = var_r($value, false, true);
        } else {
            $value = (string)$value;
        }
        if (is_bool($default)) {
            $default = ($default) ? 'true' : 'false';
        } elseif (is_array($default)) {
            $default = var_r($default, false, true);
        } else {
            $default = (string)$default;
        }
        $out = str_pad("$key: ''", 50)."# default=$default\n";
        return $out;
    } // getConfigLine



    public function renderConfigOverlay()
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
        $url = $GLOBALS['lizzy']['pageUrl'];

        if (isset($_POST) && $_POST) {
            $this->updateConfigValues( $_POST, $this->configFile );
        }


        $configItems = $this->getConfigInfo();
        ksort($configItems);
        $out = "<h1>Lizzy Config-Items and their Purpose:</h1>\n";
        $out .= "<p>Settings stored in file <code>{$this->configFile}</code>.<br/>\n";
        $out .= "&rarr; Default values in (), values deviating from defaults are marked <span class='lzy-config-viewer-hl'>red</span>)</p>\n";
        $out .= "<p class='lzy-config-select'>Select: <a href='$url?config=1'$level1Class>Essential</a> ".
            "| <a href='$url?config=2'$level2Class>Common</a> | <a href='$url?config=3'$level3Class>All</a> ".
            "| <a href='$url?config=raw'$level3Class>raw</a></p>\n";
        $out .= "  <form class='lzy-config-form' action='$url?config=$level' method='post'>\n";
        $out .= "    <input class='lzy-button' type='submit' value='{{ lzy-config-save }}'>";

        $i = 1;
        foreach ($configItems as $key => $rec) {
            if ($rec[2] > $level) {     // skip elements with lower priority than requested
                continue;
            }
            $currValue = $this->$key;
            $displayValue = $currValue;
            $defaultValue = $this->getDefaultValue($key);
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
                $inputType = 'comment';
            }

            $comment = $rec[1];

            $id = translateToIdentifier($key).$i++;

            if ($inputType === 'comment') {
                $inputField = "<span id='$id' style='width: 5em;display: inline-block;'></span>";
            } else {
                $inputField = "<input id='$id' name='$key' type='$inputType' value='$inputValue'$checked />";
            }
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
        $configItems = $this->getConfigInfo();
        ksort($configItems);
        foreach ($configItems as $item => $rec) {
            if (is_bool($rec[0])) {
                $val = $rec[0]? 'true' : 'false';

            } elseif (is_array($rec[0])) {
                $str = str_pad("$item:", 45, ' ') . "# {$rec[1]}\n";
                foreach ($rec[0] as $k => $v) {
                    $str .= "    '$k': false,\n";
                }
                $out .= $str;
                continue;

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
        $this->lzy->page->addJq("$('#raw-config-text').selText();");
        return "<pre id='raw-config-text'>$out\n\n</pre>";
    }

} // Defaults
