<?php

/**
 * PHP Application Packer (PackApp.php)
 * Pack the PHP Application files
 * 
 * Requirements:
 *  PHP 5.3+ with mbstring, zip
 *  Dependencies: plugins - PackCSS, PackHTM, PackJS, PackJSON, PackPHP, PackXML
 *
 * @package Packer
 * @author Vallo Reima
 * @copyright (C)2015
 */
abstract class Minify {

  const DS = DIRECTORY_SEPARATOR;
  const FIL = 1;  /* file mode */
  const FOL = 2;  /* folder mode */
  const ARC = 3;  /* archives mode */

  protected $app; /* program name */
  protected $opt = [ /* initial options */
      'lvl' => 0,
      'src' => '',
      'dst' => '',
      'sgr' => true,
      'sgn' => '', /* signature: null - suppress */
      'exf' => ['*.min.*'], /* files packing exclusion */
      'cpy' => true, /* copy non-minified too */
      'sbd' => true, /* recurse sub-directories */
      'sfx' => '_pkd', /* default destination name suffix */
      'pgn' => [], /* user plugins */
      'arc' => 'zip', /* archives type */
      'tml' => 30, /*  time limit in seconds a script is allowed to run */
      'log' => 0, /* logging: 0,1,2 */
      'dbg' => false /* debug mode */
  ];
  /* file types allowed: wildcard => dependencies; '!' means other plugin, e.g. *.inc is passed to php plugin */
  private $tps = ['*htm*' => ['php', 'js', 'css'], 'css*' => ['php'], 'js' => ['php'], 'json' => ['php'], 'xml' => [], 'php*' => [], 'inc' => ['!php']];
  private $pks = []; /* packer counters: type => counter */
  protected $lvl; /* processing level */
  protected $set; /* processing settings */
  protected $pth = [];  /* paths: extension, plugins */
  protected $pgs = [];  /* plugins list: [type => ['fnc','arr'] */
  protected $nms = ['ans' => 'addons', 'pgs' => 'plugins', 'min' => 'minify', 'pck' => 'Pack', 'srv' => 'S', 'obf' => 'O', 'cln' => ''];  /* names */
  protected $obf = null; /* obfuscator object */
  protected $obs = [];  /* obfuscation counters */
  private $tzn; /* user timezone */
  private $cnt = [0, 0, 0, [0.0, 0.0]];   /* count of files total, minified files, total lines, total symbols */
  private $tme = [0, 60]; /* start time, default exec time / elapsed time */
  private $sgr = ['', ''];  /* signatures: message, stamp */
  private $lgf = '';  /* log file */
  private $err = false; /* error flag */
  protected $rlt = ['', '', [], ''];     /* result data: token, short message, counters|html, details text */

  /**
   * called by extender
   * @param int $lvl
   * @param array $opt
   */
  public function __construct($lvl, $opt) {
    date_default_timezone_set(@date_default_timezone_get()); // define if not defined
    $this->lvl = $lvl;
    $app = __FILE__;  // apps filename
    $this->app = basename($app);
    $this->tzn = date_default_timezone_get(); // save user zone
    date_default_timezone_set('UTC'); // work in utc
    $this->tme = microtime(true);
    $this->Options($opt);
    $this->sgr[0] = "$this->app {$this->lic} {$this->ver}";  // messages signature
    if ($this->set->sgn) { // prepend to php/js/css output
      $this->sgr[1] = str_replace(['{app}', '{ver}', '{time}'], [$this->app, $this->ver, $this->Date(false)], $this->set->sgn); // stamp signature
    }
    set_time_limit($this->set->tml);
    $this->lgf = substr($app, 0, -strlen(pathinfo($app, PATHINFO_EXTENSION))) . 'log';
    $this->tps[$this->set->arc] = null; /* add as valid filetype */
    $pth = pathinfo($app, PATHINFO_DIRNAME);  /* root path */
    foreach (['ans', 'pgs'] as $p) {  // the paths 
      $this->pth[$p] = $pth . self::DS . $this->nms[$p]; // sub-dir
      if (!is_dir($this->pth[$p])) {
        $this->pth[$p] = dirname($pth) . self::DS . $this->nms[$p]; // sibling dir
      }
    }
    $this->Setup();
    if (is_null($this->obf) && $lvl > 1) { // irrelevant level
      $this->lvl = $lvl % 2;  // adjust level
    }
  }

