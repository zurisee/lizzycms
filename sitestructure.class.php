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
	public $currPage;
	public $prevPage = '';
	public $nextPage = '';
	public $currPageRec = false;
	public $config;
	private $noContent = false;

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
		$this->cacheFile = $this->cachePath.'_siteStructure.dat';


		if (!$this->readCache()) {
            if ($config->feature_sitemapFromFolders) {
                $this->list = $this->getSitemapFromFolders();
            } else {
                $this->list = $this->getList();
            }
			$this->tree = $this->getTree();
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
            $this->currPageRec = [
                'name' => 'Default',
                'level' => 0,
                'folder' => '_unknown/',
                'requestedFolder' => $currPage,
                'isCurrPage' => true,
                'inx' => '0',
                'urlExt' => '',
                'active' => false,
                'noContent' => false,
                'hide!' => false,
                'hasChildren' => false,
                'restricted' => false,
                'parent' => null
            ];
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
	public function getSiteList() {
		return $this->list;
	} // getSiteList



	//....................................................
	public function getSiteTree() {
		return $this->tree;
	} // getSiteTree




    //....................................................
    private function getSitemapFromFolders() {
        $list = array();
        $i = 0;
        return $this->_traverseDir($this->config->path_pagesPath,$i, 0, $list);
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
                $rec['inx'] = $i;
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



    //....................................................
	private function getList()
	{
		$lines = file($this->site_sitemapFile);
		$list = array();
		$i = -1;
		$lastLevel = 0;
		foreach($lines as $line) {
			if (strpos($line, '__END__') !== false) {
				break;
			}

            $rec = [];
			$line = str_replace(['http://', 'https://'], ['http:||', 'https:||'], $line);
			$line = preg_replace('|//.*|', '', $line);
			$line = preg_replace('|#.*|', '', $line);
            $line = str_replace(['http:||', 'https:||'], ['http://', 'https://'], $line);
			$line = rtrim($line);
			if (preg_match('/^\s*$/', $line) || preg_match('/^\s*#/', $line)) {continue;}
			$i++;

			if (preg_match('/^(\s*) \{\{ ([^\}]+) \}\} (.*)/x', $line, $m)) {   // catch {{}}
			    $transName = "{{ {$m[2]} }}";
			    $line = $m[1].ltrim($m[2]).$m[3];
            } else {
			    $transName = false;
            }

			if (preg_match('/^(\s*)([^:\{]+)(.*)/', $line, $m)) {
				$indent = $m[1];
				$name = trim($m[2]);
				$args = preg_replace('/:?\s*(\{[^\}]*\})/', "$1", $m[3]);;

				$rec['name'] = $transName ? $transName : trim($m[2]);
				if (strlen($indent) === 0) {
                    $level = 0;
                } else {
				    // siteIdententation -> 4 blanks count as one tab
                    // convert every 4 blanks to a tab, then remove all remaining blanks => level
				    $indent = str_replace(str_repeat(' ', $this->config->siteIdententation), "\t", $indent);
				    $indent = str_replace(' ', '', $indent);
                    $level = strlen($indent);
                }
				if (($level - $lastLevel) > 1) {
                    writeLog("Error in sitemap.txt: indentation on line $line (level: $level / lastLevel: $lastLevel)", true);
                    $level = $lastLevel + 1;
				}
                $rec['level'] = $level;
				$lastLevel = $level;

				$rec['folder'] = basename(translateToFilename($name, true), '.html').'/';
				if ($args) {
					$args = parseArgumentStr($args, ',', true);
					if (is_array($args)) {
						foreach($args as $key => $value) {
							if (($key === 'folder') || ($key === 'showthis')) {

							    // if it's folder, take care of absolute URLs, e.g. HTTP://
							    if (preg_match('|^https?\://|i', $value)) { // external link:
							        $folder = $value;

                                } else {                                // internal link -> fix it if necessary
                                    $folder = fixPath($value);
                                    if (!$folder) {
                                        $folder = './';
                                    }
                                }
								$rec[strtolower($key)] = $folder;

							} else {
								$rec[strtolower($key)] = $value;
							}
						}
					}
				}
				$rec['isCurrPage'] = false;
				$rec['inx'] = $i;
				$rec['urlExt'] = '';
				$rec['active'] = false;
				$rec['noContent'] = false;

				// Hide: hide always propagating, therefore we need 'hide!' element
                if (isset($rec['hide!'])) {
                    if (isset($rec['hide'])) {
                        unset($rec['hide']);
                    }
                } else {
                    $rec['hide!'] = (isset($rec['hide'])) ? $rec['hide'] : false;   // always propagate hide attribute
                    unset($rec['hide']);    // beyond this point hide is permanently replaced by hide!
                }


				// case: page only visible to selected users:
                $hideArg = &$rec['hide!'];

                if ($hideArg) {
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
                }


                // check time dependencies:
				if (isset($rec['availablefrom'])) {
				    $t = strtotime($rec['availablefrom']);
                    if (time() < $t) {
                        continue;
                    }
                }
				if (isset($rec['availabletill'])) {
				    $t = strtotime($rec['availabletill']);
                    if (time() > $t) {
                        continue;
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

                if (isset($rec['showthis']) && $rec['showthis']) {
                    $rec['actualFolder'] = $rec['showthis'];
                } else {
                    $rec['actualFolder'] = $rec['folder'];
                }
                if (isset($rec['alias']) && $rec['alias']) {
                    $rec['alias'] = resolvePath(fixPath($rec['alias']));
                }
                $list[] = $rec;
            }
        }
		return $list;
	} // getList



	//....................................................
	private function getTree()
	{
		$this->lastLevel = 0;
		$i = 0;
		list($tree, $visChildren) = $this->exploreTree($this->list, $i, '', 0, null);
		return $tree;
	} // getTree



	//....................................................
	private function exploreTree(&$list, &$i, $path, $lastLevel, $parent)
	{
	                    // $i -> list-elem counter
		$j = 0;         // $j -> counter within level
		$tree = array();
		$hasVisibleChildren = false;
		if ($path === './') {
		    $path = '';
        }

		$pagesPath = $this->config->path_pagesPath;
        while ($i < sizeof($list)) {
            $level = $list[$i]['level'];
			if ($level > $lastLevel) {
				$lastRec = &$list[$i-1];

				list($subtree, $visChildren) = $this->exploreTree($list, $i, $list[$i-1]['folder'], $lastLevel+1, ($i) ? ($i-1) : null);

				$lastRec['hasChildren'] = $visChildren;
				$tree[$j-1] = (isset($tree[$j-1])) ? array_merge($tree[$j-1], $subtree) : $subtree;

			} elseif ($level === $lastLevel) {
				$rec = &$list[$i];
                $rec['hasChildren'] = false;
				if (substr($rec['folder'], 0, 2) != '~/') {
                    $rec['folder'] = $path.$rec['folder'];
				} else {
                    $rec['folder'] = (strlen($rec['folder']) > 2) ? substr($rec['folder'], 2) : '';
				}

				if (substr($rec['actualFolder'], 0, 2) != '~/') {
                    $rec['actualFolder'] = $path.$rec['actualFolder'];
				} else {
                    $rec['actualFolder'] = (strlen($rec['actualFolder']) > 2) ? substr($rec['actualFolder'], 2) : '';
				}
                $mdFiles = getDir("$pagesPath{$rec['actualFolder']}*.md");
                $rec['noContent'] = (sizeof($mdFiles) === 0);

				$list[$i]['parent'] = $parent;
				if (isset($rec['hide!']) && $rec['hide!']) {
					$hasVisibleChildren = true;
				}

				$tree[$j] = &$list[$i];
				$i++;
				$j++;
			} else {
				return array($tree, $hasVisibleChildren); // going up
			}
		}
		return array($tree, $hasVisibleChildren); // the end, all pages consumed
	} // exploreTree



    private function propagateProperties()
    {
        $this->_propagateProperties($this->tree);
    } // propagateProperties




    private function _propagateProperties($subtree, $level = 0, $toPropagate = [])
    {
        $toPropagate1 = $toPropagate;
        if (isset($subtree['inx'])) {
            $r = &$this->list[$subtree['inx']];
            foreach ($toPropagate1 as $k => $v) {
                $r[$k] = $v;
            }

            if ($r !== null) {
                foreach ($r as $k => $v) {
                    if ($k === 'hide!') { // special case 'hide!'
                        if ($v) {
                            $toPropagate1['hide!'] = true;
                        }
                    } elseif (strpos($k, '!') !== false) {    // item to propagate found
                        $k1 = str_replace('!', '', $k);
                        $toPropagate1[$k1] = $v;
                        $r[$k1] = $v;
                        unset($r[$k]);
                    }
                }
            }
        }

        foreach ($subtree as $key => $rec) {
            if (is_int($key)) {
                $this->_propagateProperties($rec, $level + 1, $toPropagate1);
            }
        }
    } // _propagateProperties




	//....................................................
	private function markParents()
	{
		$this->currPageRec['active'] = true;
		$rec = &$this->currPageRec;
		while ($rec['parent'] !== null) {
			$rec = &$this->list[$rec['parent']];
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
			if ($found || ($str === $elem['folder']) || ($str.'/' === $elem['folder'])) {
				$folder = $this->config->path_pagesPath.$elem['folder'];
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
				$dir = getDir($this->config->path_pagesPath.$elem['folder'].'*');	// check whether folder is empty, if so, move to the next non-empty one
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
        while ($elem['parent'] !== null) {
            $elem = $this->list[$elem['parent']];
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