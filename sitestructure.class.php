<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *  SiteStructure
*/

class SiteStructure
{
    private $list = false;
    private $tree = false;
    public  $currPage;
    public  $prevPage = '';
    public  $nextPage = '';
    public  $currPageRec = false;
    public  $config;
    private $noContent = false;
    private $recTemplate = [
        'name' => '',
        'level' => 0,
        'folder' => '',
        'isCurrPage' => false,
        'listInx' => 0,
        'urlExt' => '',
        'active' => false,
        'noContent' => false,
        'hide!' => false,
        'restricted!' => false,
        'actualFolder' => '',
        'alias' => false,
        'hasChildren' => false,
        'parentInx' => NULL,
    ];


    //....................................................
    public function __construct($lzy, $currPage = false)
    {
        $this->lzy = $lzy;
        $this->nextPage = false;
        $this->prevPage = false;

        $this->config = $config = $lzy->config;

        $this->site_sitemapFile = $config->site_sitemapFile;
        $this->currPage = $currPage;
        if (!$this->config->feature_sitemapFromFolders && !file_exists($this->site_sitemapFile)) {
            $this->currPageRec = array('folder' => '', 'name' => '');
            $this->list = false;
            return;
        }
        $this->site_enableCaching = $this->config->site_enableCaching;
        $this->cachePath = $this->config->cachePath; //'.#cache/';
        $this->cacheFile = $this->cachePath . '_siteStructure.dat';


		if (true) {
//		if (!$this->readCache()) {
            if ($config->feature_sitemapFromFolders) {
                $this->list = $this->getSitemapFromFolders();

            } else {
                $this->list = $this->parseSitemapDef();
            }
            $this->tree = $this->parseList();

            $this->propagateProperties();   // properties marked with ! -> propagate into branches, e.g. 'lang!'='fr'

            $this->writeCache();
        }

        $currNr = $this->findSiteElem($this->currPage);
        if ($currNr !== false) {
            $this->list[$currNr]['isCurrPage'] = true;
            $this->currPageRec = &$this->list[$currNr];
            $this->markParents();

        } else {    // requested page not found:
            $this->currPage = '_unknown/';
            $this->currPageRec = $this->recTemplate;
            $this->currPageRec['isCurrPage'] = true;
            http_response_code(404);
            return;
        }

        if ($currNr < (sizeof($this->list) - 1)) {
            $i = 1;
            while ($this->list[$currNr + $i]['hide!'] && (($currNr + $i) < (sizeof($this->list) - 1))) {
                $i++;
            }
            $this->nextPage = $this->list[$currNr + $i]['folder'];
        }
        if ($currNr > 0) {
            $i = 1;
            $j = $currNr - $i;
            while (($j > 0) && ($this->list[$j]['hide!'] || $this->list[$j]['noContent'])) {
                $i++;
                $j = $currNr - $i;
            }
            if ($j >= 0) {
                $this->prevPage = $this->list[$j]['folder'];
            } else {
                $this->prevPage = false;
            }
        }
    } // __construct


    //....................................................
    public function getSiteList()
    {
        return $this->list;
    } // getSiteList


    //....................................................
    public function getSiteTree()
    {
        return $this->tree;
    } // getSiteTree


    //....................................................
    // first pass: parse config/sitemap.txt and assemble $this->list :
    private function parseSitemapDef()
    {
        $list = [];
        $lines = $this->getSitemapLines();
        $lastLevel = 0;
        $indent = '';
        foreach ($lines as $i => $line) {
            $rec = $this->recTemplate;

            // extract name / visibleName and split from rest of line:
            // check whether page name in {{ }} -> to be translated later:
            if (preg_match('/^ (\s*) {{ (.*?) }} \s* :? \s*  (.*) /x', $line, $m)) {
                $indent = $m[1];
                $name = trim($m[2]);
                $visibleName = "{{ $name }} ";
                $rest = $m[3];

            // -> plain name:
            } elseif (preg_match('/^ (\s*) ([^:{]*)  \s* :? \s*  (.*) /x', $line, $m)) {
                $indent = $m[1];
                $name = trim($m[2]);
                $visibleName = $name;
                $rest = $m[3];
            }
            $rec['name'] = $visibleName;

            // obtain arguments:
            $args = '';
            if (preg_match('/ { \s* (.*?) \s* } /x', $rest, $m)) {
                $args = $m[1];
            }

            // determine level:
            $rec['level'] = $level = $this->determineLevel( $indent, $lastLevel, $line );
            $lastLevel = $level;

            // folder:
            $rec['folder'] = translateToIdentifier($name, true).'/';

            // parse args:
            if ($args) {
                $args = parseArgumentStr($args, ',', true);
                if (is_array($args)) {
                    foreach($args as $key => $value) {
                        if ($key === 'folder') {
                            $key = 'specified_folder'; // explicit folder -> later override computed folder
                            $value = fixPath( str_replace(['~/', '~page/'], '', $value) );
                        } elseif (stripos(',showThis,alias,goto,', ",$key,") !== false) {
                            $value = fixPath( str_replace(['~/', '~page/'], '', $value) );
                        }
                        $rec[strtolower($key)] = $value;
                    }
                }
            }

            // determine visibility of element:
            $rec = $this->determineVisability( $rec );
            if ($rec === false) {
                continue; // do not add to list
            }

            $rec = $this->handleSpecificOptions( $rec );

            // add to list:
            $list[] = $rec;

        } // /foreach
        return $list;
    } // parseSitemapDef



