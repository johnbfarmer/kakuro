<?php

$f = fopen("tmp/x.kak", 'r');
$dsn = 'mysql:dbname=kakuro;host=127.0.0.1';
$user = 'root';
$password = 'root';

try {
    $dbh = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
$sql = 'delete from grids where name like "aaatest"';
$dbh->exec($sql);
$h = 0;
$cells = [];
$anchors = [];
$sum = 0;
$last_row = 0;
$last_col = 0;
while ($ln = fgets($f)) {
    $arr = explode('  ', trim($ln));
    if (empty($arr)) {
        continue;
    }
    $cells[] = $arr;
    $w = count($arr);
    $i = $h++;
    $anchors[$i] = [];
    // print $ln;
    // var_dump($arr);
    foreach ($arr as $j => $cell) {
        if ($cell === '.') {
            print "anchor at ($i, $j)\n";
            $anchors[$last_row][$last_col] = ['label_h' => $sum];
            $sum = 0;
            $last_row = $i;
            $last_col = $j;
        } else {
            $sum += $cell;
        }
    }
}

$anchors[$last_row][$last_col] = ['label_h' => $sum];

for ($j = 0; $j < $h; $j++) {
    for ($i = 0; $i < $w; $i++) {
        $cell = $cells[$i][$j];
        if ($cell === '.') {
            print "anchor at ($i, $j)\n";
            $anchors[$last_row][$last_col]['label_v'] = $sum;
            $sum = 0;
            $last_row = $i;
            $last_col = $j;
        } else {
            $sum += $cell;
        }
    }
}

$anchors[$last_row][$last_col]['label_v'] = $sum;

var_dump(json_encode($anchors[16]));
// exit;
$sql = 'insert ignore grids (name, width, height) values ("aaatest", '.$w.', '.$h.')';
$dbh->exec($sql);
$grid_id = $dbh->lastInsertId();
foreach ($anchors as $row => $row_anchors) {
    foreach ($row_anchors as $col => $anchor) {
        $sql = 'insert ignore cells (grid_id, row, col, label_h, label_v) values ('.$grid_id.', '.$row.', '.$col.', '.$anchor['label_h'].', '.$anchor['label_v'].')';
        print $sql."\n";
        $dbh->exec($sql);
    }
}