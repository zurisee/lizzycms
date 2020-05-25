<?php

// See: https://docs.phpsandbox.org/2.0/classes/PHPSandbox.PHPSandbox.html#source-view

$blackList = ["phpinfo", "file_get_contents", "exec", "passthru", "system", "shell_exec", "`", "popen", "proc_open", "pcntl_exec", "eval", "assert", "create_function", "include", "include_once", "require", "require_once", "ReflectionFunction", "posix_mkfifo", "posix_getlogin", "posix_ttyname", "getenv", "get_current_user", "proc_get_status", "get_cfg_var", "disk_free_space", "disk_total_space", "diskfreespace", "getcwd", "getlastmo", "getmygid", "getmyinode", "getmypid", "getmyuid", "extract", "parse_str", "putenv", "ini_set", "mail", "header", "proc_nice", "proc_terminate", "proc_close", "pfsockopen", "fsockopen", "apache_child_terminate", "posix_kill", "posix_mkfifo", "posix_setpgid", "posix_setsid", "posix_setuid", "fopen", "tmpfile", "bzopen", "gzopen", "SplFileObject", "chgrp", "chmod", "chown", "copy", "file_put_contents", "lchgrp", "lchown", "link", "mkdir", "move_uploaded_file", "rename", "rmdir", "symlink", "tempnam", "touch", "unlink", "imagepng", "imagewbmp", "image2wbmp", "imagejpeg", "imagexbm", "imagegif", "imagegd", "imagegd2", "iptcembed", "ftp_get", "ftp_nb_get", "file_exists", "file_get_contents", "file", "fileatime", "filectime", "filegroup", "fileinode", "filemtime", "fileowner", "fileperms", "filesize", "filetype", "glob", "is_dir", "is_executable", "is_file", "is_link", "is_readable", "is_uploaded_file", "is_writable", "is_writeable", "linkinfo", "lstat", "parse_ini_file", "pathinfo", "readfile", "readlink", "realpath", "stat", "gzfile", "readgzfile", "getimagesize", "imagecreatefromgif", "imagecreatefromjpeg", "imagecreatefrompng", "imagecreatefromwbmp", "imagecreatefromxbm", "imagecreatefromxpm", "ftp_put", "ftp_nb_put", "exif_read_data", "read_exif_data", "exif_thumbnail", "exif_imagetype", "hash_file", "hash_hmac_file", "hash_update_file", "md5_file", "sha1_file", "highlight_file", "show_source", "php_strip_whitespace", "get_meta_tags", "set_time_limit", "call_user_func", "call_user_func_array", "php_execute_raw", 'Composer\\Autoload\\includeFile'];

class MySandbox
{
    public function execute($phpFile, $configPath, $vars = null)
    {
        $phpCode = getFile($phpFile, true);

        $this->sandbox = new PHPSandbox\PHPSandbox;

        $this->setup($configPath, $vars);

        try {
            $this->sandbox->execute($phpCode);
        } catch(Exception $e) {
            $phpCode = htmlspecialchars($phpCode);
            fatalError("Error while executing user code '$phpFile' in sandbox: <br>".$e->getMessage()."<pre>$phpCode</pre>", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        return true;
    } // execute




    private function setup($configPath, $vars)
    {
    // To be on the safe side, we use a minimum set of blacklisted functions
    // if that's too restricted still, users can skip using the sandbox altogether
        global $blackList;

        $sandbox_disallowed_functions = getYamlFile($configPath.'sandbox_disallowed_functions.yaml');
        if (is_array($sandbox_disallowed_functions)) {
            $sandbox_allowed_functions = array_merge($sandbox_disallowed_functions, $blackList);
            $this->sandbox->blacklistFunc($sandbox_disallowed_functions);
        } else {
            $this->sandbox->blacklistFunc($blackList);
        }


        $this->sandbox_allowed_functions = getYamlFile($configPath.'sandbox_allowed_functions.yaml');
        if (is_array($this->sandbox_allowed_functions)) {
            $this->sandbox_allowed_functions = array_merge($this->sandbox_allowed_functions, $GLOBALS['WHITELIST_FUNCS']);
            $this->sandbox->whitelistFunc($this->sandbox_allowed_functions);
        }


        $this->sandbox_available_variables = getYamlFile($configPath.'sandbox_available_variables.yaml');
        if ($this->sandbox_available_variables) {
            $vars = array();
            foreach ($this->sandbox_available_variables as $varName) {
                if (isset($$varName)) {
                    $vars[$varName] = $$varName;
                }
            }
        }
        $this->sandbox->defineVars($vars);


        $this->sandbox->allow_functions = true;
        $this->sandbox->allow_closures = true;
        $this->sandbox->allow_constants = true;
        $this->sandbox->allow_aliases = false;
        $this->sandbox->allow_interfaces = true;
        $this->sandbox->allow_casting = true;
        $this->sandbox->allow_classes = true;
        $this->sandbox->error_level = false;
        //rewrite preg_replace function to override attempts to use PREG_REPLACE_EVAL
        $this->sandbox->define_func('preg_replace', function ($pattern, $replacement, $subject, $limit = -1, &$count = null) {
            if (is_array($pattern)) {
                foreach ($pattern as $_pattern) {
                    if (strtolower(substr($_pattern, -1)) === 'e') {
                        throw new Exception("Can not use PREG_REPLACE_EVAL!");
                    }
                }
            } else {
                if (strtolower(substr($pattern, -1)) === 'e') {
                    throw new Exception("Can not use PREG_REPLACE_EVAL!");
                }
            }
            return preg_replace($pattern, $replacement, $subject, $limit, $count);
        });
    }
} // MySandbox