    private function getSitemapLines()
    {
        $rawLines = file($this->site_sitemapFile);
        $lines = [];
        foreach($rawLines as $line) {
            if (strpos($line, '__END__') === 0) {
                break;
            }

            $line = preg_replace('/(?<! [\\\:] ) (\/\/|\#) .* /x', '', $line);
            $line = rtrim($line);
            if ($line) {
                $lines[] = $line;
            }
        }
        return $lines;
    } // getSitemapLines



    private function determineLevel( $indent, $lastLevel, $line )
    {
        // determine level:
        // idententation -> 4 blanks count as one tab = level
        if (strlen($indent) === 0) {
            $level = 0;
        } else {
            // convert every 4 blanks to a tab, then remove all remaining blanks => level
            $indent = str_replace(str_repeat(' ', $this->config->siteIdententation), "\t", $indent);
            $indent = str_replace(' ', '', $indent);
            $level = strlen($indent);
        }
        if (($level - $lastLevel) > 1) {
            writeLogStr("Error in sitemap.txt: indentation on line $line (level: $level / lastLevel: $lastLevel)", true);
            $level = $lastLevel + 1;
        }
        return $level;
    } // determineLevel



    private function handleSpecificOptions( $rec )
    {
        // showThis option:
        if (isset($rec['showthis']) && $rec['showthis']) {
            $rec['actualFolder'] = $rec['showthis'];
        } else {
            $rec['actualFolder'] = $rec['folder'];
        }

        // hide option -> always propagate:
        if (isset($rec['hide'])) {
            $rec['hide!'] = $rec['hide'];
            unset($rec['hide']);
        }

        // restricted option -> always propagate:
        if (isset($rec['restricted'])) {
            $rec['restricted!'] = $rec['restricted'];
            unset($rec['restricted']);
        }
        return $rec;
    } // handleSpecificOptions



	//....................................................
    // second pass: derive hierarchical structure $this->tree from $this->list :
	private function parseList()
	{
		$this->listInx = 0;
		$this->listSize = sizeof($this->list);
		$tree = $this->_parseList('', 0, null);
		return $tree;
	} // parseList



    //....................................................
    private function _parseList($path, $parentLevel, $parentInx)
    {
        $listInx = &$this->listInx;
        $treeInx = 0;         // $treeInx -> counter within level
        $tree = [];
        while ($listInx < $this->listSize) {
            // create tree elem as ref to list elem:
            $tree[ $treeInx ] = &$this->list[$listInx];
            $currTreeElem = &$this->list[$listInx];

            if (isset($currTreeElem['specified_folder'])) {
                $path1 = $currTreeElem['specified_folder'];

            } else {
                $path1 = "$path{$currTreeElem['folder']}";
            }
            $currLevel = $currTreeElem['level'];
            $currInx = $listInx;

            $currTreeElem['parentInx'] = $parentInx;
            $currTreeElem['folder'] = $path1;
            $currTreeElem['actualFolder'] = $path . $currTreeElem['actualFolder'];

            // check whether page folder contains .md file(s):
            $mdFiles = getDir(PAGES_PATH."{$currTreeElem['actualFolder']}*.md");
            $currTreeElem['noContent'] = (sizeof($mdFiles) === 0);

            $treeInx++;
            $listInx++;

            $nextLevel = @$this->list[ $listInx]['level'];
            $up = ($nextLevel < $currLevel);
            $down = ($nextLevel > $currLevel);
            if ($up) {
                return $tree;

            } elseif ($down) {
                $currTreeElem['hasChildren'] = true;

                $subtree = $this->_parseList($path1, $currLevel, $currInx);

                $currTreeElem = array_merge($currTreeElem, $subtree);
                $nextLevel = @$this->list[$listInx]['level'];
                $up = ($nextLevel < $currLevel);
                if ($up) {
                    return $tree;
                }
            }
        }
        return $tree;
    } // _parseList




    //....................................................
    private function getSitemapFromFolders() {
        $list = array();
        $i = 0;
        return $this->_traverseDir(PAGES_PATH,$i, 0, $list);
    } // getSitemapFromFolders




