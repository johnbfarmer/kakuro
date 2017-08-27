<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class BaseKakuro extends BaseProcess
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
        $grid_name,
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

    // new paradigm, cell as class not array
    protected function removeByRowAndCol(&$cells, $target)
    {
        foreach ($cells as $idx => $cell) {
            if ($cell->getRow() === $target->getRow() && $cell->getCol() === $target->getCol()) {
                unset($cells[$idx]);
            }
        }
    }

    protected function getIndexByRowAndCol($cell)
    {
        return $cell->getRow() * $this->width + $cell->getCol();
    }

    protected function displayChoices($padding = 10)
    {
        $str = "\n" . $this->displayChoicesHeader();

        foreach ($this->cells as $idx => $cell) {
            if (!$cell->isDataCell()) {
                $c = '.';
            } else {
                $ch = $cell->getChoices();
                if (count($ch) < $padding) {
                    $c = implode('', $ch);
                } else {
                    $c = 'X';
                }
            }
            if ($cell->getCol() < 1) {
                $str .= "\n";
            }
            $str .= str_pad($c, $padding, ' ');
        }
        $str .= "\n";
        $this->log($str, true);
    }

    protected function displayChoicesHeader()
    {
        return '';
    }

    protected function getCellsForStrip($strip)
    {
        $dir = $strip->getDir();
        $len = $strip->getLen();
        $cells = [];
        if ($dir === 'h') {
            $start = $strip->getStartCol();
            $row = $strip->getStartRow();
        } else {
            $start = $strip->getStartRow();
            $col = $strip->getStartCol();
        }

        for ($k = $start; $k < $start + $len; $k++) {
            $i = $dir === 'v' ? $k : $row; 
            $j = $dir === 'h' ? $k : $col;
            $idx = $i * $this->width + $j;
            $cells[] = $this->cells[$idx];
        }

        return $cells;
    }

    // old paradigm

    protected function isSolution($grid)
    {
        
    }

    protected function addSolution($grid)
    {
        
    }

    protected function solutionExists($grid)
    {
        
    }

    public function getValues($target, $size, $used = [])
    {
        return $this->table_builder->findValues($target, $size, $used);
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

    protected function isEmpty($idx)
    {
        return empty($this->cellTypes[$idx]);
    }

    protected function countBlanks()
    {
        $count = 0;
        foreach ($this->cellTypes as $idx => $cellType) {
            if ($idx > $this->width && $idx % $this->width && $this->isBlank($idx, true)) {
                $count++;
            }
        }

        return $count;
    }

    protected function countNonBlanks()
    {
        $count = 0;
        foreach ($this->cellTypes as $idx => $cellType) {
            if ($this->isNonBlank($idx)) {
                $count++;
            }
        }

        return $count;
    }

    protected function meetsCriteria($idx, $criteria)
    {
        $meets_criteria = false;
        if (in_array('blank', $criteria)) {
            $meets_criteria = $this->isBlank($idx, true);
        }
        if (!$meets_criteria && in_array('nonblank', $criteria)) {
            $meets_criteria = $this->isNonBlank($idx);
        }
        if (!$meets_criteria && in_array('empty', $criteria)) {
            $meets_criteria = $this->isEmpty($idx);
        }

        return $meets_criteria;
    }

    // $criteria is an array with choices 'blank', 'nonblank', 'empty'; get connected cells to $i $j with val
    protected function buildWeb($idx, $criteria, $arr = [], $include_diagonals = true)
    {
        if (!in_array($idx, $arr)) {
            $arr[] = $idx;
        }

        $j = $idx % $this->width;
        $i = floor($idx / $this->width);

        for ($h=max($i-1,1); $h<=min($this->width-1, $i+1); $h++) {
            for ($k=max($j-1,1); $k<=min($this->height-1, $j+1); $k++) {
                if (!$include_diagonals && ($h != $i) && ($k != $j)) {
                    continue;
                }

                $tmp_idx = $h * $this->width + $k;
                if (!in_array($tmp_idx, $arr)) {
                    if ($this->meetsCriteria($tmp_idx, $criteria)) {
                        $arr[] = $tmp_idx;
                        $arr = $this->buildWeb($tmp_idx, $criteria, $arr, $include_diagonals);
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
            return;
        }

        $arr = array_values(array_diff($arr, [$x]));
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

    public static function shuffle(&$arr)
    {
        $a = [];
        $ct = count($arr);
        while ($ct > 0) {
            $arr = array_values($arr);
            $r = rand(0, $ct-- - 1);
            $a[] = $arr[$r];
            unset($arr[$r]);
        }

        $arr = $a;
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