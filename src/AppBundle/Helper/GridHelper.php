<?php

namespace AppBundle\Helper;

class GridHelper
{
    public static 
        $logger,
        $em,
        $connection;

    public function __construct($logger, $doctrine)
    {
        self::$logger = $logger;
        self::$em = $doctrine->getManager();
        self::$connection = self::$em->getConnection();
    }

    public static function isBlank($i, $j, $grid)
    {
        $val = $grid[$i][$j];

        return !empty($val['blank']);
    }

    public static function getGrid($name)
    {
        $sql = '
        select * from grids G
        inner join cells C ON C.grid_id = G.id
        where G.name = "' . $name . '"
        ORDER BY row, col';
        $connection = self::$connection;
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        $anchors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $height = 1 + $anchors[0]['height'];
        $width = 1 + $anchors[0]['width'];
        $default_cell = [
            'display' => null,
            'is_data' => true,
            'choices' => [],
        ];
        $cells = array_fill(0, $width * $height, $default_cell);
        foreach ($anchors as $anchor) {
            $row = $anchor['row'];
            $col = $anchor['col'];
            $cells[$row * $width + $col]['display'] = [(int)$anchor['label_v'], (int)$anchor['label_h']];
            $cells[$row * $width + $col]['is_data'] = false;
        }

        return [
            'height' => $height,
            'width' => $width,
            'cells' => array_values($cells),
        ];
    }

    public static function hstrip($i, $j, $grid)
    {
        if (self::isBlank($i, $j, $grid)) {
            return [];
        }

        $j_start = self::getStripStartH($i, $j, $grid);
        $j_stop = self::getStripStopH($i, $j, $grid);

        $h = [];
        for ($k = $j_start; $k <= $j_stop; $k++) {
            $h[$k] = $grid[$i][$k];
        }

        return $h;
    }

    public static function vstrip($i, $j, $grid)
    {
        if (self::isBlank($i, $j, $grid)) {
            return [];
        }

        $i_start = self::getStripStartV($i, $j, $grid);
        $i_stop = self::getStripStopV($i, $j, $grid);
        $v = [];
        for ($k = $i_start; $k <= $i_stop; $k++) {
            $v[$k] = $grid[$k][$j];
        }

        return $v;
    }

    public static function getStrip($i, $j, $grid, $dir, $sum)
    {
        if (self::isBlank($i, $j, $grid)) {
            return null;
        }

        $strip = $dir === 'h' ? self::hstrip($i, $j, $grid) : self::vstrip($i, $j, $grid);
        $start_i = $dir === 'v' ? key($strip) : $i;
        $start_j = $dir === 'h' ? key($strip) : $j;

        return [
            'dir' => $dir,
            'total' => $sum,
            'len' => count($strip),
            'start' => [$start_i, $start_j],
        ];
    }

    public static function getStripStartH($i, $j, $grid)
    {
        $j_start = $j;
        while (true) {
            $x = $j_start - 1;
            if ($x >= 0 && !self::isBlank($i, $x, $grid)) {
                $j_start--;
            } else {
                break;
            }
        }

        return $j_start;
    }

    public static function getStripStopH($i, $j, $grid)
    {
        $j_stop = $j;
        $width = count($grid);
        while (true) {
            $x = $j_stop + 1;
            if ($x < $width && !self::isBlank($i, $x, $grid)) {
            $j_stop++;
            } else {
                break;
            }
        }

        return $j_stop;
    }

    public static function getStripStartV($i, $j, $grid)
    {
        $i_start = $i;
        while (true) {
            $x = $i_start - 1;
            if ($x >= 0 && !self::isBlank($x, $j, $grid)) {
                $i_start--;
            } else {
                break;
            }
        }

        return $i_start;
    }

    public static function getStripStopV($i, $j, $grid)
    {
        $i_stop = $i;
        $height = count($grid[0]);
        while (true) {
            $x = $i_stop + 1;
            if ($x < $height && !self::isBlank($x, $j, $grid)) {
                $i_stop++;
            } else {
                break;
            }
        }

        return $i_stop;
    }

    public static function populateDesignChoices($cells, $height, $width)
    {
        foreach ($cells as $idx => $cell) {
            if (!empty($cell['is_data']) && count($cell['choices']) !== 1) {
                $cells[$idx]['choices'] = self::designChoices($cell, $cells, $height, $width);
            }
        }

        $cells = self::filterNumsThatCauseNonUnique($cells, $height, $width);

        return $cells;
    }

