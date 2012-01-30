<?php

function generate_paragraph($length) {
  global $table, $n;
  $out = array();
  $ngram = array();
  $arr = $table;
  for ($i = 0; $i < $n - 1; $i++) {
    $target = array_rand($arr);
    $ngram[] = $target;
    $arr = &$arr[$target];
  }
  for ($i = 0; $i < $length; $i++) {
    $arr = &$table;
    for ($j = 0; $j < $n - 1; $j++) {
      $token = $ngram[$j];
      $arr = &$arr[$token];
    }
    $sum = array_sum($arr);
    $random = rand(0, $sum);
    $counter = 0;
    foreach ($arr as $token => $count) {
      $counter += $count;
      if ($counter >= $random) {
        $target = $token;
        break;
      }
    }

    $out[] = array_shift($ngram);
    array_push($ngram, $target);
  }
  $text = implode(' ', $out);
  $replacements = array(
    '  ' => ' ',
  );
  $text = strtr($text, $replacements);
  return $text;
}

function generate_html_element() {
  $html = array(
    'p' => 100,
    'span' => 100,
    'a' => 10,
    'h1' => 10,
    'h2' => 10,
  );
  $key = array_rand($html);

  return "<$key>" . generate_paragraph($html[$key]) . "</$key>";
}

function generate_html_page($minlength) {
  $text = '';
  while (strlen($text) < $minlength) {
    $text = $text . "\n". generate_html_element();
  }
  return $text;
}

srand((float) microtime() * 10000000);

require("en-galaxy-word-2gram.php");

$text = generate_html_page(10240);


