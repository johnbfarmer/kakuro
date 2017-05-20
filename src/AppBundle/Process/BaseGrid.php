<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class BaseGrid extends BaseProcess
{
    protected 
        $grid = [],
        $number_set = [1,2,3,4,5,6,7,8,9],
        $sums = [],
        $hsums = [],
        $vsums = [],
        $hsizes = [],
        $vsizes = [],
        $width,
        $height,
        $previous_row_sum = [],
        $strips = [],
        $cells = [],
        $time_limit = 60,
        $start_time,
        $paths = [],
        $initial_grid = [],
        $solutions = [],
        $nonempty_cell_count = 0,
        $solutions_desired = 2,
        $table_builder,
        $input_file,
        $open_browser,
        $debug,
        $options_by_target_and_size = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->open_browser = !empty($this->parameters['browser']);
        $this->debug = !empty($this->parameters['debug']);
    }

    protected function execute()
    {
        $this->clearLog();
        $this->table_builder = new BuildTables(
            ['number_set' => $this->number_set,], $this->em
        );
    }

    protected function readInputFromDb()
    {
        $name = 'easy1';
        $name = pathinfo($this->input_file, PATHINFO_FILENAME);
        $sql = '
        select * from grids G
        inner join cells C ON C.grid_id = G.id
        where G.name = "' . $name . '"
        ORDER BY row, col';
        $cells = $this->fetchAll($sql, true);
        $this->height = $cells[0]['height'];
        $this->width = $cells[0]['width'];
        for ($k = 0; $k < $this->height; $k++) {
            $this->hsums[$k] = array_fill(0, $this->width, 0);
        }
        for ($k = 0; $k < $this->width; $k++) {
            $this->vsums[$k] = array_fill(0, $this->height, 0);
        }
        foreach($cells as $cell) {
            $row = $cell['row'];
            $col = $cell['col'];
            $anchor[$row][$col] = [(int)$cell['label_h'], (int)$cell['label_v']];
        }
        foreach($cells as $cell) {
            $row = $cell['row'];
            $col = $cell['col'];
            $hsum = $anchor[$row][$col][0];
            $vsum = $anchor[$row][$col][1];
            if ($vsum) {
                $r = $row + 1;
                $c = $col;
                while ($r <= $this->height && empty($anchor[$r][$c])) {
                    $this->vsums[$r-1][$c-1] = $vsum;
                    $r++;
                }
            }
            if ($hsum) {
                $r = $row;
                $c = $col + 1;
                while ($c <= $this->width && empty($anchor[$r][$c])) {
                    $this->hsums[$r-1][$c-1] = $hsum;
                    $c++;
                }
            }

        }
        $this->log('vsums');
        $this->log($this->vsums);
        $this->log('hsums');
        $this->log($this->hsums);
    }

    protected function readInputFile()
    {
        $file = $this->input_file;
        $fp = fopen($file, "r");
        if (!$fp) {
            throw new \Exception("problem with file " . $file);
        }
        $this->log("READING FILE " . $file, $this->debug);
        $first_row_done = false;

        while (($entry = fgetcsv($fp, 100, "\t")) !== false) {
            if (empty($this->width)) {
                if (!empty($entry)) {
                    $this->width = current($entry);
                }
                continue;
            }
            if (empty($this->height)) {
                if (!empty($entry)) {
                    $this->height = current($entry);
                }
                continue;
            }
            if (!$first_row_done) {
                if (!empty($entry)) {
                    $first_row_done = $this->processFirstRow($entry);
                }
                $linecount = 0;
                continue;
            }
            $this->processFileRow($entry, $linecount++);
            if ($linecount >= $this->height) {
                break;
            }
        }
        unset($this->vsums[-1]);
        $this->vsums = array_values($this->vsums);
        fclose($fp);
    }

    protected function openGridInBrowser()
    {
        if (empty($this->sums)) {
            $this->getSums();
        }

        if ($this->open_browser) {
            // $link = "http://localhost/kakuro/index.php?g=" . urlencode(json_encode($this->grid['cells'])) . "&s=" . urlencode(json_encode($this->sums)) . "&show=0";
            $f = pathinfo($this->input_file, PATHINFO_BASENAME);
            // $link = "http://localhost/kakuro/web/kakuro.php?f=$f";
            $link = "http://localhost/kakuro/index.php?f=$f";
            `open "$link"`;
        }
    }

    public function getSums()
    {
        $this->sums[0][0] = 0;
        for ($k=0; $k < $this->width; $k++) {
            $this->sums[0][$k + 1] = 0;
            if (!$this->isBlankCell($this->grid['cells'][0][$k])) {
                $sum = $this->getSum(0, $k);
                $this->sums[0][$k + 1] = $sum['v'] . '\\';
            }
        }
        for ($k=0; $k < $this->height; $k++) {
            $this->sums[$k + 1][0] = 0;
            if (!$this->isBlankCell($this->grid['cells'][$k][0])) {
                $sum = $this->getSum($k, 0);
                $this->sums[$k + 1][0] = '\\' . $sum['h'];
            }
        }
        for ($k=0; $k < $this->height; $k++) {
            for ($h=0; $h < $this->width; $h++) {
                if (!$this->isBlankCell($this->grid['cells'][$k][$h])) {
                    $this->sums[$k + 1][$h + 1] = 0;
                } else {
                    $sum_vert = $this->getSum($k + 1, $h)['v'];
                    $sum_hoz = $this->getSum($k, $h + 1)['h'];
                    // this is better for displaying but we meed the \ to know it is blank
                    // $connector = empty($sum_vert) && empty($sum_hoz) ? '' : '\\';
                    $connector = '\\';
                    if (empty($sum_vert)) {
                        $sum_vert = '';
                    }
                    if (empty($sum_hoz)) {
                        $sum_hoz = '';
                    }
                    $sum = $sum_vert . $connector . $sum_hoz;
                    $this->sums[$k + 1][$h + 1] = $sum;
                }
            }
        }
    }

    public function getSum($i, $j)
    {
        $strips = $this->findMyStrips($i, $j);
        $a = ['h' => 0, 'v' => 0];
        foreach ($strips as $dir => $strip) {
            foreach ($strip as $cell) {
                $a[$dir] += $cell;
            }
        }
        return $a;
    }

    protected function findMyStrips($i, $j, $include_empty = false)
    {
        $cells = $this->grid['cells'];
        if (empty($cells[$i][$j]) || $this->isBlankCell($cells[$i][$j])) {
            return []; // aint got no strips
        }

        $criteria = ['nonblank'];

        if ($include_empty) {
            $criteria[] = 'empty';
        }

        $i_start = $i;
        while (true) {
            $x = $i_start - 1;
            if ($x >= 0 && $this->meetsCriteria($x, $j, $criteria)) {
                $i_start--;
            } else {
                break;
            }
        }

        $i_stop = $i;
        while (true) {
            $x = $i_stop + 1;
            if ($x < $this->width && $this->meetsCriteria($x, $j, $criteria)) {
                $i_stop++;
            } else {
                break;
            }
        }

        $j_start = $j;
        while (true) {
            $x = $j_start - 1;
            if ($x >= 0 && $this->meetsCriteria($i, $x, $criteria)) {
                $j_start--;
            } else {
                break;
            }
        }

        $j_stop = $j;
        while (true) {
            $x = $j_stop + 1;
            if ($x < $this->width && $this->meetsCriteria($i, $x, $criteria)) {
            $j_stop++;
            } else {
                break;
            }
        }

        $h = []; $v = [];
        for ($k = $i_start; $k <= $i_stop; $k++) {
            $v[$k] = !empty($cells[$k][$j]['value']) ? $cells[$k][$j]['value'] : 0;
        }
        for ($k = $j_start; $k <= $j_stop; $k++) {
            $h[$k] = !empty($cells[$i][$k]['value']) ? $cells[$i][$k]['value'] : 0;
        }

        return ['h' => $h, 'v'=>$v];
    }

    protected function processFileRow($row, $line)
    {
        $this->hsums[$line] = array_fill(0, $this->width, 0);
        $this->vsums[$line] = array_fill(0, $this->width, 0);

        foreach ($row as $j => $cell) {
            if ($j > $this->width) {
                continue;
            }
            $vals = $this->parseMultipleSumInput($cell);
            if ($j == 0) {
                $previous_value = empty($cell) ? 0 : (int)$vals['h'];
                continue;
            }
            if (empty($cell)) {
                $this->hsums[$line][$j - 1] = $previous_value;
                $this->vsums[$line][$j - 1] = $this->vsums[$line - 1][$j-1] ?: 
                    (!empty($this->previous_row_sum[$line - 1][$j-1]) ? $this->previous_row_sum[$line - 1][$j-1] : 0);
                continue;
            }

            if ($vals['h']) {
                $this->hsums[$line][$j - 1] = 0;
                $previous_value = $vals['h'];
            } else {
                $this->hsums[$line][$j - 1] = 0;
            }
            if ($vals['v']) {
                $this->vsums[$line][$j - 1] = 0;
                $this->previous_row_sum[$line][$j - 1] = $vals['v'];
            } else {
                $this->vsums[$line][$j - 1] = 0;
            }
        }
    }

    protected function processFirstRow($row)
    {
        $this->vsums[-1] = array_fill(0, $this->width, 0);
        foreach ($row as $j => $cell) {
            if ($j === 0) {
                continue;
            }
            $this->vsums[-1][$j-1] = empty($cell) ? 0 : (int)$cell;
        }
        return true;
    }

    protected function parseMultipleSumInput($cell)
    {
        $vals = explode('\\', $cell);
        $v = !empty($vals[0]) ? (int)$vals[0] : 0;
        $h = !empty($vals[1]) ? (int)$vals[1] : 0;
        return ['v' => $v, 'h' => $h];
    }

    protected function validate()
    {
        $hs = $this->hsums;
        if (count($this->hsums) != count($this->vsums)) {
            $x = 'h: ' . count($this->hsums) . ' v: ' . count($this->vsums);
            throw new \Exception('bad input -- ' . $x);
        }

        $previous_array_size = 0;

        foreach ($this->hsums as $i => $row) {
            $array_size = count($row);
            if (empty($previous_array_size)) {
                $previous_array_size = $array_size;
            }
            if ($array_size != $previous_array_size) {
                throw new \Exception("bad input -- row $i has $array_size vs row before has $previous_array_size");
            }
            foreach ($row as $j => $cell) {
                if (empty($cell) && !empty($this->vsums[$i][$j])) {
                    $this->log($this->hsums);
                    $this->log($this->vsums);
                    throw new \Exception("bad input at $i $j. ". $this->hsums[$i][$j].' vs '. $this->vsums[$i][$j]);
                }
            }
        }

        $previous_array_size = 0;
        foreach ($this->vsums as $i => $row) {
            $array_size = count($row);
            if (empty($previous_array_size)) {
                $previous_array_size = $array_size;
            }
            if ($array_size != $previous_array_size) {
                throw new \Exception('bad input');
            }
            foreach ($row as $j => $cell) {
                if (empty($cell) && !empty($this->hsums[$i][$j])) {
                    throw new \Exception("bad input at $i $j. ". $this->hsums[$i][$j].' vs. '. $this->vsums[$i][$j]);
                }
            }
        }
    }

    protected function score($grid)
    {
        if (empty($grid['cells'])) {
            return 100;
        }
        $ct = 0;
        foreach ($grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if (!$this->isBlank($i,$j)) {
                    $ct += count($cell['choices']) - 1;
                }
            }
        }

        // this will be 0 if all cells have only 1 pv; 100 if whole ns in each cell
        return 100 * $ct / ($this->nonempty_cell_count * (count($this->number_set) - 1));
    }

    protected function isSolution($grid)
    {
        $this->log('checking solution');

        foreach ($this->strips as $strip) {
            foreach ($strip as $cell) {
                if (!$this->checkStrip($strip, $grid)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function addSolution($grid)
    {
        if (!$this->solutionExists($grid)) {
            $key = $this->collapse($grid);
            $this->solutions[$key] = $grid;
            $this->log('add solution ' . $key);
            $this->log('path ' . implode('__', $grid['path']));
        }

        if (!empty($this->solutions_desired) && count($this->solutions) >= $this->solutions_desired) {
            $this->done = true;
            $this->log('thats it!');
        }
    }

    protected function solutionExists($grid)
    {
        $key = $this->collapse($grid);
        if (in_array($key, array_keys($this->solutions))) {
            return true;
        }
    }

    protected function collapse($grid)
    {
        $str = '';
        foreach ($grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlank($i, $j)) {
                    $str .= '.';
                } else {
                    $str .= current($cell['choices']);
                }
            }
        }

        return $str;
    }

    protected function possibleValues($i, $j) {
        $hidx = $this->grid['cells'][$i][$j]['strip_indices']['h'];
        $vidx = $this->grid['cells'][$i][$j]['strip_indices']['v'];
        $hstrip = $this->strips[$hidx];
        $vstrip = $this->strips[$vidx];
        $hsum = $hstrip['total'];
        $vsum = $vstrip['total'];
// $this->log("pv $i $j $hsum $vsum");
        $hsize = $hstrip['len'];
        $vsize = $vstrip['len'];

        $ha = $this->getValues($hsum, $hsize);
        $va = $this->getValues($vsum, $vsize);
        $a = array_values(array_intersect($ha, $va));
// $this->log($ha);
// $this->log($va);
// $this->log($a);
// $this->log('---');
        sort($a);
        return $a;
    }

    protected function displaySolutions()
    {
        if (empty($this->solutions)) {
            return 'no solutions found in ' . (microtime(true) - $this->start_time) . "s\n\n";
        } else {
            $str = count($this->solutions) . ' solution(s) found in ' . (microtime(true) - $this->start_time) . "s\n\n";
            foreach ($this->solutions as $solution) {
                if ($this->debug) {
                    $str .= "Guess sequence:\n" . $this->displayGuessSequence($solution) . "\n";
                }
                $str .= $this->displayOptions($solution, 3) . "\n";
            }
        }

        return $str;
    }

    public function getValues($target, $size, $used = [])
    {
        return $this->table_builder->findValues($target, $size, $used);
    }

    protected function removeChoices($grid, $cell, $choices_to_remove)
    {
        if (!is_array($choices_to_remove)) {
            $choices_to_remove = [$choices_to_remove];
        }
// $this->log("remove " . json_encode($choices_to_remove) . " from " . $cell['i'].' '.$cell['j']);
// $this->log("curr choices " . json_encode($cell['choices']));
        $new_choices = array_values(array_diff($cell['choices'], $choices_to_remove));
// $this->log("new choices " . json_encode($new_choices));

        if (count($cell['choices']) <= count($new_choices)) {
            return $grid;
        }
        $cell['choices'] = $new_choices;
        return $this->updateChoices($grid, $cell, $new_choices);
    }

    protected function updateChoices($grid, $cell, $choices)
    {
        if (empty($grid)) {
            return false;
        }
        $cell['choices'] = $choices;
        $grid['cells'][$cell['i']][$cell['j']] = $cell;
        foreach ($cell['strip_indices'] as $idx) {
            if (!in_array($idx, $grid['changed_strips'])) {
                $grid['changed_strips'][] = $idx;
            }
        }
        return $grid;
    }

    protected function isBlank($i, $j)
    {
        return GridHelper::isBlank($i, $j, $this->grid['cells']);
    }

    protected function isBlankCell($cell, $strict = false)
    {
        if ($strict) {
            return !empty($cell['blank']) && $cell['blank'] === true;
        }
        return !empty($cell['blank']);
    }

    protected function isNonBlankCell($cell)
    {
        return isset($cell['blank']) && $cell['blank'] === false;
    }

    protected function isEmptyCell($cell)
    {
        return !isset($cell['blank']);
    }

    protected function isEmpty($i, $j)
    {
        return $this->isEmptyCell($this->grid['cells'][$i][$j]);
    }

    protected function countBlanks()
    {
        $count = 0;
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlankCell($cell, true)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    protected function countNonBlanks()
    {
        $count = 0;
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isNonBlankCell($cell)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function meetsCriteria($i, $j, $criteria)
    {
        $meets_criteria = false;
        if (in_array('blank', $criteria)) {
            $meets_criteria = $this->isBlankCell($this->grid['cells'][$i][$j], true);
        }
        if (!$meets_criteria && in_array('nonblank', $criteria)) {
            $meets_criteria = $this->isNonBlankCell($this->grid['cells'][$i][$j]);
        }
        if (!$meets_criteria && in_array('empty', $criteria)) {
            $meets_criteria = $this->isEmptyCell($this->grid['cells'][$i][$j]);
        }

        return $meets_criteria;
    }

    // $criteria is an array with choices 'blank', 'nonblank', 'empty'; get connected cells to $i $j with val
    protected function buildWeb($i, $j, $criteria, $arr = [], $include_diagonals = true)
    {
        if (!in_array([$i, $j], $arr)) {
            $arr[] = [$i, $j];
        }
        for ($h=max($i-1,0); $h<=min($this->width-1, $i+1); $h++) {
            for ($k=max($j-1,0); $k<=min($this->height-1, $j+1); $k++) {
                if (!$include_diagonals && ($h != $i) && ($k != $j)) {
                    continue;
                }

                if ($this->meetsCriteria($i, $j, $criteria)) {
                    if (!in_array([$h, $k], $arr)) {
                        $arr[] = [$h, $k];
                        $arr = $this->buildWeb($h, $k, $criteria, $arr, $include_diagonals);
                    }
                }
            }
        }

        return $arr;
    }

    public static function unsetValue(&$arr, $x)
    {
        if (is_array($x)) {
            $x = json_encode($x);
            $r = [];
            foreach ($arr as $v) {
                if (json_encode($v) !== $x) {
                    $r[] = $v;
                }
            }

            $arr = $r;
            return null;
        }

        $arr = array_diff($arr, [$x]);
        return null;
    }

    // this takes an array as its second arg and unsets each value in it, whereas unsetValue would unset a nested array
    // example $arr = [2,3,[2,3]]
    // unsetValue($arr, [2,3]) => [2,3] (unsets 3rd element in arr)
    // unsetValues($arr, [2,3]) => [[2,3]] (unsets 1st & 2nd elements in arr)
    public static function unsetValues(&$arr, $x)
    {
        if (!is_array($x)) {
            return self::unsetValue($arr, $x);
        }

        foreach ($x as $val) {
            self::unsetValue($arr, $val);
        }

        return null;
    }

    // sort an associative array by a simple sort array {k1:asc}
    protected function aaSort(&$array_to_sort, $data_sort)
    {
        $sort_key = key($data_sort);
        $sort_direction = strtolower(current($data_sort));
        $sorter = [];
        $ret = [];
        reset($array_to_sort);

        if ($sort_direction == 'desc')
        {
            $array_to_sort = array_reverse($array_to_sort);
        }
        foreach ($array_to_sort as $ii => $va) 
        {
            $sorter[$ii] = $va[$sort_key];
        }
        if ($sort_direction == 'desc')
        {
            arsort($sorter);
        }
        else
        {
            asort($sorter);
        }
        foreach ($sorter as $ii => $va) 
        {
            $ret[$ii] = $array_to_sort[$ii];
        }
        $array_to_sort = $ret;
    }

    protected function clearLog()
    {
        if (!empty($this->parameters['clear-log'])) {
            file_put_contents($this->log_file, "");
        }
    }

    public function getResult()
    {
        return $this->solutions;
    }
}