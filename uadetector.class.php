<?php

require_once SYSTEM_PATH.'third-party/browser-detector/BrowserDetection.php';


class UaDetector
{
    public function __construct($collectBrowserSignatures = false)
    {
        $this->debug_collectBrowserSignatures = $collectBrowserSignatures;
        $this->uaStr = $_SERVER['HTTP_USER_AGENT'];

        $this->ua = new Wolfcast\BrowserDetection();
        $this->browserName = $this->ua->getName();
        $this->browserVersion = $this->ua->getVersion();
        $this->osName = $this->ua->getPlatform();
        if ($this->osName === 'iOS') {
            if (strpos($this->uaStr, 'iPhone') !== false) {
                $this->osName .= '/iPhone';
            } elseif (strpos($this->uaStr, 'iPad') !== false) {
                $this->osName .= '/iPad';
            }
            $this->osVersion = $this->ua->getPlatformVersion(true);

        } elseif (($this->osName === 'Macintosh') && (preg_match('/Mac OS X ([\w\.]*)/', $this->uaStr, $m))) {
            $this->osVersion = str_replace('_', '.',$m[1]);

        } elseif (($this->osName === 'Windows') && (preg_match('/Windows\s+NT\s+([\w\.]*)/', $this->uaStr, $m))) {
            $this->osVersion = str_replace('_', '.',$m[1]);

        } else {
            $this->osVersion = $this->ua->getPlatformVersion(true);
        }
        $this->browserSignature = "{$this->browserName} {$this->browserVersion} on {$this->osName} {$this->osVersion}";
        $this->storeBrowserSignature();
    }




    public function get()
    {
        return $this->browserSignature;
    } // get



    public function isLegacyBrowser()
    {
        $browserName = $this->browserName;
        $browserVersion = $this->browserVersion;
        $osName = $this->osName;
        $osVersion = $this->osVersion;

        $res = false;
        if ((strpos($browserName, 'IE') !== false) || (strpos($browserName, 'Internet Explorer') !== false)) {
            $res = $this->ua->compareVersions($browserVersion, '10');

        } elseif (strpos($browserName, 'Safari') !== false) {
            $res = $this->ua->compareVersions($browserVersion, '5.1');
        }
        if ($res === false) {
            if (strpos($osName, 'Windows') !== false) {
                $res = $this->ua->compareVersions($osVersion, '7');

            } elseif (strpos($osName, 'Android') !== false) {
                $res = $this->ua->compareVersions($osVersion, '5.1');

            } elseif (strpos($osName, 'Macintosh') !== false) {
                $res = $this->ua->compareVersions($osVersion, '10.8');
            }
        }
        return ($res < 0);
    } // isLegacyBrowser



    private function isOlderOSThan($product, $version)
    {
        if ($this->osName !== $product) {
            return false;
        }
        $thisVer = explode('.', $this->osVersion.'.0.0.0.0');
        foreach (explode('.', $version) as $i => $e) {
            if (intval($e) < intval($thisVer[$i])) {
                return false;
            }
        }
        return true;
    }



    private function storeBrowserSignature()
    {
        if (!$this->debug_collectBrowserSignatures) {
            return;
        }
        $signature = "{$this->browserSignature}\t\t({$this->uaStr})";
        if ($this->isLegacyBrowser()) {
            $signature .= "\tlegacy browser";
        }
        if (file_exists(BROWSER_SIGNATURES_FILE)) {
            $signatures = file(BROWSER_SIGNATURES_FILE);
            foreach ($signatures as $sig) {
                if (strpos($sig, $signature) !== false) {
                    return; // already recorded
                }
            }
        }
        if (!file_exists(BROWSER_SIGNATURES_FILE)) {
            preparePath(BROWSER_SIGNATURES_FILE);
            touch(BROWSER_SIGNATURES_FILE);
        }
        file_put_contents(BROWSER_SIGNATURES_FILE, $signature . "\n", FILE_APPEND);

        if (strpos($this->browserSignature, 'N/A') !== false) {
            file_put_contents(UNKNOWN_BROWSER_SIGNATURES_FILE, $signature."\n", FILE_APPEND);
        }
    } // storeBrowserSignature
} // UaDetector

