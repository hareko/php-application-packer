<?php

trait Hello {

  public function sayHello() {
    echo 'Hello ';
  }

}

trait World {

  public static function sayWorld() {
    echo 'World';
  }

}

class MyHelloWorld {

  use Hello,
      World;

  const EM = '!';

  private $em;

  public function __construct() {
    $this->em = self::EM;
  }

  public static function Single(){
    $obj = new self();
  }

  public function sayExclamationMark() {
    echo $this->em;
  }

}

MyHelloWorld::Single();
$o = new MyHelloWorld();
$o->sayHello();
MyHelloWorld::sayWorld();
$o->sayExclamationMark();
$u = 'http://heredoc.com/';
$h = <<<EOT
        <br><a href='$u' target='_blank'>$u</a>
        <br>Use that instead of ' & " delimiters  
EOT;
echo $h;
