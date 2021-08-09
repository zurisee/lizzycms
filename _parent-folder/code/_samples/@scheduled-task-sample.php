<?php

/*
 *  Sample Scheduled Task
 *
 *  How it works:
 *      An external process (cron job) invokes the app on a regular basis.
 *
 *
 *  Enable & Configure:
 *      config/config.yaml -> admin_enableScheduledTasks: true
 *      config/schedule.yaml
 *      config/slack.webhook
 *
 *  Invoke:
 *      ?scheduled
 *
 *  Schedule Format:
 * >>>>>>>>>>>>>>>>
 -
    time: '****-**-** 08:00'                            # each '*' will be replaced with value of current time
#    time: 'We 08:00'                                   # alternative: specify a day of week
    from: '2020-06-01 00:00'                            # optional
    till: '2020-07-23 00:00'                            # optional
    do: scheduled-task-sample                           # script to run -> 'code/@<do>.php'
    loadLizzy: true
    args:                                               # -> arguments supplied to script 'do'
        subject: '[{{ site_title }}] Test-Notification' # example applying variable
        msg: 'Current state of enrollment: \n\n'        #
        to: 'name@domain.net'                           # -> see messenger.class for further options
        dataFile: pages/home/enroll.yaml                # example here: compile summary of enrollments
 * <<<<<<<<<<<<<<<<<
*/

require_once SYSTEM_PATH.'datastorage2.class.php';


function executeScheduledTask($lzy, $args)
{
    $msg = isset($args['msg']) ? $args['msg'] : '';
    $msg = str_replace("\\n", "\n", $msg);
    $msg = $lzy->trans->translate( $msg );

    $subject = isset($args['subject']) ? $args['subject'] : "[{{ site_title }}] Notification";
    $subject = $lzy->trans->translate( $subject );

    $from = isset($args['from']) ? $args['from'] : $lzy->trans->translateVariable('webmaster_email', true);
    $to = isset($args['to']) ? $args['to'] : '';

    if (!$to) {
        die("Error in scheduled task: 'to' missing.");
    }

    $msg .= compileSummary($args);

    require_once SYSTEM_PATH . 'messenger.class.php';

    $mess = new Messenger($from);
    $mess->send($to, $msg, $subject);

} // runService




function compileSummary($args)
{
    $msg = '';
    $dataFile = isset($args['dataFile']) ? $args['dataFile'] : '';
    if ($dataFile) {
        if (!file_exists( $dataFile )) {
            die("Error in scheduled task: file '$dataFile' not found.");
        }
        $ds = new DataStorage2(['dataFile' => $dataFile, 'lockDB' => true]);
        $enrollData = $ds->read();
        if (!$enrollData) {
            die("Error in scheduled task: task-list is empty.");
        }

        foreach ($enrollData as $listName => $list) {
            $n = sizeof($list) - 1;
            $nRequired = isset($list['_']) ? $list['_'] : '?';
            $listName = str_pad("$listName: ", 24, '.');
            $msg .= "$listName $n von $nRequired\n";
        }

        $msg .= "\n";
        $msg = str_replace('\\n', "\n", $msg);
    }
    return $msg;
}