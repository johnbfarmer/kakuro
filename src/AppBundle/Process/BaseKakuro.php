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
        $result,
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

    protected function isBlank($idx, $strict = false)
    {
        return $this->isNonDataCell($idx);
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

    protected function isNonDataCell($idx, $strict = false)
    {
        $cell = $this->cells[$idx];
        return !$cell->isDataCell();
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

    protected function getNeighboringCoordinates($idx, $include_diagonals = false)
    {
        $indexes = [
            'top' => null,
            'bottom' => null,
            'left' => null,
            'right' => null,
        ];

        if ($idx >= $this->width) {
            $indexes['top'] = $idx - $this->width;
        }
        if ($idx < ($this->height - 1) * $this->width) {
            $indexes['bottom'] = $idx + $this->width;;
        }
        if ($idx % $this->width) {
            $indexes['left'] = $idx - 1;
        }
        if ($idx % $this->width < $this->width - 1) {
            $indexes['right'] = $idx + 1;
        }

        return $indexes;
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

    protected function findContiguousGroup($edge_idxs, $criteria, $contiguous_group = [], $include_diagonals = false)
    {
        if (!is_array($edge_idxs)) {
            $edge_idxs = [$edge_idxs];
        }

        // 1st time thru avoid having to pre-populate contiguous_group externally:
        if (empty($contiguous_group) && count($edge_idxs) === 1) {
            if ($this->meetsCriteria($edge_idxs[0], $criteria)) {
                $contiguous_group[] = $edge_idxs[0];
            }
        }

        $new_adds = [];
        foreach ($edge_idxs as $idx) {
            $contiguous_idxs = $this->getNeighboringCoordinates($idx, $include_diagonals);
            foreach ($contiguous_idxs as $i) {
                if ($i && !in_array($i, $contiguous_group)) {
                    if ($this->meetsCriteria($i, $criteria)) {
                        $contiguous_group[] = $i;
                        $new_adds[] = $i;
                    }
                }
            }
        }

        if (empty($new_adds)) {
            return $contiguous_group;
        }

        return $this->findContiguousGroup($new_adds, $criteria, $contiguous_group, $include_diagonals);
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

        if ($sort_direction == 'desc') {
            $array_to_sort = array_reverse($array_to_sort);
        }

        foreach ($array_to_sort as $ii => $va) {
            $sorter[$ii] = $va[$sort_key];
        }

        if ($sort_direction == 'desc') {
            arsort($sorter);
        } else {
            asort($sorter);
        }

        foreach ($sorter as $ii => $va) {
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

    public function display($padding = 10, $frameOnly = false)
    {
        $str = "\ncurrently\n" . $this->displayChoicesHeader();

        foreach ($this->cells as $idx => $cell) {
            if ($this->isBlank($idx, true)) {
                $c = '.';
            } elseif ($this->isEmpty($idx)) {
                $c = '?';
            } else {
                $c = $frameOnly ? 'D' : $cell->getChoice();
            }
            if ($cell->getCol() < 1) {
                $str .= "\n";
            }
            $str .= str_pad($c, $padding, ' ');
        }
        $str .= "\n";
        $this->log($str, true);
    }

    protected function clearLog()
    {
        if (!empty($this->parameters['clear-log'])) {
            file_put_contents($this->log_file, "");
        }
    }

    public function getResult()
    {
        return $this->result;
    }
}