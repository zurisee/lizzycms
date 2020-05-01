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
    'admin_enableAccessLink'            => [true, 'Activates one-time-access-link login mechanism.', 3 ],
    'admin_defaultAccessLinkValidyTime' => [900,    'Default Time in seconds during whith an access-link is valid.', 3 ],
    'admin_defaultGuestGroup'           => ['guest', 'Name of default group for self-registration.', 3 ],
    'admin_defaultLoginValidityPeriod'  => [86400, 'Defines how long a user can access the page since the last login.', 3 ],
    'admin_enableDailyUserTask'         => [false, 'If true, looks for "code/user-daily-task.php" and executes it.', 3 ],
    'admin_enableEditing'               => [true, 'Enables online editing', 2 ],
    'admin_enableSelfSignUp'            => [false, 'If true, visitors can create a guest account on their own.', 3 ],
    'admin_useRequestRewrite'           => [true, 'If true, assumes web-server supports request-rewrite (i.e. .htaccess).', 3 ],
    'admin_userAllowSelfAdmin'          => [false, 'If true, user can modify their account after they logged in', 3 ],
    'admin_enableFileManager'           => [true, 'If true, the file-manager (upload, rename, delete) is enabled for privileged users.', 2 ],
    'admin_enableDailyFilePurge'        => [true, 'If true, Lizzy reads '.DAILY_PURGE_FILE.' and deletes all listed files one per day.', 2 ],

    'custom_relatedGitProjects'         => ['', "Git Project(s) to be included in ?getstat command", 3 ],
    'custom_permitServiceCode'          => [false, "Enables the 'service routine' mechanism: run PHP code in '".USER_CODE_PATH."' (filename starting with '@')", 1 ],
    'custom_permitUserCode'             => [false, "Only if true, user-provided code can be executed. And only if located in '".USER_CODE_PATH."'", 1 ],
    'custom_permitUserInitCode'         => [false, "Only if true, user-provided init-code can be executed. And only if located in '".USER_CODE_PATH."'", 1 ],
    'custom_permitUserVarDefs'          => [false, 'Only if true, "_code/user-var-defs.php" will be executed.', 1 ],
    'custom_wrapperTag'                 => ['section', 	'The HTML tag in which MD-files are wrapped (default: section)', 2 ],

    'debug_allowDebugInfo'              => [false, '[false|true] If true, debugging Info can be activated: log in as admin and invoke URL-cmd "?debug"', 2 ],
    'debug_collectBrowserSignatures'    => [false, 'If true, Lizzy records browser signatures of visitors.', 3 ],
    'debug_compileScssWithLineNumbers'  => [false, 'If true, original line numbers are added as comments to compiled CSS."', 1 ],
    'debug_enableDevMode'               => [false, '[false|true] Enables devolepment mode', 1 ],
    'debug_enableDevModeAutoOff'        => [false, '[false|true] If true, devolepment mode is automatically turned of the next morning', 1 ],
    'debug_errorLogging'                => [false, 'Enable or disabling logging.', 1 ],
    'debug_forceBrowserCacheUpdate'     => [false, 'If true, the browser is forced to ignore the cache and reload css and js resources on every time.', 2 ],
    'debug_logClientAccesses'           => [false, 'If true, Lizzy records visits (IP-addresses and browser/os types).', 3 ],
    'debug_showDebugInfo'               => [false, 'If true, debugging info is appended to the page (prerequisite: localhost or logged in as editor/admin)', 1 ],
    'debug_showUndefinedVariables'      => [false, 'If true, all undefined static variables (i.e. obtained from yaml-files) are marked.', 2 ],
    'debug_showVariablesUnreplaced'     => [false, 'If true, all static variables (i.e. obtained from yaml-files) are render as &#123;&#123; name }}.', 2 ],
    'debug_monitorUnusedVariables'      => [false, '[false|true] If true, Lizzy keeps track of variable usage. Initialize tracking with url-arg "?reset"', 2 ],

    'feature_autoConvertLinks'          => [false, 'If true, automatically converts text that looks like links to HTML-links (i.e. &lt;a> tags).', 1 ],
    'feature_autoLoadClassBasedModules' => [true, 'If true, automatically loads modules that are invoked by applying classes, e.g. .editable', 3 ],
    'feature_autoLoadJQuery'            => [true, 'If true, jQuery will be loaded automatically (even if not initiated explicitly by macros)', 3 ],
    'feature_enableScssInPageFolder'    => [false, 'If true, Lizzy checks for .scss files in page folder and compiles them to .css. Alternatively use "enableScssInPageFolder" in Frontmatter of specific pages.', 3 ],
    'feature_enableAllowOrigin'         => ['false', 'Set to "*" or explicitly to a domain to allow other websites to include pages of this site.', 1 ],
    'feature_enableIFrameResizing'      => [false, 'If true, includes js code required by other pages to iFrame-embed this site', 1 ],
    'feature_filterRequestString'       => [false, 'If true, permits only regular text in requests. Special characters will be discarded.', 3 ],
    'feature_frontmatterCssLocalToSection' => [false, 'If true, all CSS rules in Frontmatter will be modified to apply only to the current section (i.e. md-file content).', 2 ],
    'feature_jQueryModule'              => ['JQUERY', 'Specifies the jQuery Version to be loaded: one of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.', 3 ],
    'feature_pageSwitcher'              => [false, 'If true, code will be added to support page switching (by arrow-keys or swipe gestures)', 2 ],
    'feature_lateImgLoading'            => [false, 'If true, enables general use of lazy-loading of images', 2 ],
    'feature_quickview'                 => [true, 'If true, enables automatic Quickview of images', 2 ],
    'feature_ImgDefaultMaxDim'          => ['1600x1200', 'Defines the max dimensions ("WxH") to which Lizzy automatically converts images which it finds in the pages folders.', 3 ],
    'feature_SrcsetDefaultStepSize'     => [300, 'Defines the step size when Lizzy creates srcsets for images.', 3 ],
    'feature_preloadLoginForm'          => [false, 'If true, code for login popup is preloaded and opens without page load.', 3 ],
    'feature_renderTxtFiles'            => [false, 'If true, all .txt files in the pages folder are rendered (in &lt;pre>-tags, i.e. as is). Otherwise they are ignored.', 2 ],
    'feature_screenSizeBreakpoint'      => [480, '[px] Determines the point where Lizzy switches from small to large screen mode.', 1 ],
    'feature_selflinkAvoid'             => [false, 'If true, the nav-link of the current page is replaced with a local page link (to satisfy a accessibility requirement).', 2 ],
    'feature_sitemapFromFolders'        => [false, 'If true, the sitemap will be derived from the folder structure under pages/, rather than the config/sitemap.yaml file.', 3 ],
    'feature_supportLegacyBrowsers'     => [false, 'If true, jQuery 1 is loaded in case of legacy browsers.', 2 ],
    'feature_touchDeviceSupport'        => [true, 'If true, Lizzy supports swipe gestures etc. on touch devices.', 2 ],

    'path_logPath'                      => [LOGS_PATH, '[true|Name] Name of folder to which logging output will be sent. Or "false" for disabling logging.', 3 ],
    'path_pagesPath'                    => ['pages/', 'Name of folder in which all pages reside.', 3 ],
    'path_stylesPath'                   => ['css/', 'Name of folder in which style sheets reside', 3 ],
    'path_userCodePath'                 => [USER_CODE_PATH, 'Name of folder in which user-provided PHP-code must reside.', 3 ],

    'site_compiledStylesFilename'       => ['__styles.css', 'Name of style sheet containing collection of compiled user style sheets', 2 ],
    'site_dataPath'                     => [DATA_PATH, 'Path to data/ folder.', 3 ],
    'site_defaultLocale'                => ['en_US', 'Default local, e.g. "en_US" or "de_CH"', 3 ],
    'site_enableCaching'                => [false, 'If true, Lizzy\'s caching mechanism is activated. (not fully implemented yet)', 3 ],
    'site_extractSelector'              => ['body main', '[selector] Lets an external js-app request an extract of the web-page', 3 ],
    'site_enableRelLinks'               => [true, 'If true, injects "rel links" into header, e.g. "&lt;link rel=\'next\' title=\'Next\' href=\'...\'>"', 3 ],
    'site_allowInsecureConnectionsTo'   => ['192.*', '[domain(s)] Permit login over insecure connections to webhost on stated domain/ip-address.', 1 ],
    'site_pageTemplateFile'             => ['page_template.html', "Name of file that will be used as the template. Must be located in '".CONFIG_PATH."'", 3 ],
    'site_robots'                       => [false, 'If true, Lizzy will add a meta-tag to inform search engines, not to index this site.', 1 ],
    'site_sitemapFile'                  => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchy simply by indenting.', 3 ],
    'site_supportedLanguages'           => ['en', 'Defines which languages will be supported: comma-separated list of language-codes. E.g. "en, de, fr" (first elem => default lang)', 1 ],
    'site_timeZone'                     => ['auto', 'Name of timezone, e.g. "UTC" or "CET". If auto, attempts to set it automatically.', 2 ],
];


    public function __construct($configFile)
    {
        $this->macrosPath               = MACROS_PATH;
        $this->extensionsPath           = EXTENSIONS_PATH;
        $this->configPath               = CONFIG_PATH;
        $this->systemPath               = SYSTEM_PATH;
        $this->systemHttpPath           = '~/'.SYSTEM_PATH;

        $this->userInitCodeFile         = USER_INIT_CODE_FILE;
        $this->cachePath                = CACHE_PATH;
        $this->cacheFileName            = CACHE_FILENAME;
        $this->cachingActive            = false;
        $this->siteIdententation        = MIN_SITEMAP_INDENTATION;
        $this->configFile               = $configFile;


        // values not to be modified by config.yaml file:
        $this->admin_usersFile                   = 'users.yaml';
        $this->class_panels_widget               = 'lzy-panels-widget'; // 'Class-name for Lizzy\'s Panels widget that triggers auto-loading of corresponding modules' ],
        $this->class_editable                    = 'lzy-editable'; // 'Class-name for "Editable Fields" that triggers auto-loading of corresponding modules' ],
        $this->class_zoomTarget                  = 'zoomTarget'; // 'Class-name for "ZoomTarget Elements" that triggers auto-loading of corresponding modules' ],
        $this->custom_variables                  = 'variables*.yaml'; // 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],


        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invocation. The higher the earlier.
        $this->jQueryWeight = 200;
        $this->loadModules['JQUERY']                = array('module' => 'third-party/jquery/jquery-3.5.0.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']               = array('module' => 'third-party/jquery/jquery-3.5.0.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']               = array('module' => 'third-party/jquery/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']              = array('module' => 'third-party/jqueryui/jquery-ui.min.js, '.
                                                            'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']          = array('module' => 'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['MOMENT']                = array('module' => 'third-party/moment/moment.min.js', 'weight' => $this->jQueryWeight + 9);

        $this->loadModules['NORMALIZE_CSS']         = array('module' => 'css/normalize.min.css', 'weight' => 150);
        $this->loadModules['TOUCH_DETECTOR']        = array('module' => 'js/touch_detector.js', 'weight' => 149);

        $this->loadModules['FONTAWESOME_CSS']       = array('module' => 'https://use.fontawesome.com/releases/v5.3.1/css/all.css', 'weight' => 135);

        $this->loadModules['AUXILIARY']             = array('module' => 'js/auxiliary.js', 'weight' => 130);

        $this->loadModules['TABBABLE']              = array('module' => 'third-party/tabbable/jquery.tabbable.min.js', 'weight' => 126);
        $this->loadModules['NAV']                   = array('module' => 'js/nav.js', 'weight' => 125);

        $this->loadModules['EDITABLE']              = array('module' => 'extensions/editable/js/editable.js,'.
                                                            'extensions/editable/css/editable.css', 'weight' => 120);

        $this->loadModules['PANELS']                = array('module' => 'js/panels.js, css/panels.css', 'weight' => 110);

        $this->loadModules['QUICKVIEW']     	    = array('module' => 'js/quickview.js, css/quickview.css', 'weight' => 92);

        $this->loadModules['POPUP']  = // POPUP is synonym for POPUPS
        $this->loadModules['POPUPS']                = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'css/popup.css', 'weight' => 85);

        $this->loadModules['TOOLTIPS']              = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'js/tooltips.js, css/tooltips.css', 'weight' => 84);

        $this->loadModules['TOOLTIPSTER' ]          = array('module' => 'third-party/tooltipster/css/tooltipster.bundle.min.css,'.
                                                                        'third-party/tooltipster/js/tooltipster.bundle.min.js', 'weight' => 83);

        $this->loadModules['QTIP' ]                 = array('module' => 'third-party/qtip/jquery.qtip.min.css,'.
                                                                        'third-party/qtip/jquery.qtip.min.js', 'weight' => 82);

        $this->loadModules['MAC_KEYS']              = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']              = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 70);
        $this->loadModules['HAMMERJQ']              = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['PANZOOM']               = array('module' => 'third-party/panzoom/jquery.panzoom.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES']            = array('module' => 'third-party/datatables/datatables.min.js,'.
                                                                        'third-party/datatables/datatables.min.css', 'weight' => 50);

        $this->loadModules['PAGED_POLYFILL']        = array('module' => 'third-party/paged.polyfill/paged.polyfill.js', 'weight' => 46);
        $this->loadModules['ZOOM_TARGET']           = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['PAGE_SWITCHER']         = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']                = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);
        $this->loadModules['IFRAME_RESIZER']        = array('module' => 'third-party/iframe-resizer/iframeResizer.contentWindow.min.js', 'weight' => 19);
        $this->loadModules['USER_ADMIN']            = array('module' => 'js/user_admin.js, css/user_admin.css', 'weight' => 5);



        // elementes that shall be loaded when corresponding classes are found anywhere in the page:
        //   elements: can be any of cssFiles, css, js, jq etc.
        $this->classBasedModules = [
            'editable' => ['modules' => 'EDITABLE', 'jq' => "\$('.lzy-editable').editable();"],
            'panels_widget' => ['modules' => 'PANELS'],
            'zoomTarget' => ['jsFiles' => 'ZOOM_TARGET'],
        ];

        $this->getConfigValues($configFile);

        if ($this->debug_enableDevMode && file_exists(DEV_MODE_CONFIG_FILE)) {
            $this->getConfigValues(DEV_MODE_CONFIG_FILE, true);
        }


        // userConfigurableSettingsAndDefaults will be needed if ?config arg was used, so keep it
        if (!getUrlArg('config')) {
            unset($this->userConfigurableSettingsAndDefaults);
        }
        return $this;
    } // __construct


    //....................................................
    private function getConfigValues($configFile, $append = false)
    {
        global $globalParams;

        $configValues = getYamlFile($configFile);

        $overridableSettings = array_keys($this->userConfigurableSettingsAndDefaults);
        foreach ($overridableSettings as $key) {
            if (isset($configValues[$key])) {
                $defaultValue = $this->userConfigurableSettingsAndDefaults[$key][0];
                $val = $configValues[$key];
                if (stripos($key, 'Path') !== false) {
                    if ($key !== 'site_dataPath') { // site_dataPath is the only exception that is allowed to use ../
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
                    $this->$key = (string)$val;

                } elseif (is_array($defaultValue)) {
                    $this->$key = explode(',', str_replace(' ', '',$val ));

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
                $globalParams[$key] = $val;
            }
        }

        // fix some values:

        if ($this->path_logPath == '1/') {
            $this->path_logPath = LOGS_PATH;
        }

        if ($append) {
            return;
        }

        if (!$this->site_supportedLanguages) {
            fatalError('Error: no value(s) defined for config item "site_supportedLanguages".');
        }
        $this->site_multiLanguageSupport = true;
        $supportedLanguages = explode(',', str_replace(' ', '', $this->site_supportedLanguages ));
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

        if (isLocalCall()) {
            if (($lc = getUrlArgStatic('localcall')) !== null) {
                $localCall = $lc;
            } else {
                $localCall = true;
            }
        } else {
            $localCall = false;
            setStaticVariable('localcall', false);
        }

        $this->isLocalhost = $this->localCall = $localCall;

    } // getConfigValues


    public function getConfigInfo()
    {
        return $this->userConfigurableSettingsAndDefaults;
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

} // Defaults
