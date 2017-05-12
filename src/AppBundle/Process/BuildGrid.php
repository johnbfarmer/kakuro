<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class BuildGrid extends BaseGrid
{
    protected 
        $width,
        $height,
        $density_constant,
        $density_randomness = 0.3,
        $symmetry = false,
        $grid,
        $sums,
        $rank = 0,
        $last_choice = [],
        $saved_grids = [],
        $minimum_strip_size = 2,
        $island = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->width = !empty($this->parameters['width']) ? $this->parameters['width'] : 12;
        $this->height = !empty($this->parameters['height']) ? $this->parameters['height'] : 12;
        $this->density_constant = !empty($this->parameters['density']) ? $this->parameters['density'] : 0.8;
        $this->symmetry = !empty($this->parameters['symmetry']);
        $this->grid = [
            'cells' => []
        ];
        $this->sums = [];
    }

    public function execute()
    {
        $this->createKakuro();
        $this->draw();
    }

    public function createKakuro($start_i = 0, $start_j = 0)
    {
        $this->buildInitialFrame($start_i, $start_j);
        $this->buildFrame($start_i, $start_j);
$this->draw(false);
        $this->init();
// $this->log($this->grid, false);
        $this->addNumbers();
// $this->log($this->grid, false);
        $this->getSums();
    }

    public function buildInitialFrame($start_i = 0, $start_j = 0)
    {
        $unknown_cell = [];
        for ($j=$start_j; $j < $this->width; $j++) {
            $this->grid['cells'][$start_i][$j] = $unknown_cell;
        }
        for ($i=$start_i+1; $i < $this->width; $i++) {
            $this->grid['cells'][$i] = array_fill(0, $this->height, $unknown_cell);
        }
    }

    public function buildFrame($start_i = 0, $start_j = 0)
    {
        for ($i = $start_i; $i < $this->width; $i++) {
            for ($j = $start_j; $j < $this->height; $j++) {
                $start_j = 0; // for subsequent loops
                $this->island = [];

                if ($this->symmetry && $this->height - $j <= $i) {
                    continue;
                }

                if (!$this->isUnknown($this->grid['cells'][$i][$j])) {
                    continue;
                }

                $must = $this->mustBeBlank($i, $j);
                $must_not = $this->mustNotBeBlank($i, $j);

                if ($must && !$must_not) {
                    $this->setBlank($i, $j, true);
                    $this->setForced($i, $j, true);
                }

                if (!$must && $must_not) {
                    $this->setNonBlank($i, $j);
                    $this->setForced($i, $j, true);
                }

                if (!$must && !$must_not) {
                    $this->decideBlankOrNot($i, $j);
                    $this->setForced($i, $j, false);
                }

                if ($must && $must_not) {
                    $this->log("$i, $j, must & must not be blank", true);
                    $this->draw(false);
                    return $this->changeLastUnforced($i, $j);
                }
            }
        }
        return true;
    }

    protected function decideBlankOrNot($i, $j)
    {
        $blanks = $this->countBlanks();
        $nonblanks = $this->countNonBlanks();
        $desired_fullness = 1 - $this->density_constant;
        if (!$blanks && !$nonblanks) {
            $this->randomlyDecideBlankOrNot($i, $j);
        } else {
            $fullness = $blanks / ($blanks + $nonblanks);
            if ($fullness <  $desired_fullness - $this->density_randomness) {
                $this->setBlank($i, $j, true);
            } elseif ($fullness >  $desired_fullness + $this->density_randomness) {
                $this->setNonBlank($i, $j);
            } else {
                $this->randomlyDecideBlankOrNot($i, $j);
            }
        }
    }

    protected function randomlyDecideBlankOrNot($i, $j)
    {
        $rand = rand(1,100) / 100;
        $desired_fullness = 1 - $this->density_constant;
        if ($rand < $desired_fullness) {
            $this->setBlank($i, $j, true);
        } else {
            $this->setNonBlank($i, $j);
        }
    }

    protected function changeLastUnforced($i, $j)
    {
        // walk back to last unforced cell; change; unset forced cells after that; rewalk from there
        while (true) {
            list($i, $j) = $this->getPreviousCell($i, $j);
$this->log("prev is $i $j", true);
// $this->log($this->grid['cells'][$i][$j], true);
            if ($this->isForced($i, $j)) {
                $this->setUnknown($i, $j);
                continue;
            }

            if ($this->isEmpty($i, $j)) {
                $this->setNonBlank($i, $j);
            } else {
                // careful here -- may create island
                $this->setBlank($i, $j);
            }

            $this->setForced($i, $j, true);
            list($i, $j) = $this->getNextCell($i, $j);
            return $this->buildFrame($i, $j);
        }
    }

    protected function changeLastUnforcedNumber()
    {
        list($i, $j) = $this->getPreviousCellWithChoices();
        $cell = $this->grid['cells'][$i][$j];

        if ($this->isBlank($i, $j) || count($cell['choices']) < 2) {
            return;
        }

        $choices = $cell['choices'];
        $val = $cell['value'];
        $this->unsetValue($choices, $val);
        $choices = array_values($choices);
        // $choices = array_values($this->unsetValue($choices, $val));
        $saved_grid = $this->saved_grids[$i . '_' . $j];
        $saved_grid['cells'][$i][$j]['choices'] = $choices;
        $this->grid = $saved_grid;
$this->log("reset grid to {$i}_{$j}", true);
        $this->selectValue($i, $j, $choices);
    }

    protected function getPreviousCellWithChoices()
    {
        return array_pop($this->last_choice);
    }

    protected function getPreviousCell($i, $j)
    {
        if ($j > 0) {
            return [$i, $j - 1];
        }

        if ($i === 0) {
            throw new \Exception('No previous cell');
        }

        return [$i - 1, $this->width - $i];
    }

    protected function getNextCell($i, $j)
    {
        if ($j < $this->width - $i) {
            return [$i, $j + 1];
        }

        if ($i === $this->height) {
            throw new \Exception('No next cell');
        }

        return [$i + 1, 0];
    }

    protected function init()
    {
        for ($i=0; $i < $this->width; $i++) {
            for ($j=0; $j < $this->height; $j++) {
                if ($this->isBlank($i, $j)) {
                    continue;
                }
                $this->grid['cells'][$i][$j]['i'] = $i;
                $this->grid['cells'][$i][$j]['j'] = $j;
                $this->grid['cells'][$i][$j]['position'] = [$i, $j];
                $this->grid['cells'][$i][$j]['strips'] = $this->findMyStrips($i,$j);
                $this->grid['cells'][$i][$j]['rank'] = 0;
                $this->grid['cells'][$i][$j]['choices'] = $this->number_set;
            }
        }
    }

    protected function addNumbers()
    {
        while (true) {
            list($i, $j) = $this->pickCellToProcess();
var_dump("pick $i $j");
            if (is_null($i)) {
                break;
            }
            $this->addNumber($i, $j);
$val = !empty($this->grid['cells'][$i][$j]['value']) ? $this->grid['cells'][$i][$j]['value'] : null;
$this->log("$i $j assign " . $val, true);
        }
    }

    protected function pickCellToProcess()
    {
        // prioritize choices then small strip size
        $position = [null, null];
        $fewest_options = $smallest_strip_size = count($this->number_set) + 1;
        $candidates = [];
        $best_candidate = null;

        for ($i = 0; $i < $this->width; $i++) {
            for ($j = 0; $j < $this->height; $j++) {
                $cell = $this->grid['cells'][$i][$j];
                if ($this->isBlankCell($cell)) {
                    continue;
                }
                // already processed
                if (!empty($cell['value'])) {
                    continue;
                }
                $num_options = count($cell['choices']);
                if ($num_options == $fewest_options) {
                    $candidates[] = $cell;
                }
                if ($num_options < $fewest_options) {
                    $candidates = [$cell]; // clear previous
                    $fewest_options = $num_options;
                }
                if (empty($candidates)) {
                    return $position;
                }

                if (count($candidates) == 1) {
                    $best_candidate = $candidates[0];
                } else {
                    foreach ($candidates as $candidate) {
                        $c_smallest_strip_size = min(count($candidate['strips']['h']), count($candidate['strips']['v']));
                        if ($c_smallest_strip_size < $smallest_strip_size) {
                            $smallest_strip_size = $c_smallest_strip_size;
                            $best_candidate = $candidate;
                        }
                    }
                }
            }
        }

        if ($best_candidate && !empty($best_candidate['position'])) {
            $position = $best_candidate['position'];
        }

        return $position;
    }

    protected function addNumber($i, $j)
    {
        $cell = $this->grid['cells'][$i][$j];
        if (!$this->isBlankCell($cell)) {
            $strips = $this->findMyStrips($i,$j);
// $this->log("strips $i $j", true);
// $this->log($strips, true);
            $cell['strips'] = $strips;
            $available = $this->number_set;
            $taken = [];
            foreach ($strips as $strip) {
                foreach ($strip as $strip_cell) {
                    if (in_array($strip_cell, $available) && !in_array($strip_cell, $taken)) {
                        $taken[] = $strip_cell;
                    }
                }
            }

            $available = array_values(array_diff($available, $taken));
            if (empty($available)) {
$this->log("Nothing available $i $j", true);
$this->draw(false);
                $this->changeLastUnforcedNumber();
            } else {
                $this->selectValue($i, $j, $available);
            }
        }
    }

    protected function selectValue($i, $j, $choices)
    {
        if (count($choices) > 1) {
$this->log("$i $j is latest choice", true);
$this->log($choices, true);
            $this->last_choice[] = [$i,$j];
            $this->saved_grids[$i . '_' . $j] = $this->grid;
$this->log($this->last_choice, true);
        }

        if (count($choices) == 1) {
            $this->unsetValue($this->last_choice, [$i, $j]);
$this->log("unset $i $j from LC", true);
$this->log($this->last_choice, true);
        }

        $this->grid['cells'][$i][$j]['choices'] = $choices;
        $index = rand(1, count($choices)) - 1;

        if (!isset($choices[$index])) {
            $this->fail($i, $j, 'unavailable index chosen');
        }

        $val = $choices[$index];
$this->log("assign $i $j $val, choices:", true);
$this->log($choices, true);
        $this->grid['cells'][$i][$j]['value'] = $val;
        $this->computeChoices();
    }

    protected function computeChoices()
    {
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlankCell($cell)) {
                    continue;
                }
                $this->computeCellChoices($i, $j);
            }
        }
    }

    protected function computeCellChoices($i, $j)
    {
        $strips = $this->findMyStrips($i, $j);
        $cell['strips'] = $strips;
        $available = $this->number_set;
        $taken = [];
        foreach ($strips as $strip) {
            foreach ($strip as $strip_cell) {
                if (in_array($strip_cell, $available) && !in_array($strip_cell, $taken)) {
                    $taken[] = $strip_cell;
                }
            }
        }

        $choices = array_values(array_diff($available, $taken));
        $this->grid['cells'][$i][$j]['choices'] = $choices;
    }

    public function draw($open_broswer = true)
    {
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                $blank = $this->isBlankCell($cell);
                $unknown = $this->isUnknown($cell);
                $ph = !empty($cell['value']) ? $cell['value'] : '*';
                $val = $unknown
                    ? '?'
                    : (!$blank ? $ph : '.');
                print $val;
            }
            print "\n";
        }

        if ($open_broswer) {
            $this->openGridInBrowser();
        }

        $hsums = [];
        $vsums = [];

        // this is useful
        $this->write_file($this->sums);
    }

    protected function write_file($c)
    {
        $fp = fopen('tmp/easy3.kak', 'w+'); // TBI
        fwrite($fp, $this->height . "\n");
        fwrite($fp, $this->width . "\n");
        for ($i = 0; $i <= $this->height; $i++) {
            for ($j = 0; $j <= $this->width; $j++) {
                $val = !empty($c[$i][$j]) ? $c[$i][$j] : '';
                if ($j < $this->width) {
                    $val .= "\t";
                }
                fwrite($fp, $val);
            }
            fwrite($fp, "\n");
        }
        fclose($fp);
    }

    public function mustBeBlank($i, $j)
    {
        $this->setNonBlank($i, $j);
        if ($this->violatesMinimumStripSize($i, $j)) {
            return true;
        }
        $this->setUnknown($i, $j);

        if ($this->causesOversizeStrip($i, $j)) {
            return true;
        }

        return false;
    }

    public function mustNotBeBlank($i, $j)
    {
        // find my nbrs -- every non-blank must have a non-blank above or below as well as to the right or left
        // we do not support strips of size 1
        $this->setBlank($i, $j);
        $nbrs = $this->getNeighboringCoordinates($i, $j);
        foreach ($nbrs as $nbr) {
            $cell = $this->grid['cells'][$nbr[0]][$nbr[1]];
            if ($this->isNonBlankCell($cell)) {
                if ($this->violatesMinimumStripSize($nbr[0], $nbr[1])) {
                    return true;
                }
            }
        }
        
        $this->setUnknown($i, $j);
        if ($i > 1) {
            // does my left nbr have a blank left nbr?
            if (!$this->isBlankCell($this->grid['cells'][$i - 1][$j]) && $this->isBlankCell($this->grid['cells'][$i - 2][$j])) {
                return true;
            }
        }

        if ($i == 1) {
            // is my left nbr non-blank?
            if (!$this->isBlankCell($this->grid['cells'][$i - 1][$j])) {
                return true;
            }
        }

        if ($j > 1) {
            // does my top nbr have a blank top nbr?
            if (!$this->isBlankCell($this->grid['cells'][$i][$j - 1]) && $this->isBlankCell($this->grid['cells'][$i][$j - 2])) {
                return true;
            }
        }

        if ($j == 1) {
            // is my top nbr non-blank?
            if (!$this->isBlankCell($this->grid['cells'][$i][$j - 1])) {
                return true;
            }
        }

        $this->island = $this->createsIsland($i, $j);
        // more: an island might be ok, particularly if unpopulated now or can be... TBI
        // if a path traverses, 2 islands are formed
        if (!empty($this->island)) {
            print "island caused by setting $i $j blank\n";
            $this->log($this->island, true);
            $this->draw(false);
            if (!$this->okToRemoveIsland($this->island)) {
                return true;
            }
        }

        return false;
    }

    protected function violatesMinimumStripSize($i, $j)
    {
        $strips = $this->findMyStrips($i, $j, true);
        foreach ($strips as $strip) {
            if (count($strip) < $this->minimum_strip_size) {
                return true;
            }
        }

        return false;
    }

    protected function getNeighboringCoordinates($i, $j)
    {
        $coords = [];
        if ($i > 0) {
            $coords[] = [$i - 1, $j];
        }
        if ($i < $this->height - 1) {
            $coords[] = [$i + 1, $j];
        }
        if ($j > 0) {
            $coords[] = [$i, $j - 1];
        }
        if ($j < $this->width - 1) {
            $coords[] = [$i, $j + 1];
        }

        return $coords;
    }

    protected function createsIsland($i, $j)
    {
        // by making this blank, is there a wall that isolates cells?
        // such a wall reaches from one side to another stepping to blank nbrs v|h|d
        // so if i can walk from here to 2 diff edges, island
        // temporarily set blank, walk, then set back to unknown:
        $this->setBlank($i, $j);
        list($creates_island, $path) = $this->walkToEdges($i, $j, ['blank']);
// if ($creates_island) {
// $this->log("path for $i $j", true);
    // var_dump(json_encode($path));
// }
        // more to do -- an island is ok if it does not have 'x' values on it
        $island = $creates_island ? $this->getIsland($path) : [];
        $this->setUnknown($i, $j);
// if ($creates_island) {
// $this->log("path for $i $j", true);
    // var_dump(json_encode($island));
// }

        return $island;
    }

    protected function getIsland($path)
    {
        // path consists of blanks; of the 2 halves, if the smaller is unpopulated, return it
        // TBI -- depopulate it if density is out of balance
        $blanks = $this->countBlanks();
$this->log($blanks . ' blanks', true);
        $left_half = [];
        $right_half = [];
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                // find one piece of an island
                if (in_array([$i, $j], $path)) {
                    continue;
                }
                $left_half = $this->buildWeb($i, $j, ['nonblank', 'empty'], [], false);
                break 2;
            }
        }

        $lhc = count($left_half);
