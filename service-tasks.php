<?php
/*
 *  Lizzy Service Tasks
 *
 *  -> Daily housekeeping
 *  -> purging temp folders (caches, recycle bins, logs etc)
 *  -> service tasks launched by cron (-> invoke as ?service)
 *      -> reads data/schedule.yaml for instructions
*/



define('SCHEDULE_FILE',         DATA_PATH.'schedule.yaml');             // schedule instructions
define('SCHEDULE_LAST_RUN',     CACHE_PATH.'/_schedule-last-run.txt');  // time keeping



//....................................................
function runServiceTasks($lzy, $run = 1)
{
    if ($run === 1) {
        $lzy->runScheduledTaskAfterInit = false;
        if (isset($_GET['service'])) {
             $res = executeScheduledTasks($lzy);
            if ($res === true) {
                exit();
            } elseif ($res) {
                $lzy->runScheduledTaskAfterInit = true;
            }
        }

        if (file_exists(HOUSEKEEPING_FILE)) {
            $fileTime = intval(filemtime(HOUSEKEEPING_FILE) / 86400);
            $today = intval(time() / 86400);
            if (($fileTime) === $today) {    // update once per day
                $lzy->housekeeping = false;
                return;
            }
        }
        if (!file_exists(CACHE_PATH)) {
            mkdir(CACHE_PATH, MKDIR_MASK);
        }
        touch(HOUSEKEEPING_FILE);
        chmod(HOUSEKEEPING_FILE, 0770);

        checkInstallation1();   // check folder structure and writability

        $lzy->housekeeping = true;
        clearCaches($lzy);

    } else { // 2nd run:
        if ($lzy->runScheduledTaskAfterInit) {
            if (executeScheduledTasks($lzy, 2 )) {
                exit();
            }
        }

        if ($lzy->housekeeping) {
            writeLog("Daily housekeeping run.");

            // if auto-off of dev-mode is enabled:
            if ($lzy->config->debug_enableDevModeAutoOff) {
                autoOffDevMode();
            }

            checkInstallation2($lzy);   // check pages/ -> writable if editing is enabled

            clearCaches($lzy, true);

            if ($lzy->config->admin_enableDailyUserTask) {
                if (file_exists(USER_DAILY_CODE_FILE)) {
                    require(USER_DAILY_CODE_FILE);
                }
            }
            touch(HOUSEKEEPING_FILE);
            chmod(HOUSEKEEPING_FILE, 0770);
        }
    }
} // runServiceTasks



//....................................................
function autoOffDevMode()
{
    if (file_exists(DEV_MODE_CONFIG_FILE)) {
        $commentedFilename = dirname(DEV_MODE_CONFIG_FILE).'/#'.basename(DEV_MODE_CONFIG_FILE);
        rename(DEV_MODE_CONFIG_FILE, $commentedFilename);
        reloadAgent();
    }
} // autoOffDevMode



//....................................................
function checkInstallation1()
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
    return;

    print( trim(shell_exec('whoami')).':'.trim(shell_exec('groups'))."<br>\n");

    $all = array_merge($writableFolders, $readOnlyFolders);
    foreach ($all as $folder) {
        $rec = posix_getpwuid(fileowner($folder));
        $name = $rec['name'];
        $rec = posix_getgrgid(filegroup($filename));
        $group = $rec['name'];
        print("$folder: $name:$group<br>\n");
    }
    exit;
} // checkInstallation1




//....................................................
function checkInstallation2($lzy)
{
    $out = '';
    if ($lzy->config->admin_enableEditing) {
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




//....................................................
function clearLogs()
{
    $dir = glob(LOGS_PATH . '*');
    foreach($dir as $file) {
        unlink($file);
    }
} // clearLogs



//....................................................
function clearCache($lzy)
{
    $dir = glob($lzy->config->cachePath.'*');
    foreach($dir as $file) {
        unlink($file);
    }

    // clear all 'pages/*/.#page-cache.dat'
    $dir = getDirDeep($lzy->config->path_pagesPath, true);
    foreach ($dir as $folder) {
        $filename = $folder.$lzy->config->cacheFileName;
        if (file_exists($filename)) {
            unlink($filename);
        }
        $filename = $folder.CACHE_DEPENDENCY_FILE;
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
} // clearCache





//....................................................
function clearCaches($lzy, $secondRun = null)
{
    if (!$secondRun) {
        if (file_exists(ERROR_LOG_ARCHIVE)) {   // clear error log
            unlink(ERROR_LOG_ARCHIVE);
        }
        if ($secondRun === null) {
            return;
        }
    }
    clearCache($lzy);                            // clear page caches
//    $lzy->siteStructure->clearCache();           // clear siteStructure cache
} // clearCaches




//....................................................
function purgeRecyleBins($lzy)
{
    $pageFolder = $lzy->config->path_pagesPath;
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




function executeScheduledTasks($lzy, $run = 1 )
{
    $schedule = getYamlFile(SCHEDULE_FILE);

    if ($run === 1) {
        $lastRun = intval(@filemtime(SCHEDULE_LAST_RUN));
        touch(SCHEDULE_LAST_RUN);
        $lzy->lastScheduledRun = $lastRun;
    } else {
        $lastRun = $lzy->lastScheduledRun;
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
        $scheduledTime = $scheduleRec['time'];

        // check whether within time window:
        $schedFrom = strtotime($scheduleRec['from']);
        $schedTill = strtotime($scheduleRec['till']);
        // add one day if no time is defined
        if (strpos($scheduleRec['till'], ':') === false) {
            $schedTill += 86400;
        }
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
        if (($scheduledTime > $lastRun) && ($scheduledTime <= $now)) {  // fire now:
            $do = str_replace('@', '', base_name($scheduleRec['do'], false));
            $do = USER_CODE_PATH . "@$do.php";
            if (file_exists($do)) {
                require_once $do ;
                runService($lzy, $scheduleRec['args']);

            } elseif (!sendSimpleNotification($scheduleRec['args'])) { // send msg directly, if msg is defined
                die("Error: schedule-handler '$do' not found.");
            }
        }
    }
    return $secondRunRequired ? 1 : true;
} // executeScheduledTasks



function sendSimpleNotification($args)
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