  /**
   * option settings
   * @param array $opt
   */
  private function Options($opt) {
    $set = [];
    foreach ($this->opt as $key => $val) {  // loop defaults
      if (array_key_exists($key, $opt) && (!empty($opt[$key]) || empty($val) || is_bool($val) || is_array($val))) {  // user's value or allowed empty
        $val = $opt[$key];
        if (!is_array($val)) {
          settype($val, gettype($this->opt[$key])); // adjust type
        } else if (is_array($this->opt[$key])) {
          $val = (array) $val;  // array required
        }
      }
      $set[$key] = $val;
    }
    if ($set['tml'] < $this->opt['tml'] || $set['tml'] > 60 * $this->opt['tml']) {  // out of limits
      $set['tml'] = $this->opt['tml']; // default time limit
    }
    /* signature setting */
    $this->opt['sgn'] = "{app} {time}";  // default
    if (!array_key_exists('sgn', $opt)) { // skipped
      $set['sgn'] = $this->opt['sgn'];
    }
    $this->set = (object) $set;
  }

  /**
   * setup plugins and extension
   */
  private function Setup() {
    if ($this->lvl % 2 == 1) {
      $this->tps['js'] = [];  //no embeddings in obfuscated js
    }
    $a = ['', []];
    foreach ($this->tps as $p => $f) {
      if (is_array($f)) {
        $t = trim($p, '*'); // strip wildcard chars
        $this->pks[$t] = 0; // counter
        $this->pgs[$t] = ['fnc' => [$this->nms['pck'] . mb_strtoupper($t), $this->nms['min']], 'arr' => $a];
      }
    }
    foreach ($this->set->pgn as $p => $f) {  // add user types 
      $t = trim($p, '*'); // strip wildcard chars
      if (!isset($this->pks[$t]) ||
              (isset($this->tps[$p]) && count($this->tps[$p]) == 1 && mb_strpos($this->tps[$p][0], '!') !== false)) { // add or overwrite if '!' - item only
        $this->tps[$t] = $f === true ? ['php'] : [];  // php embedding or not
        $this->pks[$t] = 0;
        $this->pgs[$t] = ['fnc' => [$this->nms['pck'] . mb_strtoupper($t), $this->nms['min']], 'arr' => $a];
      }
    }
    $this->pgs['js']['arr'][1] = ['mth' => ''];  // default minify method
    $this->pgs['css']['arr'][1] = ['mth' => ''];  // RW is alternative
    $this->pgs['htm']['arr'][1] = ['jsMinifier' => $this->pgs['js'], 'cssMinifier' => $this->pgs['css']]; // callbacks
    if ($this->lvl % 2 == 1) {
      $this->pgs['js']['arr'][1]['mth'] = 'O';  //obfuscate
    }
    $this->nms['cln'] = pathinfo(__FILE__, PATHINFO_FILENAME) . $this->nms['srv'];  // server class
    $this->Server(); // connect server
    $this->nms['cln'] = pathinfo(__FILE__, PATHINFO_FILENAME) . $this->nms['obf'];  // extension class
    $this->Extend(); // extend functionality
    if ($this->obf) {
      $this->pgs['php']['arr'][1] = ['obn' => $this->obf, 'min' => !$this->set->dbg];  // obfuscate caller and minifying flag
    } else if (is_null($this->obf)) {
      $this->nms['cln'] = pathinfo(__FILE__, PATHINFO_FILENAME) . $this->nms['srv'];  // missing class
    }
  }

