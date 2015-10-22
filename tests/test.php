<?php
/**
 * testing
 */
define ( "BR", '<br />', true);  /* line break */
function t($c){
  return "count: " . count($c);
}

$a = 'vv';
$$a = ['v' => 'r'];
foreach ($vv as $b => $c) {
  echo "$b => $c" . br;  // display
}

echo @t( $$a );

$o = new Test('ok');
print(BR . $o->Msg());

class Test {

  private $set;

  public function __construct($t) {
    $this->set = (object) ['msg' => "Test $t"];
  }

  public function Msg() {
    return $this->set->msg;
  }

}
