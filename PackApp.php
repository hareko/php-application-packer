<?php

/*
 * Pack the PHP Application files
 * 
 * Requirements:
 *  PHP 5.3+ with mbstring, zip
 *  Dependencies: add-ons - PackCSS, PackHTM, PackJS, PackJSON, PackPHP, PackXML
 *                extension - PackAppE
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

  private $tit; /* program name */
  private $sgr = '';  /* packer signature */
  /* file types allowed: wildcard => dependencies; '!' means other add-on, e.g. *.inc is passed to php add-on */
  private $tps = ['*htm*' => ['php', 'js', 'css'], 'css*' => ['php'], 'js' => ['php'], 'json' => ['php'], 'xml' => [], 'php*' => [], 'inc' => ['!php']];
  private $pks = []; /* packer counters: type => counter */
  protected $lvl; /* processing level */
  protected $set; /* processing settings */
  protected $pth = [];  /* paths: extension, add-ons */
  protected $ans = [];  /* add-ons list: [type => ['fnc','arr'] */
  protected $nms = ['minify', 'Pack', 'E'];  /* names */
  protected $ext = null; /* extension object */
  protected $obs = [];  /* obsfucation counters */
  private $tzn; /* user timezone */
  private $cnt = [0, 0, 0, [0.0, 0.0]];   /* count of files total, minified files, total lines, total symbols */
  private $tme = [0, 60]; /* start time, default exec time / elapsed time */
  private $smp = '';  /* packed content stamp */
  private $err = false; /* error flag */
  protected $rlt = ['', '', [], ''];     /* result data: token, short message, counters|html, details text */

  /**
   * called by extender
   * @param int $lvl
   * @param array $opt
   */
  public function __construct($lvl, $opt) {
    date_default_timezone_set(@date_default_timezone_get()); // define if not defined
    $this->tit = basename(__FILE__);
    $this->sgr = "$this->tit v$this->ver $this->tkn";  // signature
    $this->nms[2] = pathinfo(__FILE__, PATHINFO_FILENAME) . $this->nms[2];  // extension classname
    $this->lvl = $lvl;
    $this->Options($opt);
    $this->tzn = date_default_timezone_get(); // save user zone
    date_default_timezone_set('UTC'); // work in utc
    set_time_limit($this->set->tml);
    $this->tme[0] = microtime(true);
    $this->tps[$this->set->arc] = null; /* add as valid filetype */
    $this->pth[0] = pathinfo(__FILE__, PATHINFO_DIRNAME);
    $this->pth[1] = $this->pth[0] . self::DS . $this->nms[0]; // add-ons path
    $this->Setup();
    if ($this->ext === false && $lvl > 1) { // irrelevant level
      $this->lvl = $lvl % 2;  // adjust level
    }
    if (!is_null($this->set->sgn)) { // prepend to php/js/css output
      $s = (empty($this->set->sgn) ? $this->sgr : $this->set->sgn) . ' ' . $this->Date();
      $this->smp = "/*! $s */";
    }
  }

  /**
   * option settings
   * @param array $opt
   */
  private function Options($opt) {
    $set = [];
    foreach ($this->opt as $key => $val) {  // loop defaults
      if (array_key_exists($key, $opt) && (!empty($opt[$key]) || empty($val) || is_array($val))) {  // user's value or allowed empty
        $val = $opt[$key];
        if (!is_array($val)) {
          settype($val, gettype($this->opt[$key])); // adjust type
        } else if (is_array($this->opt[$key])) {
          $val = (array) $val;  // array required
        }
      }
      $set[$key] = $val;
    }
    if (array_key_exists('sgn', $opt) && is_null($opt['sgn'])) { // skipped
      $set['sgn'] = null;
    }
    if ($set['tml'] < $this->opt['tml'] || $set['tml'] > 60 * $this->opt['tml']) {  // out of limits
      $set['tml'] = $this->opt['tml']; // default time limit
    }
    $this->set = (object) $set;
  }

  /**
   * setup add-ons and extension
   */
  private function Setup() {
    $a = ['', []];
    foreach ($this->tps as $p => $f) {
      if (is_array($f)) {
        $t = trim($p, '*'); // strip wildcard chars
        $this->pks[$t] = 0; // counter
        $this->ans[$t] = ['fnc' => [$this->nms[1] . mb_strtoupper($t), $this->nms[0]], 'arr' => $a];
      }
    }
    foreach ($this->set->aon as $p => $f) {  // add user types 
      $t = trim($p, '*'); // strip wildcard chars
      if (!isset($this->pks[$t]) ||
              (isset($this->tps[$p]) && count($this->tps[$p]) == 1 && mb_strpos($this->tps[$p][0], '!') !== false)) { // add or overwrite if '!' - item only
        $this->tps[$t] = $f === true ? ['php'] : [];  // php embedding or not
        $this->pks[$t] = 0;
        $this->ans[$t] = ['fnc' => [$this->nms[1] . mb_strtoupper($t), $this->nms[0]], 'arr' => $a];
      }
    }
    $this->ans['js']['arr'][1] = ['mth' => ''];  // default minify method
    $this->ans['css']['arr'][1] = ['mth' => ''];  // RW is alternative
    $this->ans['htm']['arr'][1] = ['jsMinifier' => $this->ans['js'], 'cssMinifier' => $this->ans['css']]; // callbacks
    if ($this->lvl % 2 == 1) {
      $this->ans['js']['arr'][1]['mth'] = 'O';  //obfuscate
    }
    $v = $this->Extend(); // set extension
    if ($v) { // set extended version
      $v1 = explode('.', $this->ver);
      $v2 = explode('.', $v);
      $j = count($v1) - count($v2);
      $v = implode('.', array_slice($v1, 0, $j)); // main release
      for ($i = $j; $i < count($v1); $i++) { // form version number
        $v .= '.' . ($v1[$i] + $v2[$i - $j]);
      }
      $this->sgr = str_replace($this->ver, $v, $this->sgr);  // update signature
    }
  }

  /**
   * dummy extension
   * @return string version
   */
  protected function Extend() {
    $this->ext = false; // not applicable
    return '';
  }

  /**
   * minify and save
   * @param type $old -- source file/folder/archives
   * @param type $new -- destination
   * @param type $rpl -- true - replace if exists
   * @return array
   */
  public function Pack($old, $new = '', $rpl = false) {
    if (!$this->err) {  // mode set
      $src = rtrim(str_replace('/', self::DS, (string) $old), self::DS);  // normalize 
      if ($new) {
        $dst = rtrim(str_replace('/', self::DS, (string) $new), self::DS);
      } else {
        $dst = $this->Destination($src);  // take default name 
      }
      $tmp = sys_get_temp_dir() . self::DS . uniqid($this->set->arc); // temporary (un)packing folder
      $mde = $this->Mode($src, $dst);
    }
    if (!$this->err) {  // mode set
      if (!$rpl && file_exists($dst)) {
        $this->Msg('dse', $dst); // don't replace
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
    $rlt = ['code' => $this->rlt[0], 'prompt' => $this->rlt[1], 'factor' => $this->rlt[2], 'string' => $this->rlt[3]];
    return $rlt;
  }

  /**
   * check source/destination names and extension, set mode
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
    } else if ($mde > self::FIL && $exd != $this->set->arc && !is_null($this->Match($exd))) {
      $rlt = $this->Msg('dsi', $dst);  // invalid destination
    } else if (!$this->ext && $this->lvl > 1) {
      $rlt = $this->Msg('noe', $this->nms[2]); // no extension
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
      } else if ($r[0] == 'noa' && $flg) {
        @unlink($dst); //delete bad arc
      }
      $this->Msg($r[0], $r[1]);
    } else if ($flg && $r != $this->cnt[0]) {  // packed and arced files' counts are different
      @unlink($dst);
      $this->Msg('noa', "$r/{$this->cnt[0]}");
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
    if ($zip->open($src, ZipArchive::CHECKCONS)) {
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
    if ($zip->open($dst, ZipArchive::OVERWRITE)) {
      $rlt = $this->Zip($src, $zip);
      if (is_string($rlt)) {
        $rlt = ['noa', $rlt]; // couldn't zip
      } else if ($rlt != $zip->numFiles) {
        $rlt = ['noa', "$rlt/$zip->numFiles"]; // adding and zipping counts are different
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
        if ($zip->addFile($fle, substr($fle, $pos))) {  // add without root path
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
  private function Folder($src, $dst) {
    $dir = opendir($src);
    if (!is_dir($dst) && !@mkdir($dst)) {
      $this->Msg('noc', $dst); // can't create output folder
    } else {
      $skp = !is_null($this->PackCheck(basename($src), $this->set->exf));  // folder exclusion
    }
    while (!$this->err && ($file = readdir($dir)) !== false) {
      if (( $file != '.' ) && ( $file != '..' )) {  // skip dot entries
        $fr = $src . self::DS . $file;
        $to = $dst . self::DS . $file;
        if (is_dir($fr)) {
          $this->Folder($fr, $to);  // recurse subfolder
        } else {
          $this->File($fr, $to, $skp);    // pack the file
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
      if (!isset($this->pks[$tpe[0]]) || !$this->Addons($tpe)) {
        $tpe[0] = null; // no add-on
      } else if (array_search('php', $tpe[1]) !== false) {
        $tpe[2] = 'php';
      } else if (isset($tpe[1][0]) && mb_strpos($tpe[1][0], '!') !== false) {
        $tpe[0] = mb_substr($tpe[1][0], 1);
      }
      $tpe[1] = $t;
    }
    if ($tpe[0] && is_null($this->PackCheck(basename($src), $this->set->exf))) { // file to pack
      $str = @file_get_contents($src);
      if (is_string($str)) {  // source obtained 
        $lc = mb_substr_count($str, "\n") + 1;
        $lp = mb_strlen($str);
        $str = $this->Minify($str, $tpe, $dst);
        if (is_string($str)) {  // source is packed
          $la = mb_strlen($str);
          $str = is_int(@file_put_contents($dst, $str));
        }
      }
      if (is_array($str)) {
        $this->Msg('sri', "$dst - $str[0]"); // content error encountered
      } else if ($str) { // destination is saved
        ++$this->cnt[0];
        ++$this->cnt[1];
        $this->cnt[2] += $lc;
        $this->cnt[3][0] += (float) $lp;
        $this->cnt[3][1] += (float) $la;
      } else {
        $this->Msg('nop', $dst); // error encountered
      }
    } else if ($src == $dst || @copy($src, $dst)) { // copy non-packing file
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
    $this->ans[$tpe[0]]['arr'][0] = $str;  // source string
    $this->ans[$tpe[0]]['arr'][1]['fle'] = $fle;  // source file
    $rlt = call_user_func_array($this->ans[$tpe[0]]['fnc'], $this->ans[$tpe[0]]['arr']);  // minify code
    if (is_string($rlt) && $tpe[2]) {  // may contain php
      $this->ans[$tpe[2]]['arr'][0] = $rlt;
      $this->ans[$tpe[2]]['arr'][1]['fle'] = $fle;  // source file
      $rlt = call_user_func_array($this->ans[$tpe[2]]['fnc'], $this->ans[$tpe[2]]['arr']);  // minify embedded code
    }
    if (is_string($rlt)) {  // minified ok
      ++$this->pks[$tpe[1]];
      if ($this->smp && ($tpe[0] == 'css' || $tpe[0] == 'js')) {
        $rlt = $this->smp . $rlt; // prepend the signature
      } else if ($this->smp && mb_strpos($rlt, '<?php  ?>') === 0) {
        $rlt = "<?php $this->smp ?>" . mb_substr($rlt, mb_strlen('<?php  ?>')); // replace empty php tag with a signature
      } else if ($tpe[0] == 'php' && ($rlt == '' || $this->smp)) {  // empty result or signature
        $rlt = "<?php $this->smp ?>" . $rlt; // prepend php signature
      }
    }
    return $rlt;
  }

  /**
   * not implemented here
   */
  protected function Obfuscate() {
    
  }

  /**
   * foreign request
   * @return string warning
   */
  protected function Request() {
    $this->Msg('noe', $this->nms[2]); // no extension
    return $this->rlt[1];
  }

  /**
   * include the addons for the file type
   * @param array $tpe -- [primary type,secondary types]
   * @return bool -- true - loaded
   */
  private function Addons($tpe) {
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
      $nme = $this->nms[1] . mb_strtoupper($t); //class name
      if (!$this->Loader($nme, $this->pth[1], $this->nms[0])) {
        $this->pks[$t] = null; //mark n/a
        $flg = $f;
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
    $sec = microtime(true) - $this->tme[0]; // elapsed secs
    $this->tme = [$this->Date(), $sec]; // time started, elapsed
    $d = ($this->cnt[3][0] - $this->cnt[3][1]) / $this->cnt[3][0];
    $this->cnt[3] = number_format(100 * $d, 0) . '%';
    ksort($this->pks);
    $this->cnt[] = $this->pks; // packed by type
    list($tot, $min, $lns, $cmr, $tps) = $this->cnt;
    $str = "$this->sgr - {$this->txt[$this->rlt[0]]} {$this->tme[0]}"; // success message
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
      $str .= "\n\t{$this->txt['cpy']}: $c";
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
    $this->rlt[2] = [$this->cnt, $this->obs, $this->tme]; // save the counters
    return $str;
  }

  /**
   * startup date
   * @return string
   */
  private function Date() {
    $z = date_default_timezone_get(); // save user zone
    date_default_timezone_set('UTC'); // work in utc
    $d = date('Y-m-d H:i', $this->tme[0]) . ' UTC'; // timestamp
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
    $this->rlt[1] = "$this->sgr - " . $this->txt['err']; // save message
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
      'noe' => ['Extension required',
          'Visit http://vregistry.com'],
      'nop' => 'Cannot pack',
      'noc' => 'Cannot create',
      'nob' => 'Cannot obfuscate',
      'noa' => 'Unsuccessful archiving',
      'lns' => 'Lines processed',
      'cmr' => 'Compression ratio',
      'src' => 'From',
      'dst' => 'To',
      'fmt' => 'Files minified/total',
      'obf' => 'Files (js+php) obfuscated',
      'irr' => 'Identifiers (php) registered/replaced',
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

  protected $ver = '0.1.0';
  protected $tkn = 'Lte'; /* product token */
  protected $opt = [  /* initial options */
      'sgn' => '', /* signature: null - suppress */
      'exf' => ['*.min.*'], /* files packing exclusion */
      'sfx' => '_pkd',  /* default destination name suffix */
      'aon' => [], /* user add-ons */
      'arc' => 'zip',   /* archives type */
      'tml' => 30  /*  time limit in seconds a script is allowed to run */
      ];

  /**
   * 
   * @param int $lvl -- obfuscation:
   *                      0|false - no
   *                      1|true - JS
   * @param array $opt
   */
  public function __construct($lvl = 0, $opt = []) {
    parent::__construct((int) $lvl, (array) $opt);
  }

  /**
   * foreign action
   */
  public static function Action() {
    $obj = new PackApp(-1);
    $rsp = str_replace("\n", '<br>', $obj->Request([], false)); // display in html
    header("Content-Type: text/html; charset=utf-8");
    return $rsp;
  }

}

if (count(get_included_files()) == 1) {
  echo PackApp::Action(); //called outside
}