  protected function Server() {
    /* not implemented here */
  }

  protected function Extend() {
    /* not implemented here */
  }

  /**
   * minify and save
   * @param string $old -- source file/folder/archives
   * @param string $new -- destination
   * @param bool $rpl -- true - replace if exists
   * @return array
   */
  public function Pack($old, $new = '', $rpl = false) {
    $src = rtrim(str_replace('/', self::DS, (string) $old), self::DS);  // normalize 
    if ($new) {
      $dst = rtrim(str_replace('/', self::DS, (string) $new), self::DS);
    } else {
      $dst = $this->Destination($src);  // take default name 
    }
    if (!$this->err) {  // mode set
      $tmp = sys_get_temp_dir() . self::DS . uniqid($this->set->arc); // temporary (un)packing folder
      $mde = $this->Mode($src, $dst);
    }
    if (!$this->err) {  // mode ok
      if (!$rpl && file_exists($dst)) {
        $this->Msg('dse', basename($dst)); // don't replace
      } else if ($mde[0] == self::FIL) {
        $this->File($src, $dst);  // pack file to file
        if ($this->lvl > 1 && !$this->err) { // flag is ON and packed ok
          $this->Obfuscate();
        }
      } else {
        $fr = $mde[0] == self::ARC ? $tmp : $src; // from archives or folder
        $to = $mde[1] == self::ARC ? $tmp : $dst; // to archives or folder
        if ($mde[0] == self::FOL || $this->Archives($src, $fr, false)) {
          $this->Folder($fr, $to);        // pack folder to folder
          if ($this->lvl > 1 && !$this->err) { // flag is ON and packed ok
            $this->Obfuscate();
          }
        }
        if (!$this->err && $mde[1] == self::ARC) {
          $this->Archives($to, $dst, true); // archive the files
        }
        if ($to == $tmp || ($this->err && $src != $dst)) {
          $this->Remove($to);       // delete temporary or invalid to-folder
        }
      }
      if (!$this->err && $this->cnt[0] == 0) {
        $this->Msg('sre', $src); // no source file(s)
      }
    }
    date_default_timezone_set($this->tzn);  // restore user time
    if ($this->err) {  // error encountered
      $this->rlt[3] = $this->rlt[1];
    } else { // success
      $this->rlt[3] = $this->Result($src, $dst);
    }
    $a = explode(' - ', $this->rlt[1]);
    $c = array_pop($a);
    if (($this->set->log > 0 && $this->rlt[0] === 'ok') || $this->set->log > 1) {
      $l = date('Y-m-d H:i:s', $this->tme) . " $c\n";
      @file_put_contents($this->lgf, $l, FILE_APPEND); // log result
    }
    if (isset($this->opr)) {  // comshell
      $b = explode("\n", $c);
      $this->rlt[1] = explode(':', array_shift($b));
      $this->rlt[1] = array_merge($this->rlt[1], $b);
      if (!$this->err || $this->rlt[0] == 'dse') {
        array_push($this->rlt[1], $dst, $src);  // pass to shell
      }
    }
    $rlt = ['code' => $this->rlt[0], 'prompt' => $this->rlt[1], 'factor' => $this->rlt[2], 'string' => $this->rlt[3]];
    return $rlt;
  }