    //....................................................
    private function _traverseDir($path, &$i, $level, &$list)
    {
        if ($dh = opendir($path)) {
            while (($f = readdir($dh)) !== false) {
                $rec = [];
                $file = $path . $f;
                if (!is_dir($file) || (strpos('._#', $f[0]) !== false)) {
                    continue;
                }

                $name = preg_replace('/^\d\d_/', '', $f);
                $rec['name'] = str_replace('_', ' ', $name);
                $rec['level'] = $level;
                if (!$list) {
                    $rec['folder'] = '~/./';
                    $rec['showthis'] = $f . '/';
                } else {
                    $rec['folder'] = $f . '/';
                }
                $rec['isCurrPage'] = false;
                $rec['listInx'] = $i;
                $rec['urlExt'] = '';
                $rec['active'] = false;
                $rec['hide!'] = false;

                $i++;
                $list[] = $rec;
                $this->_traverseDir("$path$f/", $i, $level + 1, $list);
            };
        };
        return $list;
    } // _traverseDir



    private function determineVisability( $rec )
    {
        // Hide: hide always propagating, therefore we need 'hide!' element
        if (isset($rec['hide'])) {
            $rec['hide!'] = $rec['hide'];
            unset($rec['hide']);
        }


        // case: page only visible to selected users:
        $hideArg = $rec['hide!'];

        if (!is_bool( $hideArg )) {
            // detect leading inverter (non or not or !):
            $neg = false;
            if (preg_match('/^((non|not|\!)\-?)/i', $hideArg, $m)) {
                $neg = true;
                $hideArg = substr($hideArg, strlen($m[1]));
            }

            if (preg_match('/privileged/i', $hideArg)) {
                $hideArg = $this->config->isPrivileged;
            } elseif (preg_match('/loggedin/i', $hideArg)) {
                $hideArg = $_SESSION['lizzy']['user'];
            } elseif (($hideArg !== 'true') && !is_bool($hideArg)) {        // if not 'true', it's interpreted as a group
                $hideArg = $this->lzy->auth->checkGroupMembership($hideArg);
            }
            if ($neg) {
                $hideArg = !$hideArg;
            }
            $rec['hide!'] = $hideArg;
        }

        // arg 'omit':
        if (isset($rec['omit'])) {
            $omitArg = $rec['omit'];
            if ($omitArg === true) {
                return false;
            }

            // detect leading inverter (non or not or !):
            $neg = false;
            if (preg_match('/^((non|not|\!)\-?)/i', $omitArg, $m)) {
                $neg = true;
                $omitArg = substr($omitArg, strlen($m[1]));
            }

            if (preg_match('/privileged/i', $omitArg)) {
                $omitArg = $this->config->isPrivileged;
            } elseif (preg_match('/loggedin/i', $omitArg)) {
                $omitArg = $_SESSION['lizzy']['user'];
            } elseif (($omitArg !== 'true') && !is_bool($omitArg)) {        // if not 'true', it's interpreted as a group
                $omitArg = $this->lzy->auth->checkGroupMembership($omitArg);
            }
            if ($neg) {
                $omitArg = !$omitArg;
            }
            if ($omitArg) {
                return false;
            }
        }


        // check time dependencies:
        if (isset($rec['omittill']) || isset($rec['availablefrom'])) {
            $s = isset($rec['omittill'])? $rec['omittill']: $rec['availablefrom'];
            $t = strtotime( $s );
            if (time() < $t) {
                return false;
            }
        }
        if (isset($rec['omitfrom']) || isset($rec['availabletill'])) {
            $s = isset($rec['omitfrom'])? $rec['omitfrom']: $rec['availabletill'];
            $t = strtotime( $s );
            if (time() > $t) {
                return false;
            }
        }

        if (isset($rec['showfrom'])) {
            $t = strtotime($rec['showfrom']);
            $rec['hide!'] |= (time() < $t);
        }
        if (isset($rec['showtill'])) {
            $t = strtotime($rec['showtill']);
            $rec['hide!'] |= (time() > $t);
        }
        if (isset($rec['hidefrom'])) {
            $t = strtotime($rec['hidefrom']);
            $rec['hide!'] |= (time() > $t);
        }
        if (isset($rec['hidetill'])) {
            $t = strtotime($rec['hidetill']);
            $rec['hide!'] |= (time() < $t);
        }
        return $rec;
    } // determineVisability




    private function propagateProperties()
    {
        $this->_propagateProperties($this->tree);
    } // propagateProperties




