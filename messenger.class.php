<?php

class Messenger
{
    public function __construct($from, $lzy = null)
    {
        $this->from = $from;
        $this->lzy = $lzy;
    }



    //------------------------------------
    public function send($to, $msg, $subject = '')
    {
        if (strpos($to, ':') === false) {
            $channel = 'mail';
        } else {
            if (!preg_match('/^(\w+):(.*)/', $to, $m)) {
                die("Error: illegal value for Enroll() -> To: '$to'.");
            }
            $to = $m[2];
            $channel = strtolower($m[1]);
        }

        switch ($channel) {
            case 'mail':
            case 'email':
                $from = $this->sendEmail($to, $subject, $msg);
                break;
            case 'telegram':
                $from = $this->sendTelegram($to, $msg);
                break;
            case 'slack':
                $from = $this->sendSlack($to, $msg);
                break;
            default:
                die("Error: channel '$channel' unknown to Lizzy's Messenger");
        }
        $time = date('r');
        $log = <<<EOT
---------------------------
Time:    $time
From:    $from
To:      $to
Subject: $subject
Message:
$msg

EOT;

        file_put_contents(LOGS_PATH.'messenger.log', $log, FILE_APPEND);
    } // send




    //------------------------------------
    private function sendEmail($to, $subject, $msg)
    {
        $pattern = '/([\._\'\p{L}\p{M}\p{N}-]+@[\._\p{L}\p{M}\p{N}-]+)/u';
        if (preg_match($pattern, $this->from, $m)) {
            $from = $m[1];
        } elseif (isset($this->lzy->trans)) {
            $from = $this->trans->getVariable('webmaster_email');
        } else {
            $from = 'unknown'; //ToDo
        }
        if (!isLocalCall()) {
            sendMail($to, $from, $subject, $msg);
        } else {
            if (isset($this->lzy->page)) {
                $msg1 = str_replace("\n", '<br>', $msg);
                $this->lzy->page->addMessage($msg1);
            } else {
                echo "Sending E-Mail to $to<br/>";
            }
        }
        return $from;
    } // sendEmail



    //------------------------------------
    private function sendSlack($to, $msg)
    {
        if (fileExists("config/$to.webhook")) {
            $slackWebhook = file_get_contents("config/$to.webhook");
        } else {
            $slackWebhook = $to;
        }
        $from = $this->from;
        if (preg_match('/slack: @? (\w+)/x ', $this->from, $m)) {
            $from = $m[1];
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://hooks.slack.com/services/$slackWebhook");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"text\":\"$msg\"}");

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return $from;
    } // sendSlack



    //------------------------------------
    private function sendTelegram($to, $msg)
    {
        if ($to[0] !== '@') {
            $to = "@$to";
        }
        $from = $this->from;
        if (preg_match('/telegram: @? (\w+)/x ', $this->from, $m)) {
            $from = $m[1];
        }
        $pwd = getcwd();
        $cwd = SYSTEM_PATH.'third-party/madelineProto/';
        if (!is_writable($cwd)) {
            die("Error: macro 'telegram()' requires write access-rights in folder '$cwd/'");
        }
        chdir($cwd);
        include 'madeline.php';
        chdir($pwd);
        preparePath('data/telegram');

        if ($from) {
            if (file_exists($from)) {
                $sessionFile = $from;
            } else {
                $sessionFile = "data/telegram/$from.session.madeline";
            }
        } else {
            $sessionFile = 'data/telegram/session.madeline';
        }

        $MadelineProto = new \danog\MadelineProto\API($sessionFile);
        $MadelineProto->async(true);
        $MadelineProto->loop(function () use ($MadelineProto, $to, $msg) {
            yield $MadelineProto->start();
            yield $MadelineProto->messages->sendMessage(['peer' => $to, 'message' => $msg]);
        });
        return $from;
    } // sendTelegram


} // class LzyMessenger

