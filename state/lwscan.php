<?php
$s = file_get_contents('/var/www/html/vendor/livewire/livewire/dist/livewire.js');
$needle = 'directive("click"';
$i = strpos($s, $needle);
echo "click directive @ $i\n";
if ($i === false) {
  $i = strpos($s, 'on("click"');
  echo "on click @ $i\n";
}
$positions = array();
$pos = 0;
while (($j = strpos($s, 'click', $pos)) !== false && count($positions) < 15) {
    $chunk = substr($s, max(0,$j-30), 80);
    if (str_contains($chunk, 'directive') || str_contains($chunk, 'wire:click') || str_contains($chunk, 'onClick')) {
        $positions[] = $j;
        echo "@$j ".$chunk."\n";
    }
    $pos = $j + 5;
}
echo "--- parent handler ---\n";
$k = strpos($s, 'startsWith("$parent")');
echo substr($s, max(0,$k-500), 900), "\n";