  /**
   * check source/destination names and types, set mode
   * @param string $src 
   * @param string $dst 
   * @return mixed -- array - in/out modes
   *                  bool - error
   */
  private function Mode($src, $dst) {
    $exs = mb_strtolower(pathinfo($src, PATHINFO_EXTENSION)); // source name extension 
    if (is_dir($src)) {
      $mde = self::FOL; // folder 
    } else if (is_null($this->Match($exs))) {
      $mde = 'sri'; // unsupported filetype
    } else if (!is_file($src)) {
      $mde = 'srn'; // missing file
    } else if ($exs == $this->set->arc) {
      $mde = self::ARC; // archives
    } else {
      $mde = self::FIL; // file
    }
    $exd = mb_strtolower(pathinfo($dst, PATHINFO_EXTENSION)); // dest name extension 
    if (is_string($mde)) {  // invalid source
      $rlt = $this->Msg($mde, $src);
    } else if (($mde == self::FIL && $exd != $exs) || /* file to unknown type */
            ($mde > self::FIL && $exd != $this->set->arc && !is_null($this->Match($exd)))) {  // non-file to known type
      $rlt = $this->Msg('dsi', $dst);  // invalid destination
    } else if (!$this->obf && $this->lvl > 1) {
      $rlt = $this->Msg('nof', $this->nms['cln']); // no extension
    } else {
      $rlt = [$mde];
      if ($mde == self::FIL) {  // destination mode
        $rlt[] = $mde;
      } else if ($exd == $this->set->arc) {
        $rlt[] = self::ARC;
      } else {
        $rlt[] = self::FOL;
      }
    }
    if (isset($rlt[1]) && $rlt[0] == self::FOL) { //check folders' suitability
      $d = $rlt[1] == self::FOL ? $dst : pathinfo($dst, PATHINFO_DIRNAME);
      if ($d != $src && mb_strpos($d, $src . self::DS) === 0) {
        $rlt = $this->Msg('dsi', $dst);  // source can't be destination's subdir
      }
    }
    return $rlt;
  }

  /**
   * (un)compact the archives
   * @param string $src 
   * @param string $dst
   * @param bool $flg -- true - compact
   * @return bool
   */
  private function Archives($src, $dst, $flg) {
    $mth = 'Pack' . ucfirst(mb_strtolower($this->set->arc)) . ($flg ? 'C' : 'U'); // archiving method
    $r = $this->$mth($src, $dst);
    $rlt = is_int($r);
    if (!$rlt) {// can't (un)arc
      if ($r[0] == 'noc' && !$flg) {
        $this->Remove($dst); // delete the remains
      } else if ($r[0] == 'noz' && $flg) {
        @unlink($dst); //delete bad arc
      }
      $this->Msg($r[0], $r[1]);
    } else if ($flg && $r != $this->cnt[0]) {  // packed and arced files' counts are different
      @unlink($dst);
      $this->Msg('noz', "$r/{$this->cnt[0]}");
      $rlt = false;
    }
    return $rlt;
  }

  /**
   * uncompact the zip archives
   * @param string $src 
   * @param string $dst 
   * @return int|array
   */
  protected function PackZipU($src, $dst) {
    $zip = new ZipArchive;
    if ($zip->open($src, ZipArchive::CHECKCONS) === true) {
      if ($zip->extractTo($dst)) {
        $rlt = $zip->numFiles;
      } else {
        $rlt = ['noc', $dst]; // can't unzip
      }
      $zip->close();
    } else {
      $rlt = ['sri', $src]; // bad zip
    }
    return $rlt;
  }

  /**
   * compact the zip archives
   * @param string $src 
   * @param string $dst 
   */
  protected function PackZipC($src, $dst) {
    $zip = new ZipArchive;
    if ($zip->open($dst, ZipArchive::OVERWRITE) === true) {
      $rlt = $this->Zip($src, $zip);
      if (is_string($rlt)) {
        $rlt = ['noz', $rlt]; // couldn't zip
      } else if ($rlt != $zip->numFiles) {
        $rlt = ['noz', "$rlt/$zip->numFiles"]; // adding and zipping counts are different
      } else {
        $rlt = $zip->numFiles;
      }
      $zip->close();  /* save data */
    } else {
      $rlt = ['noc', $dst]; // can't create
    }
    return $rlt;
  }

