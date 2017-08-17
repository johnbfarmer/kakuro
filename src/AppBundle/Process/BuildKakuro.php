<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;
use AppBundle\Entity\Strip;
use AppBundle\Entity\Solution;

class BuildKakuro extends BaseKakuro
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
        $grid,
        $sums = [],
        $rank = 0,
        $idx = 0,
        $lastChoice = [],
        $lastSimplifyIdx = -1,
        $forbiddenValues = [],
        $testResult,
        $minimum_strip_size = 2,
        $notReducible = false,
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
    }

    public function execute()
    {
        parent::execute();
        $this->createKakuro();
    }

    public function createKakuro()
    {
        $grid = $this->em->getRepository('AppBundle:Grid')->findOneByName('aaatest');
        if ($grid) {
            $this->em->remove($grid);
            $this->em->flush();
        }
        $this->gridObj = new Grid();
        $this->gridObj->setName('aaatest');
        $this->gridObj->setWidth($this->width);
        $this->gridObj->setHeight($this->height);
        $this->em->persist($this->gridObj);
        $this->buildInitialFrame();
        $this->buildFrame();
        $this->initializeForSettingVals();
        $this->addNumbers();
        // $this->calculateStrips();
        // $this->testUnique();
        $this->save();
    }

    public function buildInitialFrame()
    {
        for ($i=0; $i < $this->width; $i++) {
            for ($j=0; $j < $this->width; $j++) {
                $cell = new Cell();
                $this->gridObj->addCell($cell);
                $cell->setLocation($i, $j);
                $this->cells[$cell->getIdx()] = $cell;
            }
        }
    }

    public function buildFrame($start_idx = 0)
    {
        $start_i = $start_idx % $this->width; // TBI?
        $start_j = floor($start_idx / $this->width);
        foreach (array_keys($this->cellChoices) as $tmp_idx) {
            if ($tmp_idx >= $start_idx) {
                unset($this->cellChoices);
            }
        }
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
                return $this->changeLastUnforced($idx);
            }
        }

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
        while (true) {
            $idx--;
            if ($this->isForced($idx)) {
                $this->setUnknown($idx);
                continue;
            }

            if ($this->isBlank($idx)) {
                $this->setNonBlank($idx);
            } else {
                // careful here -- may create island
                $this->setBlank($idx);
            }

            $this->setForced($idx, true);
            return $this->buildFrame($idx + 1);
        }
    }

    protected function changeLastUnforcedNumber()
    {
$this->log($this->lastChoice, true);
        $idx = $this->getPreviousCellWithChoices();
$this->log("last unforced = $idx", true);
        if (empty($this->forbiddenValues[$idx])) {
            $this->forbiddenValues[$idx] = [];
        }
        $val = $this->cellChoices[$idx];
$this->log("$idx $val is forbidden", true);
        $this->forbiddenValues[$idx][] = $val;
        $idxs = [$idx];
        for ($i = $idx + 1; $i < count($this->cells); $i++) {
            $this->forbiddenValues[$i] = [];
            $this->cellChoices[$i] = null;
            $idxs[] = $i;
        }
        $this->addNumbers($idxs);
    }

    protected function getPreviousCellWithChoices()
    {
        return array_pop($this->lastChoice);
    }

    protected function initializeForSettingVals()
    {
        foreach ($this->cells as $idx => $cell) {
            if ($this->cellTypes[$idx] === 'dataCell') {
                $cell->setDataCell(true);
            }
            $this->cells[$idx] = $cell;
        }
    }

    protected function addNumbers($idxs = [])
    {
        if (empty($idxs)) {
            $idxs = array_keys($this->cells);
        }
        foreach ($idxs as $idx) {
            $this->addNumber($idx);
            // if (empty($this->cells[++$idx])) {
            //     break;
            // }
        }

        $this->calculateStrips();
        $this->makeEasilyReducible();
        $this->testUnique();
    }

    protected function addNumber($idx)
    {
        $cell = $this->cells[$idx];
        if (!$this->isBlank($idx)) {
            $taken = $this->getTaken($idx);
// $this->log($this->cellChoices, true);

            $available = array_values(array_diff($this->number_set, $taken));
            // another thing to do:
            


            $available = $this->filterNumsThatCauseNonUnique($cell, $available);


            if (empty($available)) {
$this->log("nothing available", true);
                $this->changeLastUnforcedNumber();
            } else {
                $this->selectValue($idx, $available);
            }
        }
    }

    protected function getTaken($idx)
    {
        $strips = $this->findMyStrips($idx);
        $available = $this->number_set;
        $taken = !empty($this->forbiddenValues[$idx]) ? $this->forbiddenValues[$idx] : [];
$this->log($taken, true);
        foreach ($strips as $strip) {
            foreach ($strip as $stripCell) {
                $tmp_idx = $stripCell->getIdx();
                $val = !empty($this->cellChoices[$tmp_idx]) ? $this->cellChoices[$tmp_idx] : 0;
                if (!in_array($val, $taken)) {
                    $taken[] = $val;
                }
            }
        }

        return $taken;
    }

    protected function filterNumsThatCauseNonUnique($cell, $available)
    {
        // cannot have a set of 2 cells from 2 strips in which the values can be swapped
        // 
        // example
        // 3 1
        // 1 3
        // or 
        // 1 2 3
        // 6 7 8
        // 3 5 1

        $idx = $cell->getIdx();
        $strips = $this->findMyStrips($idx, false);
        if (empty($strips)) {
            return $available;
        }

        $commonValuedCellPairs = $this->interesectByValue($strips);
        if (empty($commonValuedCellPairs)) {
            return $available;
        }
// $this->log('jbf', true);
// $this->log($cell->speak(), true);
// foreach ($commonValuedCellPairs as $pair) {
//     foreach ($pair as $c) {
//         $this->log($c->speak(), true);
//     }
// }
        foreach ($commonValuedCellPairs as $pair) {
            $intersection = $this->interesect($pair, $cell);
            if ($intersection) {
                $this->unsetValue($available, $intersection->getChoice());
$this->log('note '.$idx.' cannot have ' .$intersection->getChoice(), true);
            }
        }

        return $available;
    }

    protected function interesectByValue($strips)
    {
        $cells = [];
        foreach ($strips['h'] as $cell) {
            $choice = $cell->getChoice();
            if (!$choice) {
                continue;
            }
            foreach ($strips['v'] as $vCell) {
                if ($vCell->getChoice() === $choice) {
                    $cells[] = [$cell, $vCell];
                    continue 2;
                }
            }
        }

        return $cells;
    }

    /*
     * given two cells, find where their strips intersect, ignoring one of the points of intersection
     */
    protected function interesect($cells, $ignoreCell)
    {
        // $idx of intersection is cell[0]->row cell[1]->col and vice versa
        $interesectionRow = $cells[0]->getRow() == $ignoreCell->getRow() ? $cells[1]->getRow() : $cells[0]->getRow();
        $interesectionCol = $cells[0]->getCol() == $ignoreCell->getCol() ? $cells[1]->getCol() : $cells[0]->getCol();
        $idx = $interesectionRow * $this->width + $interesectionCol;

        $strips = $this->findMyStrips($cells[0]->getIdx());
        $inStrip = false;
        foreach ($strips as $strip) {
            foreach ($strip as $stripCell) {
                if ($stripCell->getIdx() == $idx) {
                    $inStrip = true;
                }
            }
        }

        if (!$inStrip) {
            return null;
        }

        $strips = $this->findMyStrips($cells[1]->getIdx());
        foreach ($strips as $strip) {
            foreach ($strip as $stripCell) {
                if ($stripCell->getIdx() == $idx) {
                    return $stripCell;
                }
            }
        }

        return null;
    }

    protected function selectValue($idx, $choices)
    {
        if (count($choices) > 1) {
            $this->lastChoice[] = $idx;
        }

        if (count($choices) == 1) {
            $this->unsetValue($this->lastChoice, $idx);
        }


        $index = rand(1, count($choices)) - 1;
        $choices = array_values($choices); // JIC assoc array
        $val = $choices[$index];
        $this->cellChoices[$idx] = $val;
        $this->cells[$idx]->setChoice($val);
$this->log("set $idx to $val", true);
    }

    protected function makeEasilyReducible()
    {
        // $cell = $this->cells[$this->lastChoice[count($this->lastChoice) - 1]];
        // $strips = $cell->getStripObjects();
        // need a more powerful function that KakuroReducer::reduce since larger grids do not reduce all the way with currect techniques. perhaps a 3rd arg that probes (guesses)
$this->log('test easily reduc', true);
$this->display(3);
        $parameters = [
            'grid' => $this->gridObj,
            'simpleReduction' => false,
        ];
        KakuroReducer::autoExecute($parameters);
        $mostChoices = 1;
        foreach($this->cells as $cell) {
            $choices = $cell->getChoices();
            $choiceCount = count($choices);
            if ($choiceCount > $mostChoices) {
                $idx = $cell->getIdx();
                if ($idx == $this->lastSimplifyIdx) {
                    continue;
                }
                $strips = $cell->getStripObjects();
                $taken = $this->getTaken($idx);
                $possible = array_diff($choices, $taken);
                if (empty($possible)) {
                    continue;
                }
$this->log($cell->speak(), true);
                $choicesForCandidate = $possible;
                $mostChoices = $choiceCount;
                $idxToChange = $idx;
            }
        }

        if ($mostChoices > 1) {
            $idx = $idxToChange;
$this->log("change cell $idx", true);
            $this->lastSimplifyIdx = $idx;
            $this->forbiddenValues[$idx][] = $this->cellChoices[$idx];
            $this->simplify($idx, $choicesForCandidate);
        } else {
$this->log('yes easily reduc', true);
        }
    }

    protected function simplify($idx, $choices)
    {
$this->log('needs simplification', true);
        // foreach cell get choice count, pick highest, but not last one picked
        // try all vals (less current) pick the one with fewest total choices
        $cell = $this->cells[$idx];
        $choices = $this->filterNumsThatCauseNonUnique($cell, $choices);
        if (empty($choices)) {
            throw new \Exception('no choices');
        }
        $lowestCount = count($this->number_set) * $this->width * $this->height;
        $newVal = 0;

        foreach ($choices as $choice) {
            $parameters = [
                'targetIdx' => $idx,
                'targetValue' => $choice,
                'grid' => $this->gridObj,
            ];
            $testResult = ChoiceCounter::autoExecute($parameters, null)->getResult();
$this->log($testResult, true);
            $choiceCount = $testResult['choiceCount'];
            if ($choiceCount && $choiceCount < $lowestCount) {
                 $lowestCount = $choiceCount;
                 $newVal = $choice;
            }
        }
if (!$newVal) {
    throw new \Exception("no newVal");
}
        $this->selectValue($idx, [$newVal]);
        $this->calculateStrips();
        $this->makeEasilyReducible();
    }

    protected function testUnique()
    {
$this->display(3);
        $idx = max(array_keys($this->cellChoices));
        $cell = $this->cells[$idx];
        $choice = $cell->getChoice();
        $cell->calculatePossibleValues();
        $pv = $cell->getPossibleValues();
        $this->unsetValue($pv, $choice);
        $success = true;
        foreach ($pv as $val) {
            if ($this->hasSolution($idx, $val)) {
                $success = false;
                break;
            }
        }
        if ($success) {
            return true;
        }

        if ($this->notReducible) {
            // $this->simplify();
            throw new \Exception("Hmmm");
        } else {
            $this->changeLastUnforcedNumber();
        }
    }

    protected function hasSolution($idx, $val)
    {
        $parameters = [
            'testIdx' => $idx,
            'testVal' => $val,
            'grid' => $this->gridObj,
        ];
        $this->testResult = UniquenessTester::autoExecute($parameters, null)->getResult();
        $this->notReducible = $this->testResult['notReducible'];
        return $this->testResult['hasSolution'];
    }

    protected function computeChoices()
    {
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlank($cell)) {
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

    protected function calculateStrips()
    {
        $stripIdx = 0;
        foreach ($this->cells as $idx => $cell) {
            if ($this->isBlank($idx)) {
                $stripH = $this->arrayToStrip($this->stripToTheRight($idx), $stripIdx);
                if (!empty($stripH)) {
                    $this->gridObj->addStrip($stripH, $stripIdx++);
                    $cell->setLabelH($stripH->getTotal());
                }
                $stripV = $this->arrayToStrip($this->stripBelow($idx), $stripIdx);
                if (!empty($stripV)) {
                    $this->gridObj->addStrip($stripV, $stripIdx++);
                    $cell->setLabelV($stripV->getTotal());
                }
            }
        }
    }

    protected function arrayToStrip($arr, $idx)
    {
        if (empty($arr)) {
            return null;
        }
        $strip = new Strip();
        $strip->setId($idx);
        $startRow = $this->width;
        $startCol = $this->height;
        $stopRow = 0;
        $stopCol = 0;
        $total = 0;
        foreach ($arr as $cell) {
            $row = $cell->getRow();
            $col = $cell->getCol();
            if ($row < $startRow) {
                $startRow = $row;
            }
            if ($col < $startCol) {
                $startCol = $col;
            }
            if ($row > $stopRow) {
                $stopRow = $row;
            }
            if ($col > $stopCol) {
                $stopCol = $col;
            }

            $total += $cell->getChoice();
        }

        $strip->setStartRow($startRow);
        $strip->setStartCol($startCol);
        $strip->setStopRow($stopRow);
        $strip->setStopCol($stopCol);
        $strip->setLen(count($arr));
        $strip->setTotal($total);
        $dir = $startRow == $stopRow ? 'h' : 'v';
        $strip->setDir($dir);
        $strip->setPossibleValues($this->gridObj->getPossibleValues($strip->getTotal(), $strip->getLen(), []));

        foreach ($arr as $cell) {
            if ($dir == 'h') {
                $this->cells[$cell->getIdx()]->setStripH($idx);
            } else {
                $this->cells[$cell->getIdx()]->setStripV($idx);
            }
        }

        return $strip;
    }

    public function display($padding = 10)
    {
        $str = "\ntest solution\n" . $this->displayChoicesHeader();

        foreach ($this->cells as $idx => $cell) {
            if ($this->isBlank($idx)) {
                $c = '.';
            } else {
                $c = $cell->getChoice();
            }
            if ($cell->getCol() < 1) {
                $str .= "\n";
            }
            $str .= str_pad($c, $padding, ' ');
        }
        $str .= "\n";
        $this->log($str, true);
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
        $this->setBlank($idx);
        $nbrs = $this->getNeighboringCoordinates($idx);
        foreach ($nbrs as $nbr) {
            if ($this->isNonBlank($nbr)) {
                if ($this->violatesMinimumStripSize($nbr)) {
                    return true;
                }
            }
        }
        
        $this->setUnknown($idx);
        $i = $idx % $this->width;
        $j = floor($idx / $this->width);

        if ($i > 1) {
            // does my left nbr have a blank left nbr?
            if (!$this->isBlank($idx - $this->width) && $this->isBlank($idx - 2 * $this->width)) {
                return true;
            }
        }

        if ($i == 1) {
            // is my left nbr non-blank?
            if (!$this->isBlank($idx - $this->width)) {
                return true;
            }
        }

        if ($j > 1) {
            // does my top nbr have a blank top nbr?
            if (!$this->isBlank($idx - 1) && $this->isBlank($idx - 2)) {
                return true;
            }
        }

        if ($j == 1) {
            // is my top nbr non-blank?
            if (!$this->isBlank($idx - 1)) {
                return true;
            }
        }

        $this->island = $this->createsIsland($idx);
        // more: an island might be ok, particularly if unpopulated now or can be... TBI
        // if a path traverses, 2 islands are formed
        if (!empty($this->island) && !$this->okToRemoveIsland($this->island)) {
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
        list($creates_island, $path) = $this->walkToEdges($idx, ['blank']);
// if ($creates_island) {
// $this->log("path for $i $j", true);
    // var_dump(json_encode($path));
// }
        // more to do -- an island is ok if it does not have 'x' values on it
        $island = $creates_island ? $this->getIsland($path) : [];
        $this->setUnknown($idx);
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

    // if we set ij blank, get the path of blanks created
    protected function walkToEdges($idx, $val = 0)
    {
        $path = $this->buildWeb($idx, ['blank']);
        $edges = [];
        foreach ($path as $idx) {
            $i = $idx % $this->width;
            $j = floor($idx / $this->width);
            if ($i == 0 || $i == $this->width - 1 || $j == 0 || $j == $this->height - 1) {
                $edges[] = $idx;
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

    protected function causesOversizeStrip($idx)
    {
        $this->setNonBlank($idx);
        $strips = $this->findMyStrips($idx);
        $this->setUnknown($idx);
        foreach ($strips as $strip) {
            if (count($strip) > count($this->number_set)) {
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

    protected function setBlank($idx, $remove_island = false)
    {
        $this->cellTypes[$idx] = 'blankCell';
        if ($remove_island && !empty($this->island)) {
            $this->removeIsland($this->island);
        }
    }

    protected function setNonBlank($idx)
    {
        $this->cellTypes[$idx] = 'dataCell';
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

    protected function calculateStripTotal($strip)
    {
        $sum = 0;
        foreach ($strip as $cell) {
            $sum += $this->cellChoices[$cell->getIdx()];
        }

        return $sum;
    }

    protected function save()
    {
        // only anchors get written to the db
$this->log('unique solution found', true);
$this->display(3);
        $cells = [];
        foreach ($this->gridObj->getCells() as $idx => $cell) {
            if ($this->isBlank($idx)) {
                $cells[] = $cell;
            } else {
                $solution = new Solution();
                $solution->setRow($cell->getRow());
                $solution->setCol($cell->getCol());
                $solution->setChoice($cell->getChoice());
                $this->gridObj->addSolution($solution);
            }
        }
        $this->gridObj->removeAllCells();
        foreach ($cells as $cell) {
            $this->gridObj->addCell($cell);
        }

        $this->em->flush();
        $this->gridObj->dumpTable();
    }
}