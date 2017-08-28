<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;
use AppBundle\Entity\Strip;
use AppBundle\Entity\Solution;

class BuildKakuroFrame extends BaseKakuro
{
    protected 
        $width,
        $height,
        $cells = [],
        $cellTypes = [],
        $isForcedCellType = [],
        $cellChoices = [],
        $density_constant,
        $density_randomness = 0.3,
        $symmetry = false,
        $finished = false,
        $grid,
        $idx = 0,
        $frameId = 0,
        $minimum_strip_size = 2,
        $dataCellCount = 0,
        $startIdx = 0,
        $island = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->width = !empty($this->parameters['width']) ? $this->parameters['width'] : 12;
        $this->height = !empty($this->parameters['height']) ? $this->parameters['height'] : 12;
        $this->density_constant = !empty($this->parameters['density']) ? $this->parameters['density'] : 12;
        $this->maxStripLength = !empty($this->parameters['max-strip-length']) ? $this->parameters['max-strip-length'] : count($this->number_set);
        $this->symmetry = !empty($this->parameters['symmetry']);
        if (!empty($this->parameters['frame-id'])) {
            $this->frameId = $this->parameters['frame-id'];
        }
        if (!empty($this->parameters['grid'])) {
            $this->gridObj = $this->parameters['grid'];
            $this->gridObj->setWidth($this->width);
            $this->gridObj->setHeight($this->height);
        }
        $this->grid = [
            'cells' => []
        ];
    }

    public function execute()
    {
        $this->buildInitialFrame();
        if ($this->frameId) {
            $this->getFrame();
        } else {
            $this->buildFrame();
            // $this->saveFrame();
            $this->initializeForSettingVals();
        }
    }

    public function buildInitialFrame()
    {
        for ($i=0; $i < $this->height; $i++) {
            for ($j=0; $j < $this->width; $j++) {
                $cell = new Cell();
                $this->gridObj->addCell($cell);
                $cell->setLocation($i, $j);
                $idx = $cell->getIdx();
                $this->cells[$idx] = $cell;
            }
        }
    }

    public function buildFrame()
    {
        while (!$this->finished) {
            $start_idx = $this->startIdx;
            foreach ($this->cells as $idx => $cell) {
                if ($idx < $start_idx) {
                    continue;
                }

                $i = $cell->getRow();
                $j = $cell->getCol();
                $this->island = [];

                if ($this->symmetry && $this->height - $j <= $i) {
                    continue;
                }

                if (!empty($this->cellTypes[$idx])) {
                    continue;
                }

                $must = $this->mustBeBlank($idx);
                $must_not = $this->mustNotBeBlank($idx);
    $this->log("$idx must:$must must_not:$must_not", true);
                if ($must && !$must_not) {
                    $this->setBlank($idx, true);
                    $this->setForced($idx, true);
                }

                if (!$must && $must_not) {
                    $this->setNonBlank($idx);
                    $this->setForced($idx, true);
                }

                if (!$must && !$must_not) {
                    $this->decideBlankOrNot($idx);
                    $this->setForced($idx, false);
                }

                if ($must && $must_not) {
                    $this->log("$idx, must & must not be blank", true);
                    $this->changeLastUnforced($idx);
                    continue 2;
                }
            }

            $this->finished = true;
        }
$this->display(3, true);
        return true;
    }

    protected function decideBlankOrNot($idx)
    {
        $blanks = $this->countBlanks();
        $nonblanks = $this->countNonBlanks();
        $desired_fullness = 1 - $this->density_constant;
        if (!$blanks && !$nonblanks) {
            $this->randomlyDecideBlankOrNot($idx);
        } else {
            $fullness = $blanks / ($blanks + $nonblanks);
            if ($fullness <  $desired_fullness - $this->density_randomness) {
                $this->setBlank($idx, true);
            } elseif ($fullness >  $desired_fullness + $this->density_randomness) {
                $this->setNonBlank($idx);
            } else {
                $this->randomlyDecideBlankOrNot($idx);
            }
        }
    }

    protected function randomlyDecideBlankOrNot($idx)
    {
        $rand = rand(1,100) / 100;
        $desired_fullness = 1 - $this->density_constant;
        if ($rand < $desired_fullness) {
            $this->setBlank($idx, true);
        } else {
            $this->setNonBlank($idx);
        }
    }

    protected function changeLastUnforced($idx)
    {
        // walk back to last unforced cell; change; unset forced cells after that; rewalk from there
$this->display(3, true);
        while (true) {
            $idx--;
            if ($this->isForced($idx)) {
                $this->setUnknown($idx);
                continue;
            }

            if ($this->isBlank($idx)) {
                $this->setNonBlank($idx);
$this->log('set '.$idx.' data', true);
            } else {
                // careful here -- may create island
                $this->setBlank($idx);
$this->log('set '.$idx.' blank', true);
            }

            $this->setForced($idx, true);
            foreach (array_keys($this->cells) as $i) {
                if ($i > $idx) {
                    unset($this->cellTypes[$i]);
                    unset($this->isForcedCellType[$i]);
                }
            }

            $this->startIdx = $idx + 1;
            break;
        }
    }

    protected function initializeForSettingVals()
    {
        foreach ($this->cells as $idx => $cell) {
            if ($this->cellTypes[$idx] === 'dataCell') {
                $cell->setDataCell(true);
                $this->dataCellCount++;
            }
            $this->cells[$idx] = $cell;
        }
    }

    public function mustBeBlank($idx)
    {
        // topmost && leftmost are all blank
        if ($idx <= $this->width || $idx % $this->width === 0) {
            return true;
        }

        $this->setNonBlank($idx);
        if ($this->violatesMinimumStripSize($idx)) {
            return true;
        }
        $this->setUnknown($idx);

        if ($this->causesOversizeStrip($idx)) {
            return true;
        }

        return false;
    }

    public function mustNotBeBlank($idx)
    {
        // find my nbrs -- every non-blank must have a non-blank above or below as well as to the right or left
        // we do not support strips of size 1

        // topmost && leftmost are all blank
        if ($idx <= $this->width || $idx % $this->width === 0) {
            return false;
        }

        $this->setBlank($idx);
        $nbrs = $this->getNeighboringCoordinates($idx);
        foreach ($nbrs as $nbr) {
            if ($this->isNonBlank($nbr)) {
                if ($this->violatesMinimumStripSize($nbr)) {
$this->log('vmss', true);
                    return true;
                }
            }
        }
        
        $this->setUnknown($idx);
        $col = $idx % $this->width;
        $row = floor($idx / $this->width);

        if ($col > 2) {
            // does my left nbr have a blank left nbr?
            if (!$this->isBlank($idx - 1) && $this->isBlank($idx - 2)) {
$this->log('left nbr have a blank left nbr', true);
                return true;
            }
        }

        if ($col == 2) {
            // is my left nbr non-blank?
            if (!$this->isBlank($idx - 1)) {
$this->log('left nbr non-blank', true);
                return true;
            }
        }

        if ($row > 2) {
            // does my top nbr have a blank top nbr?
            if (!$this->isBlank($idx - $this->width) && $this->isBlank($idx - 2 * $this->width)) {
$this->log('top nbr have a blank top nbr', true);
                return true;
            }
        }

        if ($row == 2) {
            // is my top nbr non-blank?
            if (!$this->isBlank($idx - $this->width)) {
$this->log('top nbr non-blank', true);
                return true;
            }
        }

        $this->island = $this->createsIsland($idx);
if (!empty($this->island)) {
    $x = 'debug';
}
        // more: an island might be ok, particularly if unpopulated now or can be... TBI
        // if a path traverses, 2 islands are formed
        if (!empty($this->island) && !$this->okToRemoveIsland($this->island)) {
$this->log('island', true);
            return true;
        }

        return false;
    }

    protected function violatesMinimumStripSize($idx)
    {
        $strips = $this->findMyStrips($idx, true);
        foreach ($strips as $strip) {
            if (count($strip) < $this->minimum_strip_size) {
                return true;
            }
        }

        return false;
    }

    protected function findMyStrips($idx, $include_unknown = true)
    {
        if ($this->isBlank($idx)) {
            return ['h' => [], 'v'=>[]]; // aint got no strips
        }

        $h = $this->findMyHStrip($idx, $include_unknown);
        $v = $this->findMyVStrip($idx, $include_unknown);

        return ['h' => $h, 'v'=>$v];
    }

    protected function findMyHStrip($idx, $include_unknown = true)
    {
        while (!$this->isBlank(--$idx, !$include_unknown)) {
            //
        }

        return $this->stripToTheRight($idx, $include_unknown);
    }

    protected function findMyVStrip($idx, $include_unknown = true)
    {
        $idx -= $this->width;
        while (!$this->isBlank($idx, !$include_unknown)) {
            $idx -= $this->width;
        }

        return $this->stripBelow($idx, $include_unknown);
    }

    protected function isBlank($idx, $strict = false)
    {
        if ($strict) {
            return !empty($this->cellTypes[$idx]) && $this->cellTypes[$idx] === 'blankCell';
        }

        return empty($this->cellTypes[$idx]) || $this->cellTypes[$idx] === 'blankCell';
    }

    protected function isNonBlank($idx)
    {
        return !empty($this->cellTypes[$idx]) && $this->cellTypes[$idx] !== 'blankCell';
    }

    protected function getNeighboringCoordinates($idx)
    {
        $indexes = [];
        if ($idx >= $this->width) {
            $indexes[] = $idx - $this->width;
        }
        if ($idx < ($this->height - 1) * $this->width) {
            $indexes[] = $idx + $this->width;;
        }
        if ($idx % $this->width) {
            $indexes[] = $idx - 1;
        }
        if ($idx % $this->width < $this->width - 1) {
            $indexes[] = $idx + 1;
        }

        return $indexes;
    }

    protected function createsIsland($idx)
    {
        // by making this blank, is there a wall that isolates cells?
        // such a wall reaches from one side to another stepping to blank nbrs v|h|d
        // so if i can walk from here to 2 diff edges, island
        // temporarily set blank, walk, then set back to unknown:
        $this->setBlank($idx);

        // find some data cell
        foreach ($this->cellTypes as $i => $type) {
            if ($type === 'dataCell') {
                $dataIdx = $i;
                break;
            }
        }

        if (!isset($dataIdx)) {
            return [];
        }
        $web = $this->buildWeb($dataIdx, ['nonblank', 'empty'], [], false);
        $nonBlankCount = $this->countNonBlanks();
        $nonBlankCountWeb = 0;
        foreach ($web as $cellIdx) {
            if ($this->isNonBlank($cellIdx)) {
                $nonBlankCountWeb++;
            }
        }
$this->log($idx.' island?', true);
$this->log($dataIdx.' start data', true);
sort($web);
$this->log($web, true);
$this->display(3, true);
        if ($nonBlankCountWeb < $nonBlankCount) {
$this->log($idx.' DOES create island', true);
            $island = [];
            foreach ($this->cellTypes as $i => $type) {
                if ($type === 'dataCell' && !in_array($i, $web)) {
                    $island[] = $i;
                }
            }
$this->log(json_encode($island).' island', true);
            return $island;
        }

        $this->setUnknown($idx);

        return [];
    }

    protected function getIsland($path)
    {
        // path consists of blanks; of the 2 halves, if the smaller is unpopulated, return it
        // TBI -- depopulate it if density is out of balance
        $blanks = $this->countBlanks();
        $left_half = [];
        $right_half = [];
        foreach (array_keys($this->cellTypes) as $idx) {
            // find one piece of an island
            if (in_array($idx, $path)) {
                continue;
            }
            $left_half = $this->buildWeb($idx, ['nonblank', 'empty'], [], false);
            break;
        }

        $lhc = count($left_half);
        $unacctd = ($this->height * $this->width - $lhc - $blanks);

        if (!$unacctd) {
            return [];
        }

        if ($unacctd > $lhc) {
            return $left_half;
        }

        foreach (array_keys($this->cellTypes) as $idx) {
            // walk till you find sth not on the path or the left half
            if ($this->isBlank($idx)) {
                continue;
            }
            if (in_array($idx, $path)) {
                continue;
            }
            if (in_array($idx, $left_half)) {
                continue;
            }

            $right_half = $this->buildWeb($idx, ['nonblank', 'empty'], [], false);
            break;
        }

        if (empty($right_half)) {
            return [];
        }
        if (count($right_half) > count($left_half)) {
            return $left_half;
        }

        return $right_half;
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
        foreach ($island as $idx) {
            $this->setBlank($idx);
        }
    }

    protected function causesOversizeStrip($idx)
    {
        $this->setNonBlank($idx);
        $strips = $this->findMyStrips($idx, false);
        $this->setUnknown($idx);
        foreach ($strips as $strip) {
            if (count($strip) > $this->maxStripLength) {
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
    }

    protected function setBlank($idx, $remove_island = false)
    {
        $this->cellTypes[$idx] = 'blankCell';
        if ($remove_island && !empty($this->island)) {
            $this->removeIsland($this->island);
        }
$this->log("$idx now blank", true);
    }

    protected function setNonBlank($idx)
    {
        $this->cellTypes[$idx] = 'dataCell';
$this->log("$idx now data", true);
    }

    protected function setUnknown($idx)
    {
        unset($this->cellTypes[$idx]);
    }

    protected function setForced($idx, $forced)
    {
        $this->isForcedCellType[$idx] = $forced;
    }

    protected function isForced($idx)
    {
        return !empty($this->isForcedCellType[$idx]);
    }

    protected function fail($i, $j, $msg)
    {
        $this->draw(false);
        throw new \Exception("failure at $i, $j: " . $msg);
    }

    protected function isUnknown($val)
    {
        return empty($val);
    }

    protected function stripToTheRight($idx, $include_unknown = false, $arr = [])
    {
        $cell = $this->cells[$idx];
        if (!$this->isBlank($idx, $include_unknown)) {
            $arr[] = $cell;
        }

        if ($cell->getCol() < $this->width - 1) {
            return $this->isBlank($idx + 1, $include_unknown) ? $arr : $this->stripToTheRight($idx + 1, $include_unknown, $arr);
        }

        return $arr;
    }

    protected function stripBelow($idx, $include_unknown = false, $arr = [])
    {
        $cell = $this->cells[$idx];
        if (!$this->isBlank($idx, $include_unknown)) {
            $arr[] = $cell;
        }

        if ($cell->getRow() < $this->height - 1) {
            return $this->isBlank($idx + $this->width, $include_unknown) ? $arr : $this->stripBelow($idx + $this->width, $include_unknown, $arr);
        }

        return $arr;
    }

    public function getCells() {
        return $this->cells;
    }

    protected function getFrame()
    {
        $grid = $this->em->getRepository('AppBundle:Grid')->find($this->frameId);
        $height = $grid->getHeight();
        $width = $grid->getWidth();
        $cells = array_fill(0, $width * $height, null);
        foreach ($grid->getCells() as $cell) {
            $idx = $cell->getRow() * $width + $cell->getCol();
            $cells[$idx] = $cell;
        }
        foreach ($cells as $idx => $cell) {
            if (!$cell) {
                $cell = new Cell();
                $col = $idx % $width;
                $row = floor($idx / $width);
                $cell->setCol($col);
                $cell->setRow($row);
                $cell->setDataCell($col && $row); // all of col 0 and row 0 are non-data
                $grid->addCell($cell);
                $cells[$idx] = $cell;
            }
        }

        $this->cells = $cells;
        $name = $this->gridObj->getName();
        $this->gridObj = clone $grid;
        $this->gridObj->setName($name);
    }

    protected function saveFrame()
    {
        $cells = [];
        foreach ($this->gridObj->getCells() as $idx => $cell) {
            if ($this->isBlank($idx)) {
                $cells[] = $cell;
            }
        }
        $this->gridObj->removeAllCells();
        foreach ($cells as $cell) {
            $this->gridObj->addCell($cell);
        }

        $this->em->flush();
    }
}