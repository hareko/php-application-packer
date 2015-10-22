<?php

/**
 * Minify XML source
 *
 * @package Packer
 * @author Vallo Reima
 * @copyright (C)2015
 */
class PackXML {

  private $input;
  private $nodes = [];  /* node objects to remove */

  /**
   * @param string $source
   * @param array $options
   * @return mixed -- string - ok
   */
  public static function minify($source, $options = []) {
    $min = new self($source);
    return $min->process();
  }

  /**
   * @param string $input
   */
  public function __construct($input) {
    $this->input = $input;
  }

  /**
   * minify
   * @return string|false
   */
  private function process() {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    if (@$dom->loadXML($this->input)) {
      $this->Detect($dom);  // fix excessive nodes
      foreach ($this->nodes as $node) {
        $node->parentNode->removeChild($node);  // remove fixed nodes
      }
      $rlt = $dom->saveXML(); // convert to string
    } else {  // bad content
      $rlt = false;
    }
    return $rlt;
  }

  /**
   * collect excessive node objects
   * @param object $root
   */
  private function Detect($root) {
    foreach ($root->childNodes as $node) {
      if ($node->nodeType == XML_COMMENT_NODE || ($node->nodeType == XML_TEXT_NODE && trim($node->nodeValue) == '')) {
        array_push($this->nodes, $node);  // comment or empty text
      } else if ($node->nodeType == XML_ELEMENT_NODE) {
        $this->Detect($node); // recurse subnodes
      }
    }
  }

}
