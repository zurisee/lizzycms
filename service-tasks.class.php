<?php
/*
 *  Lizzy Service Tasks
 *
*/



define('SCHEDULE_FILE',         CONFIG_PATH.'schedule.yaml');           // schedule instructions
define('SCHEDULE_LAST_RUN',     CACHE_PATH.'/_schedule-last-run.txt');  // time keeping


class ServiceTasks
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->auth = null;
        $this->config = null;
    }


    //....................................................
    public function runServiceTasks($run = 1)
    {
        if ($run === 1) {
            // 1sr run: before Lizzy infrastructure is set up:
            $this->runEarlyServiceTasks();

        } else {
            // 2nd run: Lizzy fully set up:
            $this->auth = $this->lzy->auth;
            $this->config = $this->lzy->config;

            $this->handleEditSaveRequests();
            $this->restoreEdition();  // if user chose to activate a previous edition of a page

            $this->runScheduledTasks();

            $this->runPgRequestTriggeredServiceTask();

            $this->runDailyHousekeepingTask();
        }
    } // runServiceTasks



    //....................................................
    public function purgeAll()
    {
        // Purpose: get rid of ALL files generated by Lizzy, so they will be recreated from scratch:
        $this->purgeRecyleBins();
        $this->clearLogs();
        $this->clearCaches();
        $this->execDailyPurge();
        $this->clearTickets();
        $this->checkInstallation1();
        unset($_SESSION['lizzy']);
        reloadAgent( false, 'Lizzy purged all self generated files' );
    } // purgeAll




    public function resetLizzy()
    {
        // Purpose: get rid of all temporary elements holding state information, e.g. caches, tickets, SESSION-vars
        $this->clearCaches();
        $this->clearTickets();
        $this->clearLogs( true );
        $this->checkInstallation1();
        unset($_SESSION['lizzy']);
        reloadAgent( false, 'All statefull information has been reset: caches, tickets, session-vars.' );
    } // resetLizzy




    // === private methods =============================================
    private function runEarlyServiceTasks()
    {
        // these are tasks that don't rely on the Lizzy infrastructure
        $this->runScheduledTaskAfterInit = false;
        if (isset($_GET['scheduled'])) {
            $res = $this->executeScheduledTasks();
            if ($res === true) {
                exit();
            } elseif ($res) {
                $this->runScheduledTaskAfterInit = true;
            }
        }
    } // runEarlyServiceTasks



    private function runScheduledTasks()
    {
        if ($this->runScheduledTaskAfterInit) {
            if (!$this->config->admin_enableScheduledTasks) {
                die("Attempt to run a scheduled task, but is feature not enabled.<br>To activate, set config -> admin_enableScheduledTasks:true");
            }
            if ($this->executeScheduledTasks(2 )) {
                exit();
            }
        }
    } // runScheduledTasks




    private function runPgRequestTriggeredServiceTask()
     {
         if ($this->config->admin_serviceTasks) {
             $serviceTasksDef = $this->config->admin_serviceTasks;

             // service task trigged every request:
             if (isset($serviceTasksDef['onPageRequest']) && $serviceTasksDef['onPageRequest']) {
                 $this->executeServiceTask($serviceTasksDef['onPageRequest']);
             }

             // service task triggered by configurable request GET arg:
             if (isset($serviceTasksDef['onRequestGetArg']) ) {
                 $urlArg = $serviceTasksDef['onRequestGetArg'];
                 if (strpos($urlArg, ':') !== false) {
                     list($urlArg, $functionToCall) = explodeTrim(':', $urlArg);
                 } else {
                     $functionToCall = 'executeService';
                 }

                 if (isset($_GET[ $urlArg ]) && $_GET[ $urlArg ]) {
                     $file = $_GET[$urlArg];
                     if (isset($_GET[ $urlArg ])) {
                         unset($_GET[$urlArg]);
                     }
                     $this->executeServiceTask($file, $functionToCall);
                 }
             }
         }
     } // runPgRequestTriggeredServiceTask



    private function runDailyHousekeepingTask()
    {
        // Run once per day upon first page request after midnight:
        if (!$this->checkTimeForDailyHousekeeping()) {
            return;
        }
        writeLog("Daily housekeeping run.");

        // daily cleanup round:
        $this->execDailyPurge();

        // if auto-off of dev-mode is enabled:
        if ($this->config->debug_enableDevModeAutoOff) {
            $this->autoOffDevMode();
        }

        $this->checkInstallation2();   // check pages/ -> writable if editing is enabled

        $this->runDailyTask();

        // reset housekeeping flag:
        touch(HOUSEKEEPING_FILE);
    } // runDailyHousekeepingTask




    private function checkTimeForDailyHousekeeping()
    {
        // check housekeeping flag:
        $intervall = $this->config->site_cacheResetIntervall * 3600;
        if (file_exists(HOUSEKEEPING_FILE)) {
            $fileTime = intval(filemtime(HOUSEKEEPING_FILE) / $intervall);
            $today = intval(time() / $intervall);
            if ($fileTime === $today) {    // update once per day
                return false;
            }
        }

        // Beyond this point runs only once per day upon first page request after midnight:
        //  Note: $this->clearCaches() will have been executed already by checkAndRenderCachePage()
        //  so no need to do it again here.

        $this->checkInstallation1();   // check folder structure and writability

        // initialise housekeeping flag:
        preparePath(HOUSEKEEPING_FILE);
        file_put_contents(HOUSEKEEPING_FILE, $intervall);
        chmod(HOUSEKEEPING_FILE, 0770);

        return true;
    } // checkTimeForDailyHousekeeping



    //....................................................
    private function autoOffDevMode()
    {
        if (file_exists(DEV_MODE_CONFIG_FILE)) {
            $commentedFilename = dirname(DEV_MODE_CONFIG_FILE).'/#'.basename(DEV_MODE_CONFIG_FILE);
            rename(DEV_MODE_CONFIG_FILE, $commentedFilename);
            reloadAgent();
        }
    } // autoOffDevMode



    //....................................................
    private function checkInstallation1()
    {
        $writableFolders = ['data/', '.#cache/', '.#logs/'];
        $readOnlyFolders = ['_lizzy/','code/','config/','css/','pages/'];
        $out = '';
        foreach ($writableFolders as $folder) {
            if (!file_exists($folder)) {
                mkdir($folder, MKDIR_MASK2);
            }
            if (!is_writable( $folder )) {
                $out .= "<p>folder not writable: '$folder'</p>\n";
            }
            foreach( getDir($folder.'*') as $file) {
                if (!is_writable( $file )) {
                    $out .= "<p>folder not writable: '$file'</p>\n";
                }
            }
        }

        foreach ($readOnlyFolders as $folder) {
            if (!file_exists($folder)) {
                mkdir($folder, MKDIR_MASK);
            }
            if (!is_readable( $folder )) {
                $out .= "<p>folder not readable: '$folder'</p>\n";
            }

        }
        if ($out) {
            exit( $out );
        }

        // check and fix access rights to img cache folders:
        $dir = getDirDeep('pages/*', true, false, true);
        foreach ($dir as $folder) {
            if (strpos($folder, '/_/') !== false) {
                chmod($folder, 0755);
            }
        }
    } // checkInstallation1




    //....................................................
    private function checkInstallation2()
    {
        $out = '';
        if ($this->config->admin_enableEditing) {
            if (!is_writable( 'pages' )) {
                $out .= "<p>folder not writable: 'pages/'</p>\n";
            }
            foreach(getDirDeep('pages/*') as $file) {
                if (!is_writable( $file )) {
                    $out .= "<p>file or folder not writable: '$file'</p>\n";
                }
            }
        }
        if ($out) {
            exit( $out );
        }
    } // checkInstallation2




    private function executeServiceTask($codeFile, $functionToCall = false)
    {
        $codeFile = ltrim(base_name($codeFile, true), '-');
        if (!$codeFile) {
            return false;
        }
        $codeFile = fileExt($codeFile, true);
        $taskFile = USER_CODE_PATH . "-$codeFile.php";
        if (file_exists( $taskFile )) {
            require_once $taskFile;

            if ($functionToCall && function_exists($functionToCall)) {
                return $functionToCall( $this->lzy );   // may exit sending json data to agent
            }
            return true;
        } else {
            die("Error: service-handler '$taskFile' not found.");
        }
        return false;
    } // executeServiceTask




    private function runDailyTask()
    {
        if (isset($this->config->admin_serviceTasks['daily'])) {
            $dailyTask = $this->config->admin_serviceTasks['daily'];
            if ($dailyTask) {
                $this->executeServiceTask($dailyTask);
            }
        }
    } // runDailyTask




    private function executeScheduledTasks($run = 1 )
    {
        $schedule = getYamlFile(SCHEDULE_FILE);
        $forceRun = getUrlArg('force');

        if ($run === 1) {
            $lastRun = intval(@filemtime(SCHEDULE_LAST_RUN));
            touch(SCHEDULE_LAST_RUN);
            $this->lastScheduledRun = $lastRun;
        } else {
            $lastRun = $this->lastScheduledRun;
        }
        $now = time();
        $nowStr = date('Y-m-d H:i');

        $wdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

        $secondRunRequired = false;
        foreach ($schedule as $scheduleRec) {
            if (($run === 1) && isset($scheduleRec['loadLizzy']) && $scheduleRec['loadLizzy']) {
                $secondRunRequired = true;
                continue;
            }
            $scheduledTime = isset($scheduleRec['time']) ? $scheduleRec['time'] : '****-**-** 08:00';

            // check whether within time window:
            $schedFrom = (isset($scheduleRec['from']) && $scheduleRec['from']) ? strtotime($scheduleRec['from']) : 0;
            if (isset($scheduleRec['till'])) {
                $schedTill = strtotime($scheduleRec['till']);
            } else {
                $schedTill = $now + 100;
                $scheduleRec['till'] = date('Y-m-i', $schedTill);
            }

            // add one day if no time is defined
            if (strpos($scheduleRec['till'], ':') === false) {
                $schedTill += 86400;
            }

            // check whether within given time window:
            if (($now < $schedFrom) || ($now > $schedTill)) {
                continue;
            }

            // check whether it's a weekly task:
            if (preg_match('/(Mo|Tu|We|Th|Fr|Sa|Su)/', $scheduledTime, $m)) {
                $wday = array_search($m[1], $wdays);
                $wdayToday = intval(date('w'));
                if ($wday === $wdayToday) { // send on specified week-day only:
                    $scheduledTime = str_replace($m[1], date('Y-m-d'), $scheduledTime);
                } else {
                    continue;
                }
            }

            // resolve wildcards in time:
            for ($i = 0; $i < strlen($scheduledTime); $i++) {
                if ($scheduledTime[$i] === '*') {
                    $scheduledTime[$i] = $nowStr[$i];
                }
            }

            // now we got the final execution time:
            $scheduledTime = strtotime($scheduledTime);

            // check whether execution time was between last run and this one:
            if ($forceRun || (($scheduledTime > $lastRun) && ($scheduledTime <= $now))) {  // fire now:
                $do = str_replace('@', '', base_name($scheduleRec['do'], false));
                $do = USER_CODE_PATH . "@$do.php";
                if (file_exists($do)) {
                    require_once $do;
                    if (function_exists('executeScheduledTask')) {
                        $this->executeScheduledTask($scheduleRec['args']);
                    }

                } elseif (!sendSimpleNotification($scheduleRec['args'])) { // send msg directly, if msg is defined
                    die("Error: schedule-handler '$do' not found.");
                }
            }
        }
        return $secondRunRequired ? 1 : true;
    } // executeScheduledTasks



    private function sendSimpleNotification($args)
    {
        if (!isset($args['from']) ||
            !isset($args['to']) ||
            !isset($args['msg'])) {
            return false;
        }
        $subject = isset($args['subject']) ? $args['subject'] : '';

        require_once SYSTEM_PATH . 'messenger.class.php';

        $mess = new Messenger($args['from']);
        $mess->send($args['to'], $args['msg'], $subject);
        return true;
    } // sendSimpleNotification





    // === Request Handlers for Page Edit functionality:
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
    } // handleEditSaveRequests



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
                if (preg_match('|^'.PAGES_PATH.'(.*)\.md$|', $filename)) {
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
    } // savePageFile



    //....................................................
    private function renderMD()
    {
        $mdStr = get_post_data('lzy_md', true);
        $mdStr = urldecode($mdStr);

        $md = new LizzyMarkdown();
        $pg = new Page;
        $mdStr = $this->lzy->extractFrontmatter($mdStr, $pg);
        $md->compile($mdStr, $pg);

        $out = $pg->get('content');
        if (getUrlArg('html')) {
            $out = "<pre>\n".htmlentities($out)."\n</pre>\n";
        }
        exit($out);
    } // renderMD



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




    // === Clean Up ======================================
    //....................................................
    private function execDailyPurge()
    {
        if (!$this->config->admin_serviceTasks['dailyFilePurge']) {
            return;
        }
        $purgeDefFile = CONFIG_PATH . $this->config->admin_serviceTasks['dailyFilePurge'];
        if (file_exists($purgeDefFile)) {
            $files0 = file($purgeDefFile);
            $files = [];
            // parse lines, omit blank lines and comments, resolve wildcards
            foreach ($files0 as $i => $file) {
                $file = trim($file);
                if (!$file || ($file[0] === '#')) {
                    continue;
                }
                if ($file === '__END__') {
                    break;
                }
                if (strpos($file, '*') !== false) {
                    $f = glob($file);
                    $files = array_merge($files, $f);
                } else {
                    $files[] = $file;
                }
            }

            // now do the actual purging of files:
            foreach ($files as $file) {
                if (file_exists($file)) {
                    if (is_dir($file)) {
                        rrmdir($file);
                    } else {
                        unlink($file);
                    }
                }
            }
        }
    } // execDailyPurge



    //....................................................
    private function clearMdCache()
    {
        // clear all 'pages/*/.#page-cache.dat'
        $dir = getDirDeep(PAGES_PATH, true);
        foreach ($dir as $folder) {
            $filename = $folder.CACHE_FILENAME;
            if (file_exists($filename)) {
                unlink($filename);
            }
            $filename = $folder.CACHE_DEPENDENCY_FILE;
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
    } // clearMdCache





    //....................................................
    private function clearCaches()
    {
        rrmdir( CACHE_PATH );                          // clear main cache folder
        mkdir(CACHE_PATH, MKDIR_MASK);
        // $this->clearMdCache();                           // clear MD caches
    } // clearCaches




    //....................................................
    private function purgeRecyleBins()
    {
        $pageFolder = PAGES_PATH;
        $recycleBinFolderName = rtrim(RECYCLE_BIN,'/');
        $isLocalhost = isLocalCall();

        // purge in page folders:
        $pageFolders = getDirDeep($pageFolder, true, false, true);
        foreach ($pageFolders as $item) {
            $basename = basename($item);
            if (($basename === $recycleBinFolderName) ||    // it's a recycle bin...
                ($isLocalhost && ($basename === '_'))) {     // or a image cache (but only on localhost):
                rrmdir($item);
            }
        }

        // purge global recycle bin:
        $sysRecycleBin = resolvePath(SYSTEM_RECYCLE_BIN_PATH);
        if (file_exists($sysRecycleBin)) {
            rrmdir($sysRecycleBin);
        }
    } // purgeRecyleBins




    //....................................................
    private function clearLogs( $outputBufferOnly = false)
    {
        if ($outputBufferOnly) {
            unlink(LOGS_PATH . 'output-buffer.txt');
            return;
        }
        rrmdir(LOGS_PATH);
        mkdir(LOGS_PATH, MKDIR_MASK);
    } // clearLogs




    //....................................................
    private function clearTickets()
    {
        rrmdir(DEFAULT_TICKETS_PATH);
    } // clearLogs
} // class ServiceTasks




