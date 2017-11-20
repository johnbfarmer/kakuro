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
        $forcedCellType = [],
        $cellChoices = [],
        $setOrder = [],
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
                // if ($idx < $start_idx) {
                //     continue;
                // }
                if (!empty($this->cellTypes[$idx])) {
                    continue;
                }

                $i = $cell->getRow();
                $j = $cell->getCol();
                $this->island = [];

                // if ($this->symmetry && $this->height - $j <= $i) {
// $this->log("Mirror of $idx is ". $this->getMirrorIdx($idx), true);
                    // continue;
                // }


                $must_be_blank = $this->mustBeBlank($idx);
                $must_be_data = $this->mustBeData($idx);
    // $this->log("$idx must be blank:$must_be_blank must be data:$must_be_data", true);
                if ($must_be_blank && !$must_be_data) {
                    $this->setBlank($idx, true, true);
                    $this->setForced($idx, true);
                }

                if (!$must_be_blank && $must_be_data) {
                    $this->setNonBlank($idx, true);
                    $this->setForced($idx, true);
                }

                if (!$must_be_blank && !$must_be_data) {
                    $this->decideBlankOrNot($idx);
                    $this->setForced($idx, false);
                }

                if ($must_be_blank && $must_be_data) {
                    // $this->log("$idx, must be blank & must be data", true);
                    $this->setUnknown($idx);
                    $this->changeLastUnforced($idx);
                    continue 2;
                }
            }

            $this->finished = true;
        }
