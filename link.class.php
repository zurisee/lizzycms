<?php

define('COMMON_FILE_EXTENSIONS', '.jpg.jpeg.png.gif.tif.tiff.pdf.txt.doc.docx.rtf.html.htm.css.js.');

class CreateLink
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
    }

    //----------------------------------------------------------
    public function render($args)
    {
        $this->href = $args['href'];
        $this->text = $args['text'];
        $this->type = $args['type'];
        $this->id = $args['id'];
        $this->class = $args['class'];
        $this->title = $args['title'];
        $this->target = $args['target'];
        $this->subject = $args['subject'];
        $this->body = $args['body'];
        $this->option = $args['option'];

        if ($this->title) {
            $this->title = " title='{$this->title}'";
        }
        $this->id = $this->id ? " id='{$this->id}'" : '';
        $hiddenText = '';

        $this->proto = '';
        if (preg_match('|^https?:|', $this->href, $m)) {
            $this->proto = 'http';
        } elseif (preg_match('/^(\w{3,6}):(.*)/', $this->href, $m)) {
            $proto = strtolower($m[1]);
            if (stripos(',mail,mailto,sms,tel,gsm,geo,slack,pdf,', ",$proto,") !== false) {
                $this->href = $m[2];
                $this->proto = $proto;
            }
        }

        $ext = fileExt($this->href);
        if (strtolower($ext) === 'pdf') {
            $this->proto = 'pdf';
        } elseif (stripos(COMMON_FILE_EXTENSIONS, ".$ext.") !== false) {
            $this->proto = 'file';
        }

        if ($this->isTypeLink('mail')) {
            $hiddenText = $this->renderMailLink();

        } elseif ($this->isTypeLink('sms')) {
            $this->renderSmsLink();

        } elseif ($this->isTypeLink('tel')) {
            $this->renderTelLink();

        } elseif ($this->isTypeLink('gsm')) {
            $this->renderTelLink();

        } elseif ($this->isTypeLink('geo')) {
            $this->renderGeoLink();

        } elseif ($this->isTypeLink('slack')) {
            $this->renderSlackLink();

        } elseif ($this->isTypeLink('pdf')) {
            $this->renderPdfLink();

        } elseif ($this->proto === 'file') {
            $this->renderFileLink();

        } else {
            $hiddenText = $this->renderRegularLink();
        }


        if ($this->target) {
            $this->target = ($this->target === 'newwin')? '_blank': $this->target;
            $this->target = " target='{$this->target}' rel='noopener noreferrer'";
            // see: https://developers.google.com/web/tools/lighthouse/audits/noopener
            if (!$this->class) {
                $this->class = 'lzy-newwin_link';
            }

        } elseif (stripos($this->type, 'extern') !== false) {
            $this->target = " target='_blank' rel='noopener noreferrer'";
            $this->class = ($this->class) ? "{$this->class} lzy-external_link" : 'lzy-external_link';
            $this->title = $this->title ? $this->title : " title='{{ opens in new win }}'";
            if (!$this->class) {
                $this->class = 'lzy-external_link';
            }
        }

        if (stripos($this->option, 'download') !== false) {
            $this->target .= ' download';
            $this->class .= ' lzy-download';
        }
        $this->class = ($this->class) ? " class='lzy-link {$this->class}'" : '';
        if (preg_match('/^ ([^\?&]*) (.*)/x', $this->href, $m)) {     // remove blanks from href
            $this->href = str_replace(' ', '', $m[1]).str_replace(' ', '%20', $m[2]);
        }

        // now assemble code:
        $str = "<a href='{$this->href}' {$this->id}{$this->class}{$this->title}{$this->target}>{$this->text}$hiddenText</a>";

        return $str;
    } // render



    private function isTypeLink($type)
    {
        $type = ($type === 'mailto') ? 'mail' : $type;
        return (($this->proto === $type) || (stripos($this->type, $type) !== false));
    }



    private function renderMailLink()
    {
        $this->class = ($this->class) ? "{$this->class} lzy-mail_link mail_link" : 'lzy-mail_link mail_link';
        $this->title = ($this->title) ? $this->title : " title='{{ opens mail app }}'";
        $this->body = str_replace(' ', '%20', $this->body);
        $this->body = str_replace(['\n', "\n"], '%0A', $this->body);
        if ($this->subject) {
            $this->subject = str_replace(' ', '%20', $this->subject);
            $arg = "?subject={$this->subject}";
            if ($this->body) {
                $arg .= "&body={$this->body}";
            }
        } elseif ($this->body) {
            $arg = "?body={$this->body}";
        }
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        } else {
            $hiddenText = "<span class='print_only'> [$this->href]</span>";
        }
        $this->href .= $arg;
        return $hiddenText;
    }




    private function renderSmsLink()
    {
        $this->class = ($this->class) ? "$this->class lzy-sms_link sms_link" : 'lzy-sms_link sms_link';
        $this->title = ($this->title) ? $this->title : " title='{{ opens messaging app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        if ($this->body) {
            $this->href .= "?&body={$this->body}";
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
        if (preg_match('|^(\w+:) ([^/]{2} .*)|x', $this->href, $m)) {
            $this->href = "{$m[1]}//{$m[2]}";
        }
        return;
    }




    private function renderTelLink()
    {
        if (stripos($this->type, 'gsm') !== false) {
            $this->class = ($this->class) ? "$this->class lzy-gsm_link" : 'lzy-gsm_link';
        } else {
            $this->class = ($this->class) ? "$this->class lzy-tel_link tel_link" : 'lzy-tel_link tel_link';
        }
        $this->title = ($this->title) ? $this->title : " title='{{ opens telephone app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
        if (preg_match('|^(\w+:) ([^/]{2} .*)|x', $this->href, $m)) {
            $this->href = "{$m[1]}//{$m[2]}";
        }
        return;
    }




    private function renderGeoLink()
    {
        $this->class = ($this->class) ? "$this->class lzy-geo_link geo_link" : 'lzy-geo_link geo_link';
        $this->title = ($this->title) ? $this->title : " title='{{ opens map app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
    }




    private function renderSlackLink()
    {
        $this->class = ($this->class) ? "$this->class lzy-slack_link slack_link" : 'lzy-slack_link slack_link';
        $this->title = ($this->title) ? $this->title : " title='{{ opens slack app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
    }




    private function renderPdfLink()
    {
        $this->class = ($this->class) ? "$this->class lzy-pdf_link pdf_link" : 'lzy-pdf_link pdf_link';
        $this->title = ($this->title) ? $this->title : " title='{{ opens PDF in new window }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^[./~]* ([^\?\&]*) .*|x', "$1", $this->href);
            $this->text = base_name($this->href);
        }
        if (stripos($this->option, 'abs') !== false) {
            $this->href = resolvePath($this->href, true, true, true);
        } else {
            $this->href = resolvePath($this->href, true, true);
        }
        if ($this->target) {
            $this->target = ($this->target === 'newwin') ? '_blank' : $this->target;
            $this->target = " target='{$this->target}' rel='noopener noreferrer'";
            // see: https://developers.google.com/web/tools/lighthouse/audits/noopener
        }
    }



    private function renderFileLink()
    {
        $c1 = $this->href[0];
        list($elem1) = explode('/', preg_replace('|^https?://|i', '', $this->href));
        if ((stripos($this->href, 'http') !== 0)) {
            $ext = fileExt($elem1);
            if ((stripos($this->type, 'extern') !== false) ||
                ($ext && stripos(COMMON_FILE_EXTENSIONS, ".$ext.") === false)) { // contains '.xy' but it's a TLD (not a file-ext)
                $this->href = 'HTTPS://'.$this->href;
            }

            if (($c1 !== '~') && ($c1 !== '.')) {   // unqualified link -> local file
                if ((stripos($this->option, 'abs') !== false)) {
                    $this->href = resolvePath($this->href, true, true, true);
                } else {
                    $this->href = resolvePath($this->href, true, true);
                }
            } else {
                if ((stripos($this->option, 'abs') !== false)) {
                    $this->href = resolvePath($this->href, true, true, true);
                }
            }
            if (!$this->text) {
                $this->text = base_name($this->href);
            }
        } else {
            if (!$this->text) {
                $this->text = $this->href;
            }
        }
    }



    private function renderRegularLink()
    {
        $c1 = $this->href[0];
        if ((stripos($this->href, 'http') !== 0) && ($c1 !== '~') && ($c1 !== '.')) {   // unqualified link -> check whether it corresponds to a page
            $rec = $this->lzy->siteStructure->findSiteElem($this->href, true, true);
            if ($rec) {
                $this->href = '~/'.$rec['folder'];
                $this->text = $rec['name'];
            }
        }


        // prepareLinkText:
        if (!$this->text) {
            $text = $this->href;
            $text = preg_replace('|^ HTTPS?://|xi', '', $text);
            $text = preg_replace('|^ [./~]*|xi', '', $text);
            $text = preg_replace('|[?&#] .*|xi', '', $text);

            $this->text = $text;
        }

        // prepareHref:
        if ((stripos($this->type, 'intern') === false)) {
            list($elem1) = explode('/', preg_replace('|^https?://|i', '', $this->href));
            $ext = fileExt($elem1);
            if (stripos($this->href, 'http') !== 0) {   // no HTTP(S) in href:
                if ((stripos($this->type, 'extern') !== false) ||
                    ($ext && stripos(COMMON_FILE_EXTENSIONS, ".$ext.") === false)) { // contains '.xy' but it's a TLD (not a file-ext)
                    $this->href = 'HTTPS://'.$this->href;
                }
            }

            if (stripos($this->href, 'http') !== 0) {  // still no HTTP(S) in href:
                if ((stripos($this->option, 'abs') !== false)) {
                    $this->href = resolvePath($this->href, true, true, true);
                }
            }
        } else {
            if (stripos($this->href, 'http') !== 0) {  // still no HTTP(S) in href:
                if ((stripos($this->option, 'abs') !== false)) {
                    $this->href = resolvePath($this->href, true, true, true);
                }
            }
        }
        $href = resolvePath($this->href, true, true, true);
        $hiddenText = "<span class='print_only'> [$href]</span>";

        return $hiddenText;
    } // renderRegularLink



    private function renderAbsoluteUrl( $href )
    {
        $href = resolvePath($href, true, true, true);
        return $href;
    }

} // CreateLink