  /**
   * zip the directory
   * @param string $dir
   * @param object $zip 
   * @return mixed
   */
  private function Zip($dir, &$zip) {
    $cnt = 0;
    $pos = mb_strlen($dir) + 1; // remove part of the path
    $itr = new RecursiveIteratorIterator(// traverse directories
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD
    );
    foreach ($itr as $fle => $spl) {
      if ($spl->isFile()) {
        $nme = str_replace(self::DS, '/', substr($fle, $pos));  // normalize localname
        if ($zip->addFile($fle, $nme)) {  // add a file
          $cnt++;
        } else {
          $cnt = $fle;  // couldn't add the file
          break;
        }
      }
    }
    return $cnt;
  }

  /**
   * pack the directory
   * @param string $src 
   * @param string $dst 
   */
  private function Folder($src, $dst, $skp = null) {
    $dir = opendir($src);
    if (!$dir) {
      $this->Msg('noc', $src); // can't access input folder
    } else if (!$skp) { //not skipping
      $skp = !is_null($skp) && !is_null($this->PackCheck(basename($src), $this->set->exf));  // folder exclusion
    }
    if ((!$skp || $this->set->cpy) && $dir && !is_dir($dst) && !@mkdir($dst)) {
      $this->Msg('noc', $dst); // can't create output folder
    }
    while (!$this->err && ($file = readdir($dir)) !== false) {
      if (( $file != '.' ) && ( $file != '..' )) {  // skip dot entries
        $fr = $src . self::DS . $file;
        $to = $dst . self::DS . $file;
        if (!is_dir($fr)) {
          $this->File($fr, $to, $skp);    // pack the file
        } else if ($this->set->sbd) {
          $this->Folder($fr, $to, $skp);  // recurse subfolder
        }
      }
    }
    closedir($dir);
  }

  /**
   * pack a file and save
   * @param string $src 
   * @param string $dst 
   * @param bool $skp -- true - don't minify
   */
  private function File($src, $dst, $skp = false) {
    if ($skp) { // skip minify
      $tpe = [false];
    } else {
      $tpe = $this->Match(mb_strtolower(pathinfo($src, PATHINFO_EXTENSION)), true); // matching filetype & dependent ones
      $t = $tpe[0];
      $tpe[2] = '';
      if (!isset($this->pks[$tpe[0]]) || !$this->Plugin($tpe)) {
        $tpe[0] = null; // no plugin
      } else if (array_search('php', $tpe[1]) !== false) {
        $tpe[2] = 'php';
      } else if (isset($tpe[1][0]) && mb_strpos($tpe[1][0], '!') === 0) {
        $tpe[0] = mb_substr($tpe[1][0], 1);
      }
      $tpe[1] = $t;
    }
    if ($tpe[0] && is_null($this->PackCheck(basename($src), $this->set->exf))) { // file to pack
      $str = @file_get_contents($src);
      if ($str !== false) {  // source obtained 
        $lc = mb_substr_count($str, "\n") + 1;
        $lp = mb_strlen($str);
        $str = $this->Minify($str, $tpe, $dst);
        if (is_string($str)) {  // source is packed
          $la = mb_strlen($str);
          if (@file_put_contents($dst, $str) === false) {
            $str = ['noa', $dst];
          }
        } else if (is_array($str)) {
          $str = ['sri', "$dst - $str[0]"]; // content error encountered
        } else {
          $str = ['nop', $dst]; // error encountered
        }
      } else {
        $str = ['noa', $src];
      }
      if (is_string($str)) {
        ++$this->cnt[0];
        ++$this->cnt[1];
        $this->cnt[2] += $lc;
        $this->cnt[3][0] += (float) $lp;
        $this->cnt[3][1] += (float) $la;
      } else if (is_array($str)) {
        $this->Msg($str[0], $str[1]); // error encountered
      }
    } else if ($src == $dst || !$this->set->cpy || @copy($src, $dst)) { // copy non-packing file
      ++$this->cnt[0];
    } else {
      $this->Msg('noc', $dst); // can't copy
    }
  }

  /*
   * pack the files
   * @param string $str
   * @param array $tpe -- [1st, cnt, 2nd]
   * @param string $fle -- destination filename
   * @return mixed -- string - packed
   */