// $this->display(3, true);
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
// $this->log('f: '.$fullness .', '. $desired_fullness .', '. $this->density_randomness, true);
            if (($this->island && $this->preferToRemoveIsland)
                || (!$this->island && $fullness > $desired_fullness + $this->density_randomness)) {
// $this->log('prefer: '.$this->preferToRemoveIsland, true);
                $this->setBlank($idx, true, true);
            } elseif ($fullness <  $desired_fullness - $this->density_randomness) {
                $this->setNonBlank($idx, true);
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
            $this->setBlank($idx, true, true);
        } else {
            $this->setNonBlank($idx, true);
        }
    }

    protected function changeLastUnforced($idx)
    {
        // walk back to last unforced cell; change; unset forced cells set after that; rewalk from there
// $this->display(3, true);
        while (true) {
            $idx = $this->setOrder[count($this->setOrder) - 1];
            if ($this->isForced($idx)) {
                $this->setUnknown($idx);
                continue;
            }

            if ($this->isBlank($idx)) {
                $this->setNonBlank($idx, true);
            } else {
                // careful here -- may create island
                $this->setBlank($idx, true);
            }

            $this->setForced($idx, true);
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
// $this->log('testing density -- '.$density, true);
        $density_randomness = $this->density_randomness / 2;
        $min_fullness = $this->density_constant - $density_randomness;
        $max_fullness = $this->density_constant + $density_randomness;
        $this->highDensity = $density > $max_fullness;
        $this->lowDensity = $density < $min_fullness;
// gradually extend the randomness to avoid repeated failures
$this->density_randomness *= 1.1;
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
        // todo: rewrite mustBeData to account for non LRTB order
// $this->log('adjustForHighDensity', true);
        $idxs = array_keys($this->cells);
        $this->shuffle($idxs);
        foreach ($idxs as $idx) {
            if ($this->isNonBlank($idx)) {
                if ($this->mustBeData($idx)) {
                    $this->setNonBlank($idx); // may have changed during testing
                    continue;
                }
                $this->setBlank($idx, true, true); // 3rd arg, make sure to remove island
                return;
            }
        }
    }

    protected function adjustForLowDensity() {
// $this->log('adjustForLowDensity', true);
        $idxs = array_keys($this->cells);
        $this->shuffle($idxs);
        foreach ($idxs as $idx) {
            if ($this->isBlank($idx)) {
                if ($this->mustBeBlank($idx)) {
                    $this->setBlank($idx); // may have changed during testing
                    continue;
                }
                $this->setNonBlank($idx, true);
                return;
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

        if ($this->createsIsland($idx, false)) {
            return true;
        }

        return false;
    }

    public function mustBeData($idx)
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

        // check min strip size violation
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
// $this->log($cell->dump(),true);
                    if ($this->isUnknown($cell->getIdx())) {
                        continue 2;
                    }
                }
// $this->log('fail to meet striplencriteria '.$idx. ' '.$len,true);
// $this->display(3, true);
                return true;
            } 
        }

        $this->setBlank($idx);

        // edge row/col island check should not be needed -- but must process all forced decisions 1st
        // if ($row > 1 && $col > 1 && $row < $this->height - 1 && $col < $this->width - 1) {
            $this->island = $this->createsIsland($idx, true);
            $this->preferToRemoveIsland = false;
            if (!empty($this->island)) {
                if (!$this->okToRemoveIsland($this->island)) {
                    return true;
                }
            }
        // }

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

    protected function createsIsland($idx, $blank)
    {
        // by making this blank, is there a wall that isolates cells?
        // such a wall reaches from one side to another stepping to blank nbrs v|h|d
        // so if i can walk from here to 2 diff edges, island
        // temporarily set blank, walk, then set back to unknown:
        if ($blank) {
            $this->setBlank($idx);
        } else {
            $this->setBlank($idx);
        }

        // find some data cell
        if ($blank) {
            foreach ($this->cellTypes as $i => $type) {
                if ($type === 'dataCell') {
                    $dataIdx = $i;
                    break;
                }
            }
        } else {
            $dataIdx = $idx;
        }

        if (!isset($dataIdx)) {
            return [];
        }

        // $web = $this->buildWeb($dataIdx, ['nonblank', 'empty'], [], false);
        $web = $this->findContiguousGroup($dataIdx, ['nonblank', 'empty']);
        $nonBlankCount = $this->countNonBlanks();
        $nonBlankCountWeb = 0;
        foreach ($web as $cellIdx) {
            if ($this->isNonBlank($cellIdx)) {
                $nonBlankCountWeb++;
            }
        }

        // $this->setUnknown($idx);

// $this->log($idx.' island?', true);
// $this->log($dataIdx.' start data', true);
sort($web);
// $this->log($web, true);
// $this->display(3, true);
        if ($nonBlankCountWeb < $nonBlankCount) {
// $this->log($idx.' DOES create island', true);
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
// $this->log(json_encode($island1).' island 1', true);
// $this->log(json_encode($island2).' island 2', true);

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
// $this->log("ok to remove island", true);
        $this->preferToRemoveIsland = abs($desired_fullness - $fullnessWithIsland) > abs($desired_fullness - $fullnessWithoutIsland);
// $this->log("prefer to remove island? ".$this->preferToRemoveIsland, true);

        return true;
    }

    protected function removeIsland($island)
    {
// $this->log('remove island ' . json_encode($island), true);
        foreach ($island as $idx) {
            $this->setBlank($idx, true);
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

    protected function getMirrorIdx($idx)
    {
        $row = floor($idx / $this->width);
        $col = $idx % $this->width;
        return $col * $this->width + $row;
    }

    protected function setBuildValue($idx, $val, $nonTemp)
    {
        $this->cellTypes[$idx] = $val;
        if ($nonTemp && !in_array($idx, $this->setOrder)) {
            $this->setOrder[] = $idx;
        }
// $this->log("$idx now $val", true);
        if ($this->symmetry) {
            $mirrorIdx = $this->getMirrorIdx($idx);
            $this->cellTypes[$mirrorIdx] = $val;
            if ($nonTemp && !in_array($mirrorIdx, $this->setOrder)) {
                $this->setOrder[] = $mirrorIdx;
            }
// $this->log("$mirrorIdx now $val", true);
        }
    }

    protected function setBlank($idx, $nonTemp = false, $removeIsland = false)
    {
        $this->setBuildValue($idx, 'blankCell', $nonTemp);
        if ($removeIsland && !empty($this->island)) {
            $this->removeIsland($this->island);
        }
    }

    protected function setNonBlank($idx, $nonTemp = false)
    {
        $this->setBuildValue($idx, 'dataCell', $nonTemp);
    }

    protected function setUnknown($idx)
    {
        unset($this->cellTypes[$idx]);
        $this->unsetValue($this->setOrder, $idx);
// $this->log("$idx now unknown", true);
        if ($this->symmetry) {
            $mirrorIdx = $this->getMirrorIdx($idx);
            unset($this->cellTypes[$mirrorIdx]);
            $this->unsetValue($this->setOrder, $mirrorIdx);
// $this->log("$mirrorIdx now unknown", true);
        }
    }

    protected function setForced($idx, $forced)
    {
        $this->forcedCellType[$idx] = $forced;
        if ($this->symmetry) {
            $mirrorIdx = $this->getMirrorIdx($idx);
            $this->forcedCellType[$mirrorIdx] = $forced;
        }
    }

    protected function isForced($idx)
    {
        return !empty($this->forcedCellType[$idx]);
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