$this->log($lhc . ' in LHS', true);
        $unacctd = ($this->height * $this->width - $lhc - $blanks);
$this->log($unacctd . ' unacctd', true);

        if (!$unacctd) {
            return [];
        }

        if ($unacctd > $lhc) {
            return $left_half;
        }

        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                // walk till you find sth not on the path or the left half
                if ($this->isBlankCell($cell)) {
                    continue;
                }
                if (in_array([$i, $j], $path)) {
                    continue;
                }
                if (in_array([$i, $j], $left_half)) {
                    continue;
                }
                // print "$i $j not on path or left half\n";
                $right_half = $this->buildWeb($i, $j, ['nonblank', 'empty'], [], false);
                break 2;
            }
        }
$rhc = count($right_half);
$this->log($rhc . ' in RHS', true);

        if (empty($right_half)) {
            return [];
        }
        if (count($right_half) > count($left_half)) {
            return $left_half;
        }

        return $right_half;
    }

    // if we set ij blank, get the path of blanks created
    protected function walkToEdges($i, $j, $val = 0)
    {
        $path = $this->buildWeb($i, $j, ['blank']);
        $edges = [];
        foreach ($path as $point) {
            if ($point[0] == 0 || $point[0] == $this->width - 1 || $point[1] == 0 || $point[1] == $this->height - 1) {
                $edges[] = $point;
            }
        }

        return [count($edges) > 1, $path];
    }

    protected function okToRemoveIsland($island)
    {
        $blanks = $this->countBlanks();
        $nonblanks = $this->countNonBlanks();
        $desired_fullness = 1 - $this->density_constant;
        $island_size = count($island);
        $fullness = ($blanks + $island_size) / ($blanks + $island_size + $nonblanks);
        if ($fullness <  $desired_fullness - $this->density_randomness) {
            return false;
        } elseif ($fullness >  $desired_fullness + $this->density_randomness) {
            return true;
        }

        return rand(1,2) < 2;
    }

    protected function removeIsland($island)
    {
$this->log('remove island ' . json_encode($island), true);
        foreach ($island as $cell) {
            $this->setBlank($cell[0], $cell[1]);
        }
    }

    protected function causesOversizeStrip($i, $j)
    {
        $this->setNonBlank($i, $j);
        $strips = $this->findMyStrips($i, $j);
        $this->setUnknown($i, $j);
        foreach ($strips as $strip) {
            if (count($strip) > count($this->number_set)) {
$this->log("oversize strip", true);
                return true;
            }
        }

        return false;
    }

    protected function setBuildValue($i, $j, $val)
    {
        $this->grid['cells'][$i][$j] = $val;
        if ($this->symmetry) {
            $this->grid['cells'][$this->height - $j - 1][$this->width - $i - 1] = $val;
        }
// $this->log($this->grid, false);
    }

    protected function setBlank($i, $j, $remove_island = false)
    {
        $this->setBuildValue($i, $j, ['blank' => true]);
$this->log("set blank $i $j", true);
        if ($remove_island && !empty($this->island)) {
            $this->removeIsland($this->island);
        }
    }

    protected function setNonBlank($i, $j)
    {
        $this->setBuildValue($i, $j, ['blank' => false]);
$this->log("set nonblank $i $j", true);
    }

    protected function setUnknown($i, $j)
    {
        $this->setBuildValue($i, $j, []);
    }

    protected function setForced($i, $j, $forced)
    {
        $this->grid['cells'][$i][$j]['forced'] = $forced;
    }

    protected function isForced($i, $j)
    {
        return $this->grid['cells'][$i][$j]['forced'];
    }

    protected function fail($i, $j, $msg)
    {
        $this->draw(false);
        throw new \Exception("failure at $i, $j: " . $msg);
    }

    // lose this; use isEmptyCell
    protected function isUnknown($val) {
        return empty($val);
    }
}