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
        $this->type = isset($args['type'])? $args['type']: 'href';
        $this->id = isset($args['id'])? $args['id']: '';
        $this->class = isset($args['class'])? $args['class']: '';
        $this->title = isset($args['title'])? $args['title']: '';
        $this->target = isset($args['target'])? $args['target']: '';
        $this->subject = isset($args['subject'])? $args['subject']: '';
        $this->body = isset($args['body'])? $args['body']: '';
        $this->option = isset($args['option'])? $args['option']: '';
        $this->relativeToPage = isset($args['relativeToPage'])? $args['relativeToPage']: false;

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

        // work-around: MD compiler replaces _x_ with <em>x</em> -- which is no good in URLs -> reverse:
        $this->href = str_replace(['<em>', '</em>'], '_',$this->href);

        $ext = fileExt($this->href);
        if (strtolower($ext) === 'pdf') {
            $this->proto = 'pdf';
        } elseif (stripos(COMMON_FILE_EXTENSIONS, ".$ext.") !== false) {
            $this->proto = 'file';
        }

        $this->proto = ($this->proto === 'mailto') ? 'mail' : $this->proto;
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
            $this->class = trim("$this->class lzy-newwin_link");

        } elseif (stripos($this->type, 'extern') !== false) {
            $this->target = " target='_blank' rel='noopener noreferrer'";
            $this->title = $this->title ? $this->title : " title='{{ opens in new win }}'";
            $this->class = trim("$this->class lzy-external_link");
        }

        if (stripos($this->option, 'download') !== false) {
            $this->target .= ' download target="_blank"';
            $this->class = trim("$this->class lzy-download_link");
            $this->href = resolvePath($this->href, true, true);
            $this->text = basename($this->text);
            $hiddenText = '';
        }
        $class = trim("lzy-link $this->class");
        $class = " class='$class'";
        if (preg_match('/^ ([^\?&]*) (.*)/x', $this->href, $m)) {     // remove blanks from href
            $this->href = str_replace(' ', '', $m[1]).str_replace(' ', '%20', $m[2]);
        }

        // now assemble code:
        $str = "<a href='{$this->href}' {$this->id}{$class}{$this->title}{$this->target}>{$this->text}$hiddenText</a>";

        return $str;
    } // render



    private function isTypeLink($type)
    {
        return (($this->proto === $type) || (stripos($this->type, $type) !== false));
    }



    private function renderMailLink()
    {
        $this->class = trim("lzy-mail_link mail_link $this->class");
        $this->title = ($this->title) ? $this->title : " title='{{ opens mail app }}'";
        $this->body = str_replace(' ', '%20', $this->body);
        $this->body = str_replace(['\n', "\n"], '%0A', $this->body);
        $arg = '';
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
            $hiddenText = '';
        } else {
            $hiddenText = "<span class='print_only'> [$this->href]</span>";
        }
        $this->href = "mailto:{$this->href}$arg";
        return $hiddenText;
    }




    private function renderSmsLink()
    {
        $this->class = trim("lzy-sms_link $this->class");
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
        $this->href = "sms://{$this->href}";
    }




    private function renderTelLink()
    {
        if (stripos($this->type, 'gsm') !== false) {
            $this->class = trim("lzy-gsm_link $this->class");

        } else {
            $this->class = trim("lzy-tel_link $this->class");
        }
        $this->title = ($this->title) ? $this->title : " title='{{ opens telephone app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
        if (preg_match('|^(\w+:) ([^/]{2} .*)|x', $this->href, $m)) {
            $this->href = "{$m[1]}//{$m[2]}";
        }
        $this->href = "tel://{$this->href}";
    }




    private function renderGeoLink()
    {
        $this->class = trim("lzy-geo_link $this->class");
        $this->title = ($this->title) ? $this->title : " title='{{ opens map app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
        $this->href = "geo://{$this->href}";
    }




    private function renderSlackLink()
    {
        $this->class = trim("lzy-slack_link $this->class");
        $this->title = ($this->title) ? $this->title : " title='{{ opens slack app }}'";
        if (!$this->text) {
            $this->text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $this->href);
        }
        $this->target = " target='_blank' rel='noopener noreferrer'";
        $this->href = "slack://{$this->href}";
    }




    private function renderPdfLink()
    {
        $this->class = trim("lzy-pdf_link pdf_link $this->class");
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
        $this->class = trim("lzy-page-link $this->class");
        $c1 = $this->href[0];
        $rec = false;

        $href = $this->href;
        $hash = '';
        // remove target (#id) and url-args (?x) if present:
        if (preg_match('/^ (.*?) ([#?].*)/x', $href, $m)) {
            $hash = $m[2];
            $href = $m[1];
        }

        // unqualified link -> check whether it corresponds to a page:
        if ((stripos($href, 'http') !== 0) && ($c1 !== '~') && ($c1 !== '.')) {
            $rec = $this->lzy->siteStructure->findSiteElem($href, true, true);
            if ($rec) {
                $this->href = '~/'.$rec['folder'].$hash;
                if (!$this->text) {
                    $this->text = $rec['name'];
                }
            }
        }

        // if not found in previous step, make it relative-to-page (if requested):
        if (!$rec && $this->relativeToPage) {
            $this->href = makePathRelativeToPage($this->href);
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
            if (!$this->target && $this->lzy->config->feature_externalLinksInNewWin) {    // open
                $this->target = 'newwin';
            }
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
                $this->class = trim("lzy-local-link $this->class");
            }
        } else {
            if (stripos($this->href, 'http') !== 0) {  // still no HTTP(S) in href:
                if ((stripos($this->option, 'abs') !== false)) {
                    $this->href = resolvePath($this->href, true, true, true);
                }
                $this->class = trim("lzy-local-link $this->class");
            }
        }

        if ((stripos($this->option, 'noprint') === false) && (stripos($this->text, 'http') !== 0)) {
            $href = resolvePath($this->href, true, true, true);
            $hiddenText = "<span class='print_only'> [$href]</span>";
        } else {
            $hiddenText = '';
        }

        return $hiddenText;
    } // renderRegularLink



    private function renderAbsoluteUrl( $href )
    {
        $href = resolvePath($href, true, true, true);
        return $href;
    }

} // CreateLink