  private function Minify($str, $tpe, $fle) {
    $this->pgs[$tpe[0]]['arr'][0] = $str;  // source string
    $this->pgs[$tpe[0]]['arr'][1]['fle'] = $fle;  // source file
    $rlt = call_user_func_array($this->pgs[$tpe[0]]['fnc'], $this->pgs[$tpe[0]]['arr']);  // minify code
    if (is_string($rlt) && $tpe[2]) {  // may contain php
      $this->pgs[$tpe[2]]['arr'][0] = $rlt;
      $this->pgs[$tpe[2]]['arr'][1]['fle'] = $fle;  // source file
      $rlt = call_user_func_array($this->pgs[$tpe[2]]['fnc'], $this->pgs[$tpe[2]]['arr']);  // minify embedded code
    }
    if (is_string($rlt)) {  // minified ok
      ++$this->pks[$tpe[1]];
      $p = '<?php  ?>';
      if (($tpe[0] == 'php' || $tpe[2] == 'php') && mb_strpos($rlt, $p) === 0) {
        $rlt = mb_substr($rlt, mb_strlen($p)); // empty php tag
        if (empty($rlt)) {
          $rlt = $p;
        }
      }
      if ($this->sgr[1]) {   // signature specified
        if ($tpe[0] == 'css' || $tpe[0] == 'js') {
          $s = '/*#*/';
        } else if ($tpe[0] == 'php') {
          $s = '<?php /*#*/ ?>';
        } else {
          $s = '';
        }
        if ($s) {
          $rlt = str_replace('#', $this->sgr[1], $s) . $rlt; // prepend the signature
        }
      }
    }
    return $rlt;
  }

  protected function Obfuscate() {
    /* not implemented here */
  }

  /**
   * foreign request
   * @return string warning
   */
  protected function Request() {
    $this->Msg('nof', $this->nms['cln']); // no module
    return $this->rlt[1];
  }

  /**
   * include the plugin for the file type
   * @param array $tpe -- [primary type,secondary types]
   * @return bool -- true - primary is loaded
   */
  private function Plugin($tpe) {
    $flg = true;
    if (isset($tpe[1][0]) && mb_strpos($tpe[1][0], '!') !== false) {
      $t = array_shift($tpe[1]);
    } else {
      $t = "!$tpe[0]";
    }
    $tps = array_merge([$t], $tpe[1]);  // types list, primary is mandatory
    foreach ($tps as $t) {
      if (mb_strpos($t, '!') !== false) { //primary
        $t = str_replace('!', '', $t);
        $f = false;  // required
      } else {
        $f = true;
      }
      $nme = $this->nms['pck'] . mb_strtoupper($t); //class name
      if (!$this->Loader($nme, $this->pth['pgs'], $this->nms['min'])) {
        $this->pks[$t] = null; //mark n/a
        $flg = $flg && $f;
      }
    }
    return $flg;
  }

  /**
   * the class loader
   * @param string $nme class name
   * @param string $pth file path
   * @param string $mth method name
   * @return bool -- true - loaded
   */
  protected function Loader($nme, $pth, $mth) {
    $flg = true;
    if (!class_exists($nme, false)) { // not loaded
      $fnm = $pth . self::DS . "$nme.php";  // full file name
      @include($fnm);
      if (!class_exists($nme, false) || !method_exists($nme, $mth)) { // not found
        $flg = false;
      }
    }
    return $flg;
  }

  /**
   * form destination pathed name
   * @param string $src name
   * @return string
   */
  private function Destination($src) {
    $a = explode(self::DS, $src);
    $c = array_pop($a);
    $n = mb_strrpos($c, '.');
    if ($n) { // insert token
      array_push($a, substr($c, 0, $n) . $this->set->sfx . substr($c, $n));
      $r = implode(self::DS, $a);
    } else {  // append token 
      $r = $src . $this->set->sfx;
    }
    return $r;
  }