    private function _propagateProperties(&$subtree, $toPropagate = [])
    {
        $toPropagate1 = $toPropagate;
        if (isset($subtree['listInx'])) {
            // apply propagating option from further up:
            foreach ($toPropagate1 as $k => $v) {
                $subtree[$k] = $v;
            }

            // check for new propagating options:
            if ($subtree !== null) {
                foreach ($subtree as $k => $v) {
                    if (strpos($k, '!') !== false) {    // item to propagate found
                        if (strpos('hide!,restricted!', $k) !== false) {
                            if (!$v) {
                                continue;
                            }
                        } else {
                            $k = str_replace('!', '', $k);
                        }
                        $toPropagate1[$k] = $v;
                        $subtree[$k] = $v;
                    }
                }
            }
        }

        foreach ($subtree as $key => $rec) {
            if (is_int($key)) {
                $this->_propagateProperties($subtree[ $key ], $toPropagate1);
            }
        }
    } // _propagateProperties




	//....................................................
	private function markParents()
	{
		$this->currPageRec['active'] = true;
		$rec = &$this->currPageRec;
		while ($rec['parentInx'] !== null) {
			$rec = &$this->list[$rec['parentInx']];
			$rec['active'] = true;
		}
	} // markParents



	//....................................................
	public function findSiteElem($str, $returnRec = false, $allowNameToSearch = false)
	{
	    if (($str === '/') || ($str === './')) {
            $str = '';
        } elseif ((strlen($str) > 0) && ($str[0] === '/')) {
	        $str = substr($str, 1);
        } elseif ((strlen($str) > 0) && (substr($str,0,2) === '~/')) {
            $str = substr($str, 2);
        }
        if (!$this->list) {
        	return false;
        }

		$list = $this->list;
		$found = false;
		$foundLevel = 0;
		foreach($list as $key => $elem) {
			if ($found || ($str === $elem['folder'])) {
				$folder = PAGES_PATH.$elem['folder'];
				if (isset($elem['showthis']) && $elem['showthis']) {	// no 'skip empty folder trick' in case of showthis
                    $found = true;
                    break;
				}

				// case: falling through empty page-folders and hitting the bottom:
				if ($found && ($foundLevel >= $elem['level'])) {
				    $key = max(0, $key - 1);
				    break;
                }

                if (!$found && !file_exists($folder)) { // if folder doesen't exist, let it be created later in handleMissingFolder()
                    $found = true;
                    break;
                }
				$dir = getDir(PAGES_PATH.$elem['folder'].'*');	// check whether folder is empty, if so, move to the next non-empty one
				$nFiles = sizeof(array_filter($dir, function($f) {
                    return ((substr($f, -3) === '.md') || (substr($f, -5) === '.link') || (substr($f, -5) === '.html'));
				}));
				if ($nFiles > 0) {
				    $found = true;
				    break;
				} else {
					$found = true;
                    $this->noContent = true;
                    $foundLevel = $elem['level'];
				}

			} elseif (isset($elem['alias']) && ($str === $elem['alias'])) {
                $found = true;
                break;

			} elseif ($allowNameToSearch) {
			    if (strtolower($elem['name']) === strtolower($str)) {
                    $found = true;
                    break;
                }
            }
		}
		if ($returnRec && $found) {
		    return $list[$key];
        } elseif ($found) {
            return $key;
        } else {
		    return false;
        }
	} // findSiteElem




    public function hasActiveAncestor($elem)
    {
        if ($elem['active']) {
            return true;
        }
        while ($elem['parentInx'] !== null) {
            $elem = $this->list[$elem['parentInx']];
            if ($elem['active']) {
                return true;
            }
        }

        return false;
    } // hasActiveAncestor


	
	private function writeCache()
	{
		if ($this->site_enableCaching) {
			if (!file_exists($this->cachePath)) {
				mkdir($this->cachePath, MKDIR_MASK2);
			}
			$cache = serialize($this);
			file_put_contents($this->cacheFile, $cache);
		}
	} // writeCache



	
	private function readCache()
	{   // when cache can be used:
        // - config>site_enableCaching: true
        // - cached siteStructure exists
        // - cached siteStructure is newer than config/sitemap.txt
        $skipElements = ['currPage', 'config', 'site_sitemapFile', 'site_enableCaching'];
		if ($this->site_enableCaching) {
			if (!file_exists($this->cacheFile)) {
				return false;
			}
			$cacheTime = filemtime($this->cacheFile);
			if ($cacheTime > filemtime($this->site_sitemapFile)) {
				$site = unserialize(file_get_contents($this->cacheFile));
				foreach ($site as $key => $value) {
					if (!in_array($key, $skipElements)) {
						$this->$key = $value;
					}
				}
				return true;
			}
		}
		return false;
	} // readCache




    public function clearCache()
    {
        if (isset($this->cacheFile) && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    } // clearCache

	public function getNumberOfPages()
	{
		return sizeof( $this->list );
	} // getNumberOfPages

} // class SiteStructure