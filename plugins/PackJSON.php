<?php

/**
 * Minify json source
 * Based on php-json-minify by Tiste
 * 
 * @package Packer
 * @author Vallo Reima
 * @copyright (C)2015
 *
 * php-json-minify
 * @package JSONMin
 * @version 0.2.6
 * @link https://github.com/T1st3/php-json-minify
 * @author T1st3 <https://github.com/T1st3>
 * @license https://github.com/T1st3/php-json-minify/blob/master/LICENSE MIT
 * @copyright Copyright (c) 2014, T1st3
 * Based on JSON.minify (https://github.com/getify/JSON.minify) by Kyle Simspon (https://github.com/getify)
 * JSON.minify is released under MIT license.
 *
 */

/**
 * The JSONMin class
 * @author T1st3 <https://github.com/T1st3>
 * @since 0.1.0
 */
class PackJSON {

  /**
   * The original JSON string
   * @var string $original_json The original JSON string
   * @since 0.1.0
   */
  protected $original_json = '';

  /**
   * The minified JSON string
   * @var string $minified_json The minified JSON string
   * @since 0.1.0
   */
  protected $minified_json = '';

  /**
   * @param string $source
   * @param array $options
   * @return string
   */
  public static function minify($source, $options = []) {
    $min = new self($source);
    return $min->process();
  }

  /**
   * Constructor
   * @name __construct
   * @param string $json Some JSON to minify
   * @since 0.1.0
   */
  public function __construct($json) {
    $this->original_json = $json;
  }

  /**
   * @return string Minified JSON string
   */
  private function process() {
    $json = $this->original_json;
    $tokenizer = "/\"|(\/\*)|(\*\/)|(\/\/)|\n|\r/";
    $in_string = false;
    $in_multiline_comment = false;
    $in_singleline_comment = false;
    $tmp = $new_str = array();
    $from = 0;
    $rc = '';
    $lastIndex = 0;
    while (preg_match($tokenizer, $json, $tmp, PREG_OFFSET_CAPTURE, $lastIndex)) {
      $tmp = $tmp[0];
      $lastIndex = $tmp[1] + strlen($tmp[0]);
      $lc = substr($json, 0, $lastIndex - strlen($tmp[0]));
      $rc = substr($json, $lastIndex);
      if (!$in_multiline_comment && !$in_singleline_comment) {
        $tmp2 = substr($lc, $from);
        if (!$in_string) {
          $tmp2 = preg_replace("/(\n|\r|\s)*/", "", $tmp2);
        }
        $new_str[] = $tmp2;
      }else{
        $tmp2 = '';
      }
      $from = $lastIndex;
      if ($tmp[0] == "\"" && !$in_multiline_comment && !$in_singleline_comment) {
        preg_match("/(\\\\)*$/", $lc, $tmp2);
        if (!$in_string || !$tmp2 || (strlen($tmp2[0]) % 2) == 0) { // start of string with ", or unescaped " character found to end string
          $in_string = !$in_string;
        }
        $from--; // include " character in next catch
        $rc = substr($json, $from);
      } else if ($tmp[0] == "/*" && !$in_string && !$in_multiline_comment && !$in_singleline_comment) {
        $in_multiline_comment = true;
      } else if ($tmp[0] == "*/" && !$in_string && $in_multiline_comment && !$in_singleline_comment) {
        $in_multiline_comment = false;
      } else if ($tmp[0] == "//" && !$in_string && !$in_multiline_comment && !$in_singleline_comment) {
        $in_singleline_comment = true;
      } else if (($tmp[0] == "\n" || $tmp[0] == "\r") && !$in_string && !$in_multiline_comment && $in_singleline_comment) {
        $in_singleline_comment = false;
      } else if (!$in_multiline_comment && !$in_singleline_comment && !(preg_match("/\n|\r|\s/", $tmp[0]))) {
        $new_str[] = $tmp[0];
      }
    }
    $new_str[] = $rc;
    $this->minified_json = implode("", $new_str);
    return $this->minified_json;
  }
}