  /**
   * match file type against the patterns
   * @param string $tpe
   * @param string $flg -- true - full info
   * @return mixed
   */
  private function Match($tpe, $flg = false) {
    $t = $this->PackCheck($tpe, array_keys($this->tps));
    if ($flg) {
      $rlt = $t ? [trim($t, '*?!'), $this->tps[$t]] : ['', []];
    } else {
      $rlt = $t;
    }
    return $rlt;
  }

  /**
   * check the patterns list match
   * @param string $str
   * @param array $pts patterns
   * @return string|false
   */
  public function PackCheck($str, $pts) {
    $rlt = null;
    foreach ($pts as $ptn) {
      if (mb_strrpos($ptn, '!') === mb_strlen($ptn) - 1) {
        $ptn = mb_substr($ptn, 0, -1);
        $flg = FNM_CASEFOLD;
      } else {
        $flg = 0;
      }
      if (fnmatch($ptn, $str, $flg)) {
        $rlt = $ptn;
        break;
      }
    }
    return $rlt;
  }

  /**
   * delete the directory
   * @param string $dir
   */
  private function Remove($dir) {
    if (is_dir($dir)) {
      $itr = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($itr as $pth) {
        $pth->isDir() && !$pth->isLink() ? @rmdir($pth->getPathname()) : @unlink($pth->getPathname());
      }
      @rmdir($dir);
    }
  }

  /**
   * display result data
   * @param string $src 
   * @param string $dst
   * @return string to echo
   */
  private function Result($src, $dst) {
    list($ct, $cp) = $this->cnt;  // total and minified
    $this->Msg('ok', "'$src' => '$dst' ($cp/$ct)");
    $sec = microtime(true) - $this->tme; // elapsed secs
    $tme = [$this->Date(true), $sec]; // time started, elapsed
    $d = $this->cnt[3][0] ? (($this->cnt[3][0] - $this->cnt[3][1]) / $this->cnt[3][0]) : 0;
    $this->cnt[3] = number_format(100 * $d, 0) . '%';
    ksort($this->pks);
    $this->cnt[] = $this->pks; // packed by type
    list($tot, $min, $lns, $cmr, $tps) = $this->cnt;
    $str = "{$this->sgr[0]} $tme[0]"; // message header
    $str .= "\n{$this->txt['src']}: " . realpath($src);
    $str .= "\n{$this->txt['dst']}: " . realpath($dst);
    if ($this->lvl % 2 == 0 || !isset($tps['js'])) {
      $obf = [0];
    } else {
      $obf = [$tps['js']]; //js obfucation
    }
    if (empty($this->obs)) {
      $obf[1] = 0; //php obfucation
    } else {
      list($obf[1], $id, $rpl) = $this->obs[0]; // total and by identifiers
    }
    $str .= "\n{$this->txt['fmt']}: $min/$tot";
    foreach ($tps as $tpe => $t) {
      $c = is_null($t) ? $this->txt['n/a'] : $t;
      $str .= "\n\t$tpe: $c";
    }
    $c = $tot - $min;
    if ($c > 0) {
      $cc = $this->set->cpy ? 'cpy' : 'skp';
      $str .= "\n\t{$this->txt[$cc]}: $c";
    }
    $o = $obf[0] + $obf[1];
    $str .= "\n{$this->txt['obf']}: $obf[0]+$obf[1]=$o";
    if ($obf[1]) { //obfuscation made
      $str .= "\n{$this->txt['irr']}: $id/$rpl";
      $obs = [T_VARIABLE => $this->txt['vrs'], T_CONST => $this->txt['cns'], T_START_HEREDOC => $this->txt['hds'], T_FUNCTION => $this->txt['fns'], T_CLASS => $this->txt['cls'], T_TRAIT => $this->txt['trs']];  /* tokens list */
      foreach ($this->obs[1] as $t => $s) {
        $str .= "\n\t$obs[$t]: ";
        if ($s) {
          $str .= "$s[0]/$s[1]";
        } else {
          $str .= $this->txt['skp'];
        }
      }
    }
    if ($sec < 1) {
      $t = number_format($sec, 2);
    } else if ($sec < 10) {
      $t = number_format($sec, 1);
    } else {
      $t = number_format($sec, 0);
    }
    $str .= "\n{$this->txt['lns']}: $lns";
    $str .= "\n{$this->txt['cmr']}: $cmr";
    $str .= "\n{$this->txt['tme']}: $t {$this->txt['sec']}";
    $this->rlt[2] = [$this->cnt, $this->obs, $tme]; // save the counters
    return $str;
  }

