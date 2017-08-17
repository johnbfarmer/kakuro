<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;

class KakuroReducer extends BaseKakuro
{
    protected 
        $changedStrips,
        $problemStrips = [],
        $simpleReduction,
        $gridObj,
        $cells,
        $uiChoices,
        $stripsNew,
        $indexesNotToProcess = [],
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

        $this->width = $this->gridObj->getWidth();
        $this->simpleReduction = !empty($this->parameters['simpleReduction']);
        $this->changedStrips = new ArrayCollection();
    }

    protected function execute()
    {
        parent::execute();
        $this->cells = $this->gridObj->getCells();
        $this->stripsNew = $this->gridObj->getStrips();
        if (!empty($this->cells)) {
            foreach ($this->cells as $cell) {
                if ($cell->isDataCell()) {
                    $pv = $cell->getPossibleValues();
                    $idx = $cell->getRow() * $this->width + $cell->getCol();
                    $choices = $this->uiChoices[$idx]['choices'];
                    $choices = !empty($choices) ? array_values(array_intersect($choices, $pv)) : $pv;
                    $this->cells[$idx]->setChoices($choices);
                }
            }
        }

        if (!$this->reduce($this->stripsNew, !$this->simpleReduction)) {
            $this->fails = true;
        }
    }

    protected function reduce($strips, $use_advanced_reduction)
    {
        while (!$strips->isEmpty()) {
            $this->changedStrips->clear();
            foreach ($strips as $strip) {
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
                            return false;
                        }
                        if ($changed || count($newChoices) < count($choices)) {
                            $cell->setChoices($newChoices);
                            $this->addCellsStripsToChanged($cell);
                            $this->cells[$cell->getIdx()] = $cell; // not needed?
                        }
                    }
                }

                if ($use_advanced_reduction) {
                    if (!$this->adv($strip, $cells)) {
                        return false;
                    }
                }
            }

            $strips = clone $this->changedStrips;
        }

        // return $this->isSolved();
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
$this->log($cell->speak());
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

        return [
            'values' => $this->gridObj->getPossibleValues($sum, $len, $usedNumbers),
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
                if (!$this->isPossibleNew($complement, $sum - $v, array_merge($used, [$v]))) {
                    unset($choices[$idx]);
                    if (empty($choices)) {
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
                return false;
            }

            if (!$this->removeChoicesFromCells($n, $c)) {
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
            $cell = current($group);
            foreach ($cell->getChoices() as $choice) {
                $groups_total += $choice;
                $used[] = $choice;
            }
            foreach ($group as $cell) {
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
            $newChoices = array_values(array_intersect($cell->getChoices(), $pv));
            $idx = $this->getIndexByRowAndCol($cell);
            $this->cells[$idx]->setChoices($newChoices);
        }

        return $this->advancedStripReduction($cells, $sum, $used);
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

    protected function isPossibleNew($cells, $sum, $used)
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
                if (!$this->isPossibleNew($complement, $sum - $v, array_merge($used, [$v]))) {
                    return false;
                }
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

    public function getApiResponse()
    {
        if (!$this->fails) {
            $cells = [];
            foreach ($this->uiChoices as $idx => $cell) {
                if ($this->cells[$idx]->isDataCell()) {
                    $cell['choices'] = array_values($this->cells[$idx]->getChoices());
                    $cell['display'] = implode('', $cell['choices']);
                }
                $cells[] = $cell;
            }
        } else {
            $cells = $this->uiChoices;
        }

        $grid = ['cells' => $cells, 'error' => false];
        if (!empty($this->problemStrips)) {
            $grid['error'] = true;
            $grid['message'] = 'problem reducing';
        }
        return $grid;
    }
}