    public static function taken($cell, $cells, $height, $width)
    {
        $strips = self::strips($cell, $cells, $height, $width);

        $taken = [];

        foreach ($strips as $strip) {
            foreach ($strip as $stripCell) {
                if ($stripCell['idx'] === $cell['idx'] || count($stripCell['choices']) !== 1) {
                    continue;
                }

                $val = $stripCell['choices'][0];
                if (!in_array($val, $taken)) {
                    $taken[] = $val;
                }
            }
        }

        return $taken;
    }

    public static function designChoices($cell, $cells, $height, $width)
    {
        $numberSet = [1,2,3,4,5,6,7,8,9];
        $taken = self::taken($cell, $cells, $height, $width);
        return array_values(array_diff($numberSet, $taken));
    }

    public static  function filterNumsThatCauseNonUnique($cells, $height, $width)
    {
        // get all h strips
        $hStrips = [];
        $indexedStrips = [];

        // add strips key data cells:
        foreach ($cells as $idx => $cell) {
            if (empty($cell['is_data'])) {
                continue;
            }

            $strips = self::strips($cell, $cells, $height, $width);
            $sh = $strips['h'];
            $sv = $strips['v'];
            $ih = self::stripIndex($sh);
            $iv = self::stripIndex($sv);
            $cells[$idx]['strips'] = ['h' => $ih, 'v' => $iv];
        }

        // build strip indexes:
        foreach ($cells as $idx => $cell) {
            if (empty($cell['is_data'])) {
                continue;
            }

            // have to do this again here to get the updated cells from above
// note -- use idxs instead of cells in the strips big dummy then ya don't have do so much work
            $strips = self::strips($cell, $cells, $height, $width);
            $sh = $strips['h'];
            $sv = $strips['v'];
            $ih = self::stripIndex($sh);
            $iv = self::stripIndex($sv);
            if (!in_array($ih, array_keys($indexedStrips))) {
                $indexedStrips[$ih] = $sh;
                $hStrips[] = $sh;
            }
            if (!in_array($iv, array_keys($indexedStrips))) {
                $indexedStrips[$iv] = $sv;
            }
        }

        // get swappable subsets -- 2x2, must have exactly one empty cell
        $swappableSubsets = [];
        foreach ($hStrips as $strip) {
            foreach ($strip as $cell1) {
                $vStrip1 = $indexedStrips[$cell1['strips']['v']];
                foreach ($strip as $cell2) {
                    if ($cell2['row'] <= $cell1['row'] && $cell2['col'] <= $cell1['col']) {
                        continue;
                    }
                    if (count($cell1['choices']) !== 1 && count($cell2['choices']) !== 1) {
                        continue;
                    }
                    $w1 = [$cell1['idx'], $cell2['idx']];
                    $vStrip2 = $indexedStrips[$cell2['strips']['v']];
                    $w2s = self::getSwappableMatches($cell1, $cell2, $vStrip1, $vStrip2, $cells);
                    if (!empty($w2s)) {
                        foreach ($w2s as $w2) {
                            $swappableSubsets[] = [$w1, $w2];
                        }
                    }
                }

            }
        }

// self::log('jbf');
// self::log(json_encode($swappableSubsets));
        foreach ($swappableSubsets as $swappableSubset) {
            $cells = self::filterNumsThatCauseSwap2($swappableSubset, $indexedStrips, $cells, $height, $width);
        }

        $cells = self::filterNumsThatCauseSwap3($cells, $indexedStrips, $height, $width);

        // $available = $cell['choices'];
        return $cells;
    }

    public static function getSwappableMatches($cell1, $cell2, $vStrip1, $vStrip2, $cells)
    {
        $matches = [];
        $hasEmpty = count($cell1['choices']) !== 1 || count($cell2['choices']) !== 1;
        foreach ($vStrip1 as $cell3) {
            if (count($cell3['choices']) !== 1) {
                if ($hasEmpty) {
                    continue;
                }

                $hasEmpty = true;
            }
            if ($cell3['row'] <= $cell1['row'] && $cell3['col'] <= $cell1['col']) {
                continue;
            }
            foreach ($vStrip2 as $cell4) {
                if (count($cell4['choices']) !== 1) {
                    if ($hasEmpty) {
                        continue;
                    }
                }
                if ($cell4['row'] !== $cell3['row'] && $cell4['col'] !== $cell3['col']) {
                    continue;
                }
                if (self::connected($cell3, $cell4, $cells)) {
                    $matches[] = [$cell3['idx'], $cell4['idx']];
                }
            }
        }

        return $matches;
    }

