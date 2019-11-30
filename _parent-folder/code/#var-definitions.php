<?php
/*
 * user-var-defs.php
 *
 * Purpose: let's you define variables as an array of key-value tuples.
 *
 * Has no access to Lizzy's objects.
 *
  * Note: to use this feature it must be enabled in config/config.yaml: custom_permitUserVarDefs: true
 */

if (file_exists('config/vars.json')) {
    $variables = json_decode(file_get_contents('config/vars.json'), true);
} else {
    $variables = [
        'myvar' => "PHP-Code '<code>user-var-defs.php</code>' says <em>Hi Lizzy</em>!",
    ];
}

writeLog("define-variables.php executed.");

return $variables;