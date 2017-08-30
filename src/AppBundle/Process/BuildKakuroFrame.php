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
        $density_randomness = 0.1,
        $highDensity = false,
        $lowDensity = false,
        $symmetry = false,
        $finished = false,
        $grid,
        $idx = 0,
        $frameId = 0,
        $minimumStripSize = 2,
        $dataCellCount = 0,
        $startIdx = 0,
        $island = [],
        $preferToRemoveIsland = false;

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
            while (!$this->testDensity()) {
                $this->adjustForDensity();
            }
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
        $desired_fullness = $this->density_constant;
        if (!$blanks && !$nonblanks) {
            $this->randomlyDecideBlankOrNot($idx);
        } else {
            $fullness = $nonblanks / ($blanks + $nonblanks);
$this->log('f: '.$fullness .', '. $desired_fullness .', '. $this->density_randomness, true);
            if (($this->island && $this->preferToRemoveIsland)
                || (!$this->island && $fullness > $desired_fullness + $this->density_randomness)) {
$this->log('prefer: '.$this->preferToRemoveIsland, true);
                $this->setBlank($idx, true);
            } elseif ($fullness <  $desired_fullness - $this->density_randomness) {
                $this->setNonBlank($idx);
            } else {
                $this->randomlyDecideBlankOrNot($idx);
            }
        }
    }

    protected function randomlyDecideBlankOrNot($idx)
    {
        $rand = rand(1,100) / 100;
        $desired_fullness = $this->density_constant;
        if ($rand > $desired_fullness) {
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

    protected function getDensity() {
        $blanks = $this->countBlanks();
        $nonblanks = $this->countNonBlanks();
        return $nonblanks / ($blanks + $nonblanks);
    }

    protected function testDensity() {
        $density = $this->getDensity();
        $density_randomness = $this->density_randomness / 2;
        $min_fullness = $this->density_constant - $density_randomness;
        $max_fullness = $this->density_constant + $density_randomness;
        $this->highDensity = $density > $max_fullness;
        $this->lowDensity = $density < $min_fullness;
        return !$this->highDensity && !$this->lowDensity;
    }

    protected function adjustForDensity() {
        // throw new \Exception("Density Failure");
        if ($this->highDensity) {
            $this->adjustForHighDensity();
        } else {
            $this->adjustForLowDensity();
        }
    }

    protected function adjustForHighDensity() {
        // shuffle and got thru, each cell if nonblank, check if can be blank
        // modifications -- check min strip modified perhaps -- get strips, pluck this out
        // now have 4 (poss empty) strips. if empty, non violation. check min length elsewise
        // todo: rewrite mustNotBeBlank to account for non LRTB order
$this->log('adjustForHighDensity', true);
        $idxs = array_keys($this->cells);
        $this->shuffle($idxs);
        foreach ($idxs as $idx) {
            if ($this->isNonBlank($idx)) {
                if ($this->mustNotBeBlank($idx)) {
                    continue;
                }
                $this->setBlank($idx);
                break;
            }
        }
    }

    protected function adjustForLowDensity() {
$this->log('adjustForLowDensity', true);
        $idxs = array_keys($this->cells);
        $this->shuffle($idxs);
        foreach ($idxs as $idx) {
            if ($this->isBlank($idx)) {
                if ($this->mustBeBlank($idx)) {
                    continue;
                }
                $this->setNonBlank($idx);
                break;
            }
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
        
        if ($this->causesOversizeStrip($idx)) {
            return true;
        }

        return false;
    }

    public function mustNotBeBlank($idx)
    {
        // topmost && leftmost are all blank
        if ($idx <= $this->width || $idx % $this->width === 0) {
            return false;
        }

        $col = $idx % $this->width;
        $row = floor($idx / $this->width);
        $this->setBlank($idx);

        // if this being blank will cause the entire row/col to be blank, it must not be blank
        if ($this->hasAllBlankRow($idx) && $col >= $this->width - $this->minimumStripSize) {
            return true;
        }

        if ($this->hasAllBlankCol($idx) && $row >= $this->height - $this->minimumStripSize) {
            return true;
        }

        $this->setNonBlank($idx); // to get strips

        $strips = $this->findMyStrips($idx);
        $substrips = [];
        foreach ($strips as $strip) {
            $substrip = [];
            foreach ($strip as $cell) {
                if ($cell->getIdx() == $idx) {
                    $substrips[] = $substrip;
                    $substrip = [];
                } else {
                    $substrip[] = $cell;
                }
            }
            $substrips[] = $substrip;
        }

        foreach ($substrips as $substrip) {
            $len = count($substrip);
            if ($len && $len < $this->minimumStripSize) {
                // any unknowns makes this ok
                foreach ($substrip as $cell) {
$this->log($cell->dump(),true);
                    if ($this->isUnknown($cell->getIdx())) {
                        continue 2;
                    }
                }
$this->log('fail '.$idx. ' '.$len,true);
$this->display(3, true);
                return true;
            } 
        }

        $this->setBlank($idx);

        if ($row >= 2 && $col >= 2) {
            $this->island = $this->createsIsland($idx);
            $this->preferToRemoveIsland = false;
            if (!empty($this->island)) {
                if (!$this->okToRemoveIsland($this->island)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasAllBlankRow($idx) {
        $firstInRow = $idx - floor($idx / $this->width);
        $i = $firstInRow;
        while (++$i <= $firstInRow + $this->width) {
            if (!$this->isBlank($i)) {
                return false;
            }
        }

        return true;
    }

    protected function hasAllBlankCol($idx) {
        $idx -= $this->width;
        $i = $idx % $this->width;
        while ($i < $this->width * $this->height) {
            if (!$this->isBlank($i)) {
                return false;
            }
            $i += $this->width;
        }

        return true;
    }

    protected function violatesMinimumStripSize($idx)
    {
        $strips = $this->findMyStrips($idx, true);
        foreach ($strips as $strip) {
            if (count($strip) < $this->minimumStripSize) {
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

        // $this->setUnknown($idx);

$this->log($idx.' island?', true);
$this->log($dataIdx.' start data', true);
sort($web);
$this->log($web, true);
$this->display(3, true);
        if ($nonBlankCountWeb < $nonBlankCount) {
$this->log($idx.' DOES create island', true);
            $island1 = [];
            $island2 = [];
            foreach ($this->cellTypes as $i => $type) {
                if ($type === 'dataCell') {
                    if (in_array($i, $web)) {
                        $island1[] = $i;
                    } else {
                        $island2[] = $i;
                    }
                }
            }
$this->log(json_encode($island1).' island 1', true);
$this->log(json_encode($island2).' island 2', true);

            return count($island1) >= count($island2) ? $island2 : $island1;
        }

        $this->setUnknown($idx);

        return [];
    }

    protected function okToRemoveIsland($island)
    {
        // more to do -- cannot create empty row/col
        $blanks = $this->countBlanks();
        $nonblanks = $this->countNonBlanks();
        $percentDone = ($blanks + $nonblanks) / ($this->width - 1) * ($this->height - 1);
        $desired_fullness = $this->density_constant;
        $densityRandomness = $this->density_randomness / $percentDone;
        $islandSize = count($island);
        // current cell is set to blank. remove island means status quo; not removing island means set to data cell
        $fullnessWithoutIsland = ($nonblanks - $islandSize) / ($blanks + $nonblanks);
        $fullnessWithIsland = ($nonblanks + 1) / ($blanks + $nonblanks);
        if ($fullnessWithoutIsland < $desired_fullness - $densityRandomness) {
            return false;
        } 

        $this->preferToRemoveIsland = abs($desired_fullness - $fullnessWithIsland) > abs($desired_fullness - $fullnessWithoutIsland);

        return true;
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
        $strips = $this->findMyStrips($idx, false);
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

    protected function setBlank($idx, $removeIsland = false)
    {
        $this->cellTypes[$idx] = 'blankCell';
        if ($removeIsland && !empty($this->island)) {
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

    protected function isUnknown($idx)
    {
        return empty($this->cellTypes[$idx]);
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