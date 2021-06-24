<?php
/*
 * Sample Service Task
 *      Creates a zipped backup of all files in ``pages/``.
 *
 * To enable:
 *      config/config.yaml  -> admin_enableServiceTasks:true
 * To execute:
 *      ?service=sample-service-task   -> invokes code/@sample-service-task.php
 *                  |
 *                  +--> corresponds to name of php file
 *  -> use a cron job to invoke on a scheduled basis
*/

$basename = basename( __FILE__ );
mylog("ServiceTask [onRequestGetArg] '$basename' executed");


function executeService($lzy = null)
{
    mylog("ServiceTask [onRequestGetArg] -> function 'executeService()' executed");
    exit('ok');
}


// for this function to be called, request has to be "?urlarg=backup"
function backup($lzy = null)
{
    $basename = basename( __FILE__ );
    preparePath('.#backups/');
    $ts = timestamp();
    shell_exec("zip -r .#backups/{$ts}_backup.zip pages");
    mylog("ServiceTask [onRequestGetArg] $basename -> function 'backup()' executed");
    exit('ok');
    // Note: service tasks of type 'onRequestGetArg' usually exit immediately,
    // as they typically are used by AJAX requests
}

