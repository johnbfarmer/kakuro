<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;

class KakuroReducer extends BaseKakuro
{
    protected 
        $changedStrips,
        $failedStrip = [],
        $failedCell = '',
        $failReason,
        $reductionLevel = 0,
        $gridObj,
        $cells,
        $uiChoices,
        $activeCellIdx = 0,
        $allStrips,
        $indexesNotToProcess = [],
        $hintOnly = false,
        $hint = "Sorry, no hint...",
        $hasUniqueSolution = true,
        $solutions = [],
        $maxNestingLevelForProbing = 1,
        $maxChoicesForProbing = 3,
        $reachedProbeLimit = false,
        $fails = false;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['uiChoices'])) {
            $this->uiChoices = $this->parameters['uiChoices'];
        }
        if (!empty($this->parameters['grid'])) {
            $this->gridObj = $this->parameters['grid'];
        }

        $this->activeCellIdx = !empty($this->parameters['activeCellIdx']) ? (int)$this->parameters['activeCellIdx'] : 0;
        $this->reductionLevel = !empty($this->parameters['level']) ? (int)$this->parameters['level'] : 0;
        if (isset($this->parameters['simpleReduction'])) {
            $this->reductionLevel = !empty($this->parameters['simpleReduction']) ? 4 : 3;
        }
        $this->hintOnly = $this->reductionLevel == 1;
        $this->changedStrips = new ArrayCollection();
    }

    protected function execute()
    {
        parent::execute();
        $this->width = $this->gridObj->getWidth();
        $this->cells = $this->gridObj->getCells();
        $this->allStrips = $this->gridObj->getStrips();

        if (!empty($this->cells)) {
            foreach ($this->cells as $cell) {
                if ($cell->isDataCell()) {
                    $pv = $cell->getPossibleValues();
                    $idx = $cell->getRow() * $this->width + $cell->getCol();
                    $cell->setIdx($idx);
                    $choices = $this->uiChoices[$idx]['choices'];
                    $choices = !empty($choices) ? array_values(array_intersect($choices, $pv)) : $pv;
                    $this->cells[$idx]->setChoices($choices);
                }
            }
        }

        if (!$this->reduce($this->allStrips, $this->reductionLevel)) {
            $this->fails = true;
        }
    }

    protected function reduceByProbe($previousSavedChoiceArray = [], $nestingLevel = 0)
    {
$this->log('reduce by probe -- entering '.json_encode($previousSavedChoiceArray));
        if ($nestingLevel > $this->maxNestingLevelForProbing) {
            $this->log('nesting > '.$this->maxNestingLevelForProbing); // handle better?
            $this->reachedProbeLimit = true;
            return true;
        }
        // no more than 2 solutions for now thanks
        if (!$this->hasUniqueSolution) {
$this->log('reduce by probe -- enough solutions');
            return true;
        }
        // find one or more values to probe, pref with few choices
        // back up so we can restore; we are probing
        $savedChoiceArray = !empty($previousSavedChoiceArray) ? $previousSavedChoiceArray : $this->buildSavedChoicesArray();
$this->log("savedChoiceArray ".json_encode($savedChoiceArray));
        $idxs = $this->getIdxsForProbe($savedChoiceArray);
        $solutionFound = false;
        $solution = [];
        while (!empty($idxs)) {
            $idx = array_shift($idxs);
            $cell = $this->cells[$idx];
            $choices = $cell->getChoices();
            foreach ($choices as $choiceIdx => $choice) {
                // if ()
$this->log("reduce by probe ($nestingLevel) try $choice in ".$cell->coords());
                $allChoicesFailed = true;
                $cell->setChoices([$choice]);
$this->log("savedChoiceArray ".json_encode($savedChoiceArray));
                $strips = $cell->getStripObjects();
                if ($this->reduce($strips, 4)) {
                    if ($this->finished()) {
                        $solutionFound = true;
                        $solution = $this->buildSavedChoicesArray();
                        $this->solutions[md5(serialize($solution))] = $solution;
$this->log("probe produces solution found for  ".$cell->coords().' '.json_encode($solution));
                        $this->hasUniqueSolution = count($this->solutions) < 2;
                        if (!$this->hasUniqueSolution) {
                            $this->restoreSavedChoices($savedChoiceArray);
                            $this->reachedProbeLimit = false;
                            return true; // multiple solutions
                        }
                    } else {
                        // if the probe did not fail nor complete, recurse:
                        if (!$this->reduceByProbe($this->buildSavedChoicesArray(), $nestingLevel + 1)) {
$this->log('probe recursion failed');
                        }
                    }
                    // continue 2; // reduction did not fail or complete, next idx
// for now, if reduction does not finish everything, try another cell to probe
                } else {
                    // standard reduction failed; if not nesting, remove from choices
                    if (!$nestingLevel) { // necessary?
$this->log("reduce fails for $choice; removing " . $cell->coords());
                        if (!$this->removeChoicesNew($cell, [$choice])) {
$this->log("reduce fails for $choice; no more choices " . $cell->coords());
                            // continue;
                        }
                        // continue;
                    }
$this->log("reduce fails for $choice " . $cell->coords().' choices now '.json_encode($cell->getChoices()));
                }
                $allChoicesFailed = false;
                $this->failedStrip = [];
                $this->restoreSavedChoices($savedChoiceArray); // choice not valid; need to restore based on idx
            }
            if ($allChoicesFailed) {
                return false;
            }
            if ($solutionFound) {
                $this->restoreSavedChoices($solution);
                return true;
            }
        }

        return true;
    }

    protected function getIdxsForProbe($savedChoiceArray)
    {
        $ret = [];

        foreach ($savedChoiceArray as $idx => $choices) {
            $ct = count($choices);
            if ($ct > 1 && $ct <= $this->maxChoicesForProbing) {
                $ret[] = $idx;
            }
        }

        return $ret;
    }

    protected function buildSavedChoicesArray()
    {
        $savedChoiceArray = [];
        foreach ($this->cells as $idx => $cell) {
            if ($idx && $cell->isDataCell()) { // reason: somehow $idx is null or empty in some cases, causes trouble
                $savedChoiceArray[$idx] = array_values($cell->getChoices());
            }
        }

        return $savedChoiceArray;
        // return array_values($savedChoiceArray);
    }

    protected function restoreSavedChoices($savedChoiceArray)
    {
$this->log('restoreSavedChoices');
$this->log($savedChoiceArray);
        foreach ($savedChoiceArray as $idx => $choices) {
            if ($idx) {
                $this->cells[$idx]->setChoices($choices);
            }
        }
    }

    protected function finished()
    {
        foreach ($this->cells as $idx => $cell) {
            if ($idx && $cell->isDataCell() && count($this->cells[$idx]->getChoices()) !== 1) {
                return false;
            }
        }

        foreach ($this->allStrips as $strip) {
            $sum = 0;
            $total = $strip->getTotal();
            $cells = $this->getCellsForStrip($strip);
            foreach ($cells as $cell) {
                $sum += current($cell->getChoices());
            }
            if ($sum != $total) {
                return false;
            }
        }

        return true;
    }

    protected function reduce($strips, $level)
    {
        while (!$strips->isEmpty()) {
            $this->changedStrips->clear();
            foreach ($strips as $strip) {
                if (empty($strip)) {
                    continue;
                }
$this->log('considering strip '.$strip->getId());

                $cells = $this->getCellsForStrip($strip);
                $pv = $this->getPossibleValuesForStrip($strip, $cells)['values'];
                $allCellsAreSingleChoice = true;
                foreach ($cells as $cell) {
                    if (!in_array($cell->getIdx(), $this->indexesNotToProcess)) {
                        $choices = $cell->getChoices();
$this->log('considering cell '.$cell->coords().' '.json_encode($choices).' pv: '.json_encode($pv));
                        if (count($choices) === 1) {
                            continue;
                        }
                        $allCellsAreSingleChoice = false;
                        $changed = false;
                        if (empty($choices)) {
                            $choices = $pv;
                            $changed = true;
                        }
                        $newChoices = array_values(array_intersect($pv, $choices));
                        if (empty($newChoices)) {
                            $this->failedStrip = $strip->getId();
                            $this->failedCell = $cell->getIdx();
                            $this->failReason = "No choices for cell " . $cell->dump() . ", " . $strip->dump();
                            return false;
                        }
                        if ($changed || count($newChoices) < count($choices)) {
                            $cell->setChoices($newChoices);
                            $this->addCellsStripsToChanged($cell);
                            if (count($newChoices) === 1) {
                                $this->removeMyChoiceFromNeighbors($cell);
                            }
                            $this->cells[$cell->getIdx()] = $cell; // not needed?
                            if ($this->hintOnly) {
                                return true;
                            }
                        }
                    }
                }

                if ($allCellsAreSingleChoice) {
                    continue;
                }

                if ($level > 2) {
                    if (!$this->adv($strip, $cells)) {
$this->log('adv fails');
                        $this->failedStrip = $strip->getId();
                        $this->failReason .= ", " . $strip->dump();
$this->log($this->failedStrip);
$this->log($this->failReason);
                        return false;
                    }
                }
            }

            $strips = clone $this->changedStrips;
        }

        if ($level > 3) { 
            return $this->reduceByProbe();
        }

        return true;
    }

    protected function adv($strip, $cells)
    {
        $undecided = [];
        $used = [];
        $sum = $strip->getTotal();
        foreach ($cells as $cell) {
            $choices = $cell->getChoices();
            if (count($choices) > 1) {
                $undecided[] = $cell;
            } else {
                $num = current($choices);
                $used[] = $num;
                $sum -= $num;
            }
        }
        $unusedCount = count($cells)-count($used);
$this->log('advanced strip #'.$strip->getId()." $sum over ".$unusedCount.' '.json_encode($used));
        if ($sum < 0) {
            return false;
        }
        if ($sum === 0) {
            return $unusedCount === 0;
        }
        if ($unusedCount === 0) {
            return $sum === 0;
        }

        return $this->advancedStripReduction($undecided, $sum, $used);
    }

    protected function getCellsForStrip($strip)
    {
        if (empty($strip)) {
            return [];
        }
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

    protected function getPossibleValuesForStrip($strip, $cells)
    {
        $sum = $strip->getTotal();
        $len = $strip->getLen();
        $usedNumbers = [];
        $decided = [];
        $undecided = [];
        foreach ($cells as $cell) {
            if (count($cell->getChoices()) === 1) {
                $num = current($cell->getChoices());
                $sum -= $num;
                $len--;
                $usedNumbers[] = $num;
                $decided[] = $cell;
            } else {
                $undecided[] = $cell;
            }
        }

        $pv = $this->gridObj->getPossibleValues($sum, $len, $usedNumbers);

        return [
            'values' => $pv,
            'decided' => $decided,
            'undecided' => $undecided,
            'usedNumbers' => $usedNumbers,
        ];
    }

    protected function advancedStripReduction($cells, $sum, $used)
    {
        // if (!$this->reduceByTooExtreme($cells, $sum, $used)) {
        //     return false;
        // }

        if (!$this->reduceByComplement($cells, $sum, $used)) {
            return false;
        }

        return $this->reduceByPigeonHole($cells, $sum, $used);
    }

    protected function reduceByComplement($cells, $sum, $used)
    {
$this->log("reduceByComplement $sum ".json_encode($used));
        if (count($cells) < 2) {
if (in_array($sum, $used)) {
    $this->log("fail");
} else {
    $this->log("no prob");
}
            return (!in_array($sum, $used));
        }

        // each value in each cell -- is the complement possible?
        foreach ($cells as $indx => $cell) {
            $complement = $cells;
            unset($complement[$indx]);
            $choices = $cell->getChoices();
            foreach ($choices as $idx => $v) {
$this->log("choice $v in ".$cell->coords()  . " possible?");
$this->log("choices: ".json_encode($choices));
                if (!$this->isPossible(array_values($complement), $sum - $v, array_merge($used, [$v]))) {
                    unset($choices[$idx]);
$this->log("removing choice $v from temporary set");
                    if (empty($choices)) {
                        $this->failReason = "No choices reducing by complement cell ".$cell->dump();
$this->failedCell = $cell->getIdx();
$this->log($this->failReason);
                        return false;
                    }
                    if ($this->hintOnly) {
                        $this->hint = $cell->dump() . " cannot have " . $v;
$this->log($this->hint);
                        return false;
                    }
                    $cell->setChoices($choices);
                    $arr_idx = $cell->getRow() * $this->width + $cell->getCol();
                    $this->cells[$arr_idx] = $cell;
                    $this->addCellsStripsToChanged($cell);
                }
$this->log("choice $v in ".$cell->coords()  . " ok");
            }
$this->log($cell->coords().' choices ' . json_encode($cell->getChoices()));
        }

        return true;
    }

    protected function reduceByTooExtreme($cells, $sum, $used)
    {
        // inspired by example sum = 20, choices = [24567],[2456789],[123],[13]
        // no way the 1st or 2nd choice set can include 2 or 4, for example
        foreach ($cells as $indx => $cell) {
            $complement = $cells;
            unset($complement[$indx]);
            $cmax = 0;
            $cmin = 0;
            foreach ($complement as $c) {
                $choices = $c->getChoices();
                if (empty($choices)) {
                    $this->failedCell = $cell->getIdx();
                    $this->failReason = "No choices reducing by \"too extreme\"  ".$cell->dump();
                    return false;
                }
                $cmax += max($choices);
                $cmin += min($choices);
            }
            $choices = $cell->getChoices();
            foreach ($choices as $idx => $v) {
                if ($v + $cmin > $sum || $v + $cmax < $sum) {
                    unset($choices[$idx]);
                    $cell->setChoices($choices);
                    $arr_idx = $cell->getRow() * $this->width + $cell->getCol();
                    $this->cells[$arr_idx] = $cell;
                    $this->addCellsStripsToChanged($cell);
                }
            }
        }

        return true;
    }

    protected function reduceByPigeonHole($cells, $sum, $used)
    {
        // each cell, my choices C; separate cells into groups Y and N based on criteria:
        // 'do you have nothing outside of C' if count(Y) == count(C) remove C from N; fail if count(Y) > count(C)
        // example cells Q->12 R->12 S->123; relative to Q, C=12, Y=[R], N=[S]. After reduction S->3.
$this->log('pigeonhole');
        $disjointGroups = [];
        $disjointGroupValues = [];
        foreach ($cells as $cell) {
            $c = $cell->getChoices();
            $y = [];
            $n = [];
            foreach ($cells as $inner) {
                if (empty(array_diff($inner->getChoices(), $c))) {
                    $y[] = $inner;
                } else {
                    $n[] = $inner;
                }
            }

            if (empty($n)) {
                continue;
            }
            if (count($y) < count($c)) {
                continue;
            }
            if (count($y) > count($c)) {
                $this->failedCell = $cell->getIdx();
                $this->failReason = "Y>C cell ".$cell->dump();
                return false;
            }

            if (!$this->removeChoicesFromCells($n, $c)) {
                $this->failedCell = $cell->getIdx();
                $this->failReason = "Unable to remove choices from cell ".$cell->dump();
                return false;
            }

            if (empty(array_intersect($c, $disjointGroupValues))) {
                $disjointGroups[] = $y;
                $disjointGroupValues = array_merge($c, $disjointGroupValues);
            }
        }

        return $this->reduceByDisjointGroups($cells, $sum, $disjointGroups, $used);
    }

    protected function reduceByDisjointGroups($cells, $sum, $disjointGroups, $used)
    {
        if (empty($disjointGroups)) {
            return true;
        }

        $groups_total = 0;
        foreach ($disjointGroups as $group) {
            foreach ($group as $cell) {
                foreach ($cell->getChoices() as $choice) {
                    if (!in_array($choice, $used)) {
                        $groups_total += $choice;
                        $used[] = $choice;
                    }
                }

                $this->removeByRowAndCol($cells, $cell);
            }
        }

        if (empty($cells)) {
            return true;
        }

        $sum -= $groups_total;

        // $used can be empty as those vals should already have been removed from $cells
        $pv = $this->gridObj->getPossibleValues($sum, count($cells), []);
        foreach ($cells as $cell) {
            $choices = $cell->getChoices();
            $newChoices = array_values(array_intersect($choices, $pv));
            if (empty($newChoices)) {
                $this->failedCell = $cell->getIdx();
                $this->failReason = "choices empty reducing by disjoint groups cell ".$cell->dump();
                return false;
            }
            if (count($newChoices) < count($choices)) {
                $idx = $this->getIndexByRowAndCol($cell);
                $this->cells[$idx]->setChoices($newChoices);
                $this->addCellsStripsToChanged($cell);
            }
        }

        return true;
    }

    protected function addCellsStripsToChanged($cell)
    {
        foreach ($cell->getStripObjects() as $strip) {
            if (!$this->changedStrips->contains($strip)) {
                $this->changedStrips[] = $strip;
            }
        }
    }

    protected function isPossible($cells, $sum, $used)
    {
        if (empty($cells)) {
            return $sum === 0;
        }
$coll = [];
foreach ($cells as $c) {
    $coll[] = $c->getChoices();
}        
$this->log("Can I make $sum from ".json_encode($coll)." without using " .json_encode($used) ."?");
        if (count($cells) === 1) {
            return !in_array($sum, $used) && in_array($sum, $cells[0]->getChoices());
        }
        // if there are 2 in the set, just  test for complement: BUT TAKE USED INTO ACCT
        if (count($cells) === 2) {
            $cells = array_values($cells);
            $choices = $cells[0]->getChoices();
            foreach ($choices as $choice) { // one way is sufficient
                if (in_array($choice, $used)) {
                    continue;
                }
                $complement = $sum - $choice;
                if (in_array($complement, $used) || $choice !== $complement) {
                    continue;
                }
                if (in_array($complement, $cells[1]->getChoices())) {
$this->log("YES I Can make $sum from ".json_encode($coll)."!");
                    return true;
                }
            }
$this->log("NO I Can't make $sum from ".json_encode($coll)." without using " .json_encode($used) . "!");
            // return false;
        }

        $pv = $this->gridObj->getPossibleValues($sum, count($cells), $used);
// $this->log('pv:'.json_encode($pv));
        // foreach ($cells as $idx => $cell) {
        $cell = array_pop($cells);
        $new_choices = array_values(array_intersect($cell->getChoices(), $pv)); 
            // // not good enough. should fail for 
                // a set like C![1,2,3,4,5,6,7,8,9],C2[1,2,3],C3[1,3], sum 19
                // need to select one from each group
                // so call recursively
        if (empty($new_choices)) {
$this->log("NO I Can't make $sum from ".json_encode($coll)."!");            
            return false;
        }
        foreach ($new_choices as $v) {
            if (!$this->isPossible(array_values($cells), $sum - $v, array_merge($used, [$v]))) {
                continue;
            }

            return true;
        }
        // }
$this->log("YES I Can make $sum from ".json_encode($coll)."!");
        return true;
    }

    protected function getMyNeighbors($cell)
    {
        $strips = $cell->getStrips();
        $nbrIdxs = [];
        $idx = $cell ->getIdx();
        foreach ($strips as $stripIdx) {
            $strip = $this->allStrips[$stripIdx];
            $cells = $this->getCellsForStrip($strip);
            foreach ($cells as $c) {
                $i = $c->getIdx();
                if ($idx == $i) {
                    continue;
                }
                $nbrIdxs[] = $i;
            }
        }

        return array_values(array_unique($nbrIdxs));
    }

    protected function removeMyChoiceFromNeighbors($cell)
    {
        $nbrs = $this->getMyNeighbors($cell);
        $choices = $cell->getChoices();
$this->log('cell '.$cell->coords().' removing my choice from nbrs'.json_encode($cell->getChoices()));
        foreach ($nbrs as $cellIdx) {
            if (!$this->removeChoicesNew($this->cells[$cellIdx], $choices)) {
                return false;
            }
        }

        return true;
    }

    protected function removeChoicesFromCells($cells, $choices)
    {
        if (!is_array($cells)) {
            $cells = [$cells];
        }

        foreach ($cells as $cell) {
            if (!$this->removeChoicesNew($cell, $choices)) {
                return false;
            }
        }

        return true;
    }

    protected function removeChoicesNew($cell, $choices_to_remove)
    {
        if (!is_array($choices_to_remove)) {
            $choices_to_remove = [$choices_to_remove];
        }

        $choices = $cell->getChoices();
        $new_choices = array_values(array_diff($choices, $choices_to_remove));

$this->log('removeChoicesNew '.$cell->coords().' '.json_encode($new_choices));
        if (count($choices) <= count($new_choices)) {
$this->log('removeChoicesNew free pass'.$cell->coords().' '.json_encode($choices).' '.json_encode($new_choices));
            return true;
        }

        if (empty($new_choices)) {
$this->log('removeChoicesNew fail'.$cell->coords().' '.json_encode($new_choices));
            return false;
        }
$this->log('removeChoicesNew '.$cell->coords().' '.json_encode($new_choices));
        $cell->setChoices($new_choices);
        $arr_idx = $cell->getRow() * $this->width + $cell->getCol();
        $this->cells[$arr_idx] = $cell;
        $this->addCellsStripsToChanged($cell);
        return true;
    }

    public function display($padding = 10, $frameOnly = false)
    {
        $str = "\n\n" . $this->displayChoicesHeader();
        $this->log($str, true);
        $str = '';
        foreach ($this->cells as $idx => $cell) {
            if ($idx === "") {$this->log($cell->dump(), true);continue;}
            if ($this->isBlank($idx, true)) {
                $c = '.';
            } else {
                $c = implode('', $cell->getChoices());
                // $c = $cell->getRow() . $cell->getCol();
            }
            if ($cell->getCol() < 1) {
                $this->log($str, true);
                $str = "";
            }
            $str .= str_pad($c, $padding, ' ');
        }
        // $str .= "\n";
        $this->log($str, true);
    }

    public function getApiResponse() {
        return $this->getGridForResponse();
    }

    public function getGridForResponse()
    {
        if (true) {
        // if (!$this->fails) {
            $cells = [];
            foreach ($this->uiChoices as $idx => $cell) {
                if ($this->cells[$idx]->isDataCell()) {
                    if ($this->hintOnly) {
                        if ($idx !== $this->activeCellIdx) {
                            $cells[] = $cell;
                            continue;
                        }
                    }
                    $choices = array_values($this->cells[$idx]->getChoices());
                    sort($choices);
                    $cell['choices'] = $choices;
                    $cell['display'] = implode('', $cell['choices']);
                }
                $cells[] = $cell;
            }
        } else {
            $cells = $this->uiChoices;
        }

        $grid = [
            'cells' => $cells, 
            'error' => false, 
            'hasUniqueSolution' => $this->hasUniqueSolution, 
            'reachedProbeLimit' => $this->reachedProbeLimit,
        ];
$this->log('failedStrip:');
$this->log($this->failedStrip);
        if (!empty($this->fails)) {
            $grid['error'] = true;
            $grid['message'] = 'problem reducing (' . $this->failReason . ')';
            $grid['failedStripId'] = $this->failedStrip;
            $grid['failReason'] = $this->failReason;
            $grid['failedCell'] = $this->failedCell;
        }

        if (!$this->hasUniqueSolution) {
            $grid['solutions'] = $this->solutions;
        }

        return $grid;
    }

    public function getHint()
    {
        return ['hint' => $this->hint];
    }
}