    public static function connected($cell1, $cell2, $cells)
    {
        return $cell1['strips']['h'] === $cell2['strips']['h'] || $cell1['strips']['v'] === $cell2['strips']['v'];
    }

    public static  function filterNumsThatCauseSwap2($swappableSubset, $indexedStrips, $cells, $height, $width)
    {
        foreach ($swappableSubset as $outerIdx => $pair) {
            foreach ($pair as $innerIdx => $cellIdx) {
                $cell = $cells[$cellIdx];
                if (count($cell['choices']) !== 1) {
                    // X is unknown; b is diagonal to X; a and c are the others
                    $X = $cell;
                    // $a is X's horisontal partner
                    $a = $cells[$pair[abs($innerIdx - 1)]];
                    $otherPair = $swappableSubset[abs($outerIdx - 1)];
                    // $c has the same col as X so it should have the same inner idx as X
                    $c = $cells[$otherPair[$innerIdx]];
                    $b = $cells[$otherPair[abs($innerIdx - 1)]];
                    break 2;
                }
            }
        }

        if (empty($X)) {
            return $cells;
        }

self::log('X is ('.$X['row'].','.$X['col'].') choices: ' . json_encode($cells[$X['idx']]['choices']));
        // get available options for each:
        $a = self::setAvailable($a, $indexedStrips, [$b['idx']]);
        $b = self::setAvailable($b, $indexedStrips, [$a['idx'], $c['idx']]);
        $c = self::setAvailable($c, $indexedStrips, [$b['idx']]);
        $X = self::setAvailable($X, $indexedStrips, [$a['idx'], $c['idx']]);

        $choicesToUnset = [];
        // see what values of X we can have. also need to unset by similar sums(?) and other filters
        foreach ($X['choices'] as $choiceIdx => $candidate) {
            if (self::failTests($candidate, $a['choices'][0], $b['choices'][0], $c['choices'][0], $X['available'], $a['available'], $b['available'], $c['available'])) {
                $choicesToUnset[] = $candidate;
            }
        }
self::log('unset '.json_encode($choicesToUnset));
        $newChoices = [];
        foreach ($X['choices'] as $candidate) {
            if (!in_array($candidate, $choicesToUnset)) {
                $newChoices[] = $candidate;
            }
        }

        $X['choices'] = $newChoices;
        $cells[$X['idx']] = $X;
self::log('('.$X['row'].','.$X['col'].') choices: ' . json_encode($cells[$X['idx']]['choices']));
        return $cells;
    }

    public static function setAvailable($cell, $indexedStrips, $ignore)
    {
        $numberSet = [1,2,3,4,5,6,7,8,9];
        $taken = [];
        foreach ($cell['strips'] as $stripIdx) {
            $strip = $indexedStrips[$stripIdx];
            foreach ($strip as $stripCell) {
                if ($stripCell['idx'] === $cell['idx'] || count($stripCell['choices']) !== 1) {
                    continue;
                }
                if (in_array($stripCell['idx'], $ignore)) {
                    continue;
                }
                $taken[] = $stripCell['choices'][0];
            }
        }

        $cell['available'] = array_values(array_diff($numberSet, $taken));
        return $cell;
    }

