<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;

class KakuroReducer extends BaseKakuro
{
    protected 
        $changedStrips,
        $failedStrip = [],
        $failReason,
        $reductionLevel = 0,
        $gridObj,
        $cells,
        $uiChoices,
        $allStrips,
        $indexesNotToProcess = [],
        $hintOnly = false,
        $oneStep = false,
        $hint = "Sorry, no hint...",
        $hasUniqueSolution = true,
        $solutions = [],
        $maxNestingLevelForProbing = 1,
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

        $this->reductionLevel = !empty($this->parameters['level']) ? (int)$this->parameters['level'] : 0;
        if (isset($this->parameters['simpleReduction'])) {
            $this->reductionLevel = !empty($this->parameters['simpleReduction']) ? 4 : 3;
        }
        $this->oneStep = $this->reductionLevel == 1;
        $this->hintOnly = !empty($this->parameters['hintOnly']); // not used currently
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

        if ($this->reductionLevel === 2) {
            return true;
        }

        if (!$this->reduce($this->allStrips, 1 + $this->reductionLevel)) { // 1 + because I want to probe...
            $this->fails = true;
        }
    }

    protected function reduceByProbe($previousSavedChoiceArray = [], $nestingLevel = 0)
    {
        if ($nestingLevel > $this->maxNestingLevelForProbing) {
$this->log('nesting > '.$this->maxNestingLevelForProbing); // handle better?
            $this->reachedProbeLimit = true;
            return true;
        }
        // no more than 2 solutions for now thanks
        if (!$this->hasUniqueSolution) {
            return true;
        }
        // find one or more values to probe, pref with few choices
        // back up so we can restore; we are probing
        $savedChoiceArray = !empty($previousSavedChoiceArray) ? $previousSavedChoiceArray : $this->buildSavedChoicesArray();
        $idxs = $this->getIdxsForProbe($savedChoiceArray);
        $solutionFound = false;
        $solution = [];
        while (!empty($idxs)) {
            $idx = array_shift($idxs);
            $cell = $this->cells[$idx];
            $choices = $cell->getChoices();
            foreach ($choices as $choice) {
                $cell->setChoices([$choice]);
                $strips = $cell->getStripObjects();
                if ($this->reduce($strips, 4)) {
                    if ($this->finished()) {
                        $solutionFound = true;
                        $solution = $this->buildSavedChoicesArray();
                        $this->solutions[md5(serialize($solution))] = $solution;
                        $this->hasUniqueSolution = count($this->solutions) < 2;
                        if (!$this->hasUniqueSolution) {
                            $this->restoreSavedChoices($savedChoiceArray);
                            $this->reachedProbeLimit = false;
                            return true; // multiple solutions
                        }
                    } else {
                    // if the probe did not fail nor complete, recurse:
                        if (!$this->reduceByProbe($this->buildSavedChoicesArray(), $nestingLevel + 1)) {
$this->log('recursion failed');
                        }
                    }
                    // continue 2; // reduction did not fail or complete, next idx
// for now, if reduction does not finish everything, try another cell to probe
                } else {
                    // standard reduction failed; if not nesting, remove from choices
                    if (!$nestingLevel) {
                        $this->removeChoicesNew($cell, [$choice]);
                    }
                }
                $this->failedStrip = [];
                $this->restoreSavedChoices($savedChoiceArray); // choice not valid; need to restore based on idx
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
            if (count($choices) === 2) {
                $ret[] = $idx;
            }
        }

        if (true) { // tbi use levels instead of "true"
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 3) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 4) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 5) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 6) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 7) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 8) {
                    $ret[] = $idx;
                }
            }
        }

        if (true) {
            foreach ($savedChoiceArray as $idx => $choices) {
                if (count($choices) === 9) {
                    $ret[] = $idx;
                }
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

                $cells = $this->getCellsForStrip($strip);
                $pv = $this->getPossibleValuesForStrip($strip, $cells)['values'];
                foreach ($cells as $cell) {
                    if (!in_array($cell->getIdx(), $this->indexesNotToProcess)) {
                        $choices = $cell->getChoices();
                        if (count($choices) === 1) {
                            continue;
                        }
                        $changed = false;
                        if (empty($choices)) {
                            $choices = $pv;
                            $changed = true;
                        }
                        $newChoices = array_values(array_intersect($pv, $choices));
                        if (empty($newChoices)) {
                            $this->failedStrip = $strip->getId();
                            $this->failReason = "No choices for cell " . $cell->dump() . ", strip " . $strip->dump();
                            return false;
                        }
                        if ($changed || count($newChoices) < count($choices)) {
                            $cell->setChoices($newChoices);
                            $this->addCellsStripsToChanged($cell);
                            if (count($newChoices) === 1) {
                                $this->removeMyChoiceFromNeighbors($cell);
                            }
                            $this->cells[$cell->getIdx()] = $cell; // not needed?
                            if ($this->oneStep) {
                                return true;
                            }
                        }
                    }
                }

                if ($level > 3) {
                    if (!$this->adv($strip, $cells)) {
                        $this->failedStrip = $strip->getId();
                        $this->failReason .= ", strip " . $strip->dump();
                        return false;
                    }
                }
            }

            $strips = clone $this->changedStrips;
        }

        if ($level > 4) { 
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
        if (!$this->reduceByComplement($cells, $sum, $used)) {
            return false;
        }

        return $this->reduceByPigeonHole($cells, $sum, $used);
    }

    protected function reduceByComplement($cells, $sum, $used)
    {
        if (count($cells) < 2) {
            return true;
        }

        // each value in each cell -- is the complement possible?
        foreach ($cells as $indx => $cell) {
            $complement = $cells;
            unset($complement[$indx]);
            $choices = $cell->getChoices();
            foreach ($choices as $idx => $v) {
                if (!$this->isPossible($complement, $sum - $v, array_merge($used, [$v]))) {
                    unset($choices[$idx]);
                    if (empty($choices)) {
                        $this->failReason = "No choices reducing by complement cell ".$cell->dump();
                        return false;
                    }
                    if ($this->hintOnly) {
                        $this->hint = $cell->dump() . " cannot have " . $v;
                        return false;
                    }
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
                $this->failReason = "Y>C cell ".$cell->dump();
                return false;
            }

            if (!$this->removeChoicesFromCells($n, $c)) {
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

    protected function reduceDuplet($sum, $cell, $other)
    {
        $choices = $cell->getChoices();
        $pv = [];
        foreach($choices as $idx => $choice) {
            if (in_array($sum - $choice, $other->getChoices())) {
                $pv[] = $choice;
            }
        }

        return $pv;
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
        // if there are 2 in the set, just  test for complement: BUT TAKE USED INTO ACCT
        if (count($cells) === 2) {
            $cells = array_values($cells);
            $choices = $cells[0]->getChoices();
            foreach ($choices as $choice) { // one way is sufficient
                if (in_array($choice, $used)) {
                    continue;
                }
                $complement = $sum - $choice;
                if (in_array($complement, $used)) {
                    continue;
                }
                if (in_array($complement, $cells[1]->getChoices())) {
                    return true;
                }
            }

            return false;
        }

        $pv = $this->gridObj->getPossibleValues($sum, count($cells), $used);

        foreach ($cells as $idx => $cell) {
            $new_choices = array_values(array_intersect($cell->getChoices(), $pv));
            if (empty($new_choices)) {
                return false;
            }
            if (count($new_choices) === 1) {
                $v = current($new_choices);
                $complement = $cells;
                unset($complement[$idx]);
                if (!$this->isPossible($complement, $sum - $v, array_merge($used, [$v]))) {
                    return false;
                }
            }
        }

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
// $this->log('nbrs '.json_encode($nbrs));return true;
// $this->log('choices '.json_encode($choices));return true;
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

        if (count($choices) <= count($new_choices)) {
            return true;
        }

        if (empty($new_choices)) {
            return false;
        }

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
        if (!$this->fails) {
            $cells = [];
            foreach ($this->uiChoices as $idx => $cell) {
                if ($this->cells[$idx]->isDataCell()) {
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

        if (!empty($this->failedStrip)) {
            $grid['error'] = true;
            $grid['message'] = 'problem reducing (' . $this->failReason . ')';
            $grid['failedStripId'] = $this->failedStrip;
            $grid['failReason'] = $this->failReason;
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