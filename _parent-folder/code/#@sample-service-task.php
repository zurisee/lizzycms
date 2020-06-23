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

function executeService($lzy = null)
{
    preparePath('.#backups/');
    shell_exec('zip -r .#backups/backup.zip pages');
    exit('ok');
}