    public static function failTests($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        if ($a === $c && $b === $X) {
            return true;
        }

        if (self::failJuggleTest($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
            return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 'juggle');
        }

        return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 0, false);
    }

    // public static function failTests($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    // {
    //     if ($a === $c && $b === $X) {
    //         return true;
    //     }

    //     if (self::failTest1($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
    //         return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 1);
    //     }

    //     if (self::failTest2($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
    //         return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 2);
    //     }

    //     if (self::failTest3($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
    //         return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 3);
    //     }

    //     if (self::failTest4($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
    //         return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 4);
    //     }

    //     return self::logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, 0, false);
    // }

    public static function logTestResult($X, $a, $b, $c, $setX, $setA, $setB, $setC, $testNumber, $result = true)
    {
// return $result;
        if ($result) {
            self::log('failed testNumber = '. $testNumber);
        } else {
            self::log('passed all tests');
        }
        self::log('X = '. $X);
        self::log('a = '. $a);
        self::log('b = '. $b);
        self::log('c = '. $c);
        self::log('setX = '. json_encode($setX));
        self::log('setA = '. json_encode($setA));
        self::log('setB = '. json_encode($setB));
        self::log('setC = '. json_encode($setC));
        return $result;
    }

    public static function failJuggleTest($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        $testResult = false;
        // get possibilities for ab and cb
        $stripsToJuggle = self::stripsToJuggle($X, $a, $b, $c, $setX, $setA, $setB, $setC);
        if (count($stripsToJuggle[0]) <= count($stripsToJuggle[1])) {
            $strips = $stripsToJuggle[0];
        } else {
            $strips = $stripsToJuggle[1];
            $tmp = $a;
            $a = $c;
            $c = $tmp;
            $tmp = $setA;
            $setA = $setC;
            $setC = $tmp;
        }

        return self::failXXXTest($strips, $X, $a, $b, $c, $setX, $setA, $setB, $setC);
    }

    public static function failXXXTest($stripsAB, $X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        if (empty($stripsAB)) {
            return false;
        }

        foreach ($stripsAB as $strip) {
            $h = $strip[0];   // h X*
            $i = $strip[1];   // i c*
            $Xp = $X + $a - $h;
            $cp = $c + $b - $i;
self::log("$X, $a, $b, $c, $h, $i, $Xp, $cp");
            if (!in_array($Xp, $setX)) {
self::log("log 1");
                break;
            }
            if (!in_array($cp, $setC)) {
self::log("log 2");
                break;
            }
            if ($cp === $Xp) {
self::log("log 3");
                break;
            }
            if ($cp === $i) {
self::log("log 4");
                break;
            }
            if ($Xp === $h) {
self::log("log 5");
                break;
            }
self::log("log 6");
            return true;
        }
        return false;
    }

    public static function stripsToJuggle($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        $sumAB = $a + $b;
        $pairsAB = [];
        foreach ($setA as $candA) {
            $candB = $sumAB - $candA; 
            if (in_array($candB, $setB) && $candA !== $a) 
                $pairsAB[] = [$candA, $candB];
        }

        $sumCB = $c + $b;
        $pairsCB = [];
        foreach ($setC as $candC) {
            $candB = $sumCB - $candC; 
            if (in_array($candB, $setB) && $candC !== $c) 
                $pairsCB[] = [$candC, $candB];
        }

        return [$pairsAB, $pairsCB];
    }

    public static function filterNumsThatCauseSwap3($cells, $indexedStrips, $height, $width)
    {
        // each posible val (a) -- temporarily set cell to val a, consider his strips:
        //     find common vals, for each one (b):
        //         (add b's strips if they contain a
        //             each "a" cell in those strips, add their strips if they contain b) recurse
        //         count the a's and b's in the strips. if ==, can't set to a
self::log('swap3');
        foreach($cells as $cellIdx => $cell) {
            if (empty($cell['is_data']) || count($cell['choices']) === 1) {
                continue;
            }

            $strips = self::strips($cell, $cells, $height, $width); // use what is passed in

            $xx = [];
            $available = $cell['choices'];
            foreach ($available as $choiceIdx => $choice) {
                $cell['choices'] = [$choice];
                $xx = [];
                $commonValuedCellPairs = self::interesectByValue($strips); // not yet, no value!
self::log('cell '.json_encode($cell));
self::log('prs '.json_encode($commonValuedCellPairs));
                if (empty($commonValuedCellPairs)) {
                    continue;
                }
                $xx[] = $cell;
                foreach ($commonValuedCellPairs as $pair) {
                    $xx = self::findConnectedStripsMutuallyContaining($cell, $pair, $xx, $indexedStrips);
                }
self::log('csmc '.json_encode($xx));

                if (count($xx) > 2 && !(count($xx) % 2)) {
                    unset($available[$choiceIdx]);
                }
            }

            $cells[$cellIdx]['choices'] = $available;
        }



        return $cells;
    }

    public static function findConnectedStripsMutuallyContaining($cell1, $pair, $cells, $indexedStrips, $d = 0)
    {
// $d prevent runaway
self::log('d '.$d);
if ($d > 5) {
    return $cells;
}
self::log('('.$cell1['row'].','.$cell1['col'].')');
self::log('pr ' . json_encode($pair));
        $val1 = $cell1['choices'][0]; // bad, must go thru available
        foreach ($pair as $cell) {
            foreach ($cells as $c) {
                if ($c['idx'] == $cell['idx']) {
                    continue;
                }
            }

            // cell2 get strips. see if both contain cell1 val. if yes, add to cells (if not already there) and recurse
            $pairForRecurse = [];
            $strips = [$indexedStrips[$cell['strips']['h']], $indexedStrips[$cell['strips']['v']]];
            foreach ($strips as $strip) {
                foreach ($strip as $c) {
                    $found = false;
                    $choice = $c['choices'][0];
                    if ($choice == $val1) {
                        $found = true;
                        $pairForRecurse[] = $c;
                        continue 2; // next strip
                    }
                }
                if (!$found) {
                    continue 2; // went thru all cells, value not there. next cell in the pair please
                }
            }

            $cells[] = $cell;
self::log('would recurse here ' . '('.$cell['row'].','.$cell['col'].')   ' . json_encode($pairForRecurse));
// return $cells; // tbi
            $cells = self::findConnectedStripsMutuallyContaining($cell, $pairForRecurse, $cells, $indexedStrips, $d+1);
        }

        return $cells;
    }

    public static function failTest1($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // H-strip permutation
        // X valid (a)
        // a valid (X)
        // a+b-X valid in setB
        // b+c-(a+b)+X -> c-a+X valid in setC
        // X != a+b-X -> X != (a+b)/2
        // a+b-X != c-a+X -> X != (2a+b-c)/2
        // c-a+X != a -> X != 2a-c

        if (!in_array($X, $setA)) {
            return false;
        }

        if (!in_array($a, $setX)) {
            return false;
        }

        if (!in_array($a+$b-$X, $setB)) {
            return false;
        }

        if (!in_array($X+$c-$a, $setC)) {
            return false;
        }

        if ($X == ($a+$b)/2) {
            return false;
        }

        if ($X == 2*$a-$c) {
            return false;
        }

        if ($X == 2*$a-2*$b-$c) {
            return false;
        }

        return true;
    }

    public static function failTest2($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // V-strip permutation
        // c valid (X)
        // X valid (c)
        // b+c-X valid
        // a-c+X valid
        // X != (b+c)/2
        // X != (2c+b-a)/2
        // X != 2c-a

        if (!in_array($c, $setX)) {
            return false;
        }

        if (!in_array($X, $setC)) {
            return false;
        }

        if (!in_array($b+$c-$X, $setB)) {
            return false;
        }

        if (!in_array($X+$a-$c, $setA)) {
            return false;
        }

        if ($X == ($c+$b)/2) {
            return false;
        }

        if ($X == 2*$c-$a) {
            return false;
        }

        if ($X == (2*$c+$b-$a)/2) {
            return false;
        }

        return true;
    }

    public static function failTest3($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // H-neighbor permutation
        // c valid (b)
        // b valid (c)
        // a+b-c valid for a's position
        // X+c-b valid for X's position
        // c != (a+b)/2
        // X != 2b-c
        // X != 2b-2c+a

        if (!in_array($c, $setB)) {
            return false;
        }

        if (!in_array($b, $setC)) {
            return false;
        }

        if (!in_array($a+$b-$c, $setA)) {
            return false;
        }

        if (!in_array($X+$c-$b, $setX)) {
            return false;
        }

        if ($c == ($a+$b)/2) {
            return false;
        }

        if ($X == 2*$b-$c) {
            return false;
        }

        if ($X == 2*$b-2*$c+$a) {
            return false;
        }

        return true;
    }

    public static function failTest4($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // V-neighbor permutation
        // b valid (a)
        // a valid (b)
        // b+c-a valid (c)
        // X+a-b valid (X)
        // a != (b+c)/2
        // X != 2b-a
        // X != 2b-2a+c

        if (!in_array($b, $setA)) {
            return false;
        }

        if (!in_array($a, $setB)) {
            return false;
        }

        if (!in_array($c+$b-$a, $setC)) {
            return false;
        }

        if (!in_array($X+$a-$b, $setX)) {
            return false;
        }

        if ($a == ($c+$b)/2) {
            return false;
        }

        if ($X == 2*$b-$a) {
            return false;
        }

        if ($X == 2*$b-2*$a+$c) {
            return false;
        }

        return true;
    }

    public static function getImmediateNeighbors($cell, $cells, $height, $width)
    {
        $idx = $cell['idx'];

        $nbrs = [
            'top' => null,
            'bottom' => null,
            'left' => null,
            'right' => null,
        ];

        if ($idx >= $width) {
            $nbrs['top'] = $cells[$idx - $width];
        }
        if ($idx < ($height - 1) * $width) {
            $nbrs['bottom'] = $cells[$idx + $width];
        }
        if ($idx % $width) {
            $nbrs['left'] = $cells[$idx - 1];
        }
        if ($idx % $width < $width - 1) {
            $nbrs['right'] = $cells[$idx + 1];
        }

        return $nbrs;
    }

    public static function interesectByValue($strips) // takes 2 strips
    {
self::log('strips '.json_encode($strips));
        $cells = [];
        $strips = array_values($strips);
        if (empty($strips[0]) || empty($strips[1])) {
            return [];
        }

        foreach ($strips[0] as $cell) {
            if (count($cell['choices']) !== 1) {
                continue;
            }
            $choice = $cell['choices'][0];
self::log('choice '.json_encode($choice));
            if (!$choice) {
                continue;
            }
            foreach ($strips[1] as $vCell) {
                if (count($vCell['choices']) !== 1) {
                    continue;
                }
                if ($vCell['choices'][0] === $choice) {
                    $cells[] = [$cell, $vCell];
                    continue 2;
                }
            }
        }

        return $cells;
    }

    public static function strips($cell, $cells, $height, $width)
    {
        $isDataCell = !empty($cell['is_data']);
        $nbrs = [];

        // vertical
        // walk up to nearest non-data
        $strip = [];
        $i = $cell['idx'] - $width;
        while ($i > 0) {
            if (!($cells[$i]['is_data'])) {
                break;
            } else {
                $strip[] = $cells[$i];
            }
            $i = $i - $width;
        }

        if (!$isDataCell) {
            $nbrs['v'] = $strip;
            $strip = [];
        } else {
            $strip[] = $cell;
        }

        // walk down to nearest non-data
        $i = $cell['idx'] + $width;
        while ($i < $height * $width) {
            if (!($cells[$i]['is_data'])) {
                break;
            } else {
                $strip[] = $cells[$i];
            }
            $i += $width;
        }
        $nbrs['v'] = $strip;

        // horizontal
        // walk left to nearest non-data
        $i = $cell['idx'] - 1;
        $strip = [];
        while ($i % $width !== $width - 1) { // walk until you have wrapped
            if (!($cells[$i]['is_data'])) {
                break;
            } else {
                $strip[] = $cells[$i];
            }
            $i = $i - 1;
        }

        if (!$isDataCell) {
            $nbrs['h'] = $strip;
            $strip = [];
        } else {
            $strip[] = $cell;
        }
     
        // walk right to nearest non-data
        $i = $cell['idx'] + 1;
        while ($i % $width) {
            if (!($cells[$i]['is_data'])) {
                break;
            } else {
                $strip[] = $cells[$i];
            }
            $i += 1;
        }
        $nbrs['h'] = $strip;

        return $nbrs;
    }

    public static function stripIndex($strip)
    {
        $isHorizontal = true;
        $minRow = null;
        $minCol = null;
        $prevRow = null;
        forEach($strip as $cell) {
            if ($minRow === null || $cell['row'] < $minRow) {
                $minRow = $cell['row'];
            }

            if ($minCol === null || $cell['col'] < $minCol) {
                $minCol = $cell['col'];
            }

            if ($prevRow !== null && $cell['row'] != $prevRow) { 
                $isHorizontal = false;
            }

            $prevRow = $cell['row'];
        }

        $orientation = $isHorizontal ? '_h' : '_v';
        $stripStart =  $minRow . '_' . $minCol ;

        return $stripStart . $orientation;
    }

    // move to base process class
    public static function pickRandom($arr)
    {
        $keys = array_keys($arr);
        $index = rand(1, count($keys)) - 1;
        return $keys[$index];
    }

    public static function log($str, $level = 'notice')
    {
        if (is_array($str))
        {
            $str = json_encode($str);
        }
        $accepted_levels = array(
            'emergency',
            'alert',
            'critical',
            'warning',
            'notice',
            'info',
            'debug'
        );

        if (!in_array($level, $accepted_levels))
        {
            $level = 'info';
        }
        self::$logger->$level($str);
    }

    public static function getConnection()
    {
        return self::$connection;
    }

    public static function getEm()
    {
        return self::$em;
    }

    public function onKernelRequest($event)
    {
        return;
    }

    public function onConsoleCommand($event)
    {
        return;
    }
}