  /**
   * startup date
   * $param bool $flg -- true - formatted
   * @return string
   */
  private function Date($flg) {
    $z = date_default_timezone_get(); // save user zone
    date_default_timezone_set('UTC'); // work in utc
    if ($flg) {
      $d = date('Y-m-d H:i', $this->tme) . ' UTC'; // timestamp
    } else {
      $d = date('Y-m-d H:i:s', $this->tme);
    }
    date_default_timezone_set($z); // restore user zone
    return $d;
  }

  /**
   * form message, save status
   * @param string $t token/text
   * @param string $p prompt
   * @return string
   */
  protected function Msg($t, $p = '') {
    $this->err = ($t !== 'ok'); // set error flag
    if (!is_array($t) && isset($this->txt[$t])) { // fixed message
      $this->rlt[0] = $t;
      $t = $this->txt[$t];
    } else {
      $this->rlt[0] = 'err';
    }
    if (!is_array($t)) {
      $t = [$t];
    }
    if ($p) {
      $t[0] .= ": $p";  // add details
    }
    $this->txt['err'] = implode("\n", $t);  // for inner use
    $this->rlt[1] = "{$this->sgr[0]} - {$this->txt['err']}"; // save message
    return $this->err;
  }

  protected $txt = [
      'ok' => 'Source is packed',
      'err' => '',
      'srn' => 'Missing source',
      'sri' => 'Illegal source',
      'dsi' => 'Illegal destination',
      'dse' => 'Destination exists',
      'sre' => 'Source is empty',
      'nof' => ['Missing add-on',
          'Visit http://vregistry.com'],
      'noa' => "Can't access",
      'nop' => 'Cannot pack',
      'noc' => 'Cannot create',
      'nob' => 'Cannot obfuscate',
      'noz' => 'Unsuccessful archiving',
      'lns' => 'Lines processed',
      'cmr' => 'Compression ratio',
      'src' => 'From',
      'dst' => 'To',
      'fmt' => 'Files minified/total',
      'obf' => 'Files (js+php) obfuscated',
      'irr' => 'Identifiers (php) mapped/replaced',
      'vrs' => 'variables',
      'cns' => 'constants',
      'hds' => 'heredocs',
      'fns' => 'functions',
      'trs' => 'traits',
      'cls' => 'classes',
      'cpy' => 'copied',
      'skp' => 'skipped',
      'n/a' => 'N/A',
      'tme' => 'Elapsed time',
      'sec' => 'sec'
  ];

}

class PackApp extends Minify {

  public $ver = '0.1.0'; /* version */
  public $lic = 'Try'; /* license */
  
  /**
   * 
   * @param int $lvl -- obfuscation:
   *                      0|false - no
   *                      1|true - JS
   * @param array $opt -- options
   */
  public function __construct($lvl = 0, $opt = []) {
    parent::__construct((int) $lvl, (array) $opt);
  }

  /**
   * foreign action
   */
  public static function Packer() {
    $obj = new PackApp(-1);
    $rsp = str_replace("\n", '<br>', $obj->Request()); // display in html
    header("Content-Type: text/html; charset=utf-8");
    return $rsp;
  }

}

if (count(get_included_files()) == 1) {
  echo PackApp::Packer(); //called outside
}
