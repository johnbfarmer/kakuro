<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class GridReducer extends BaseGrid
{
    protected 
        $changed = false,
        $changed_strips = [],
        $problem_strips = [],
        $solutions_desired = 2,
        $saved_grids = [],
        $simple_reduction,
        $reduce_only,
        $gridObj,
        $cellsNew,
        $stripsNew;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['grid_name'])) {
            $this->grid_name = $this->parameters['grid_name'];
        }
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }
        if (!empty($this->parameters['grid'])) {
            $this->gridObj = $this->parameters['grid'];
        }

        $this->width = $this->gridObj->getWidth();
        $this->simple_reduction = !empty($this->parameters['simple_reduction']);
        $this->reduce_only = !empty($this->parameters['reduce_only']);
    }

    protected function execute()
    {
        parent::execute();
        $originalState = $this->gridObj->getForProcessing();
        $this->cellsNew = $originalState;
        $this->stripsNew = $this->gridObj->getStrips();
        if (!empty($this->cells)) {
            foreach ($this->cellsNew as $cell) {
                if ($cell->isDataCell()) {
                    $pv = $cell->getPossibleValues();
                    $idx = $cell->getRow() * $this->width + $cell->getCol();
                    $choices = $this->cells[$idx]['choices'];
                    $choices = !empty($choices) ? array_values(array_intersect($choices, $pv)) : $pv;
                    $this->cellsNew[$idx]->setChoices($choices);
                }
            }
        }

        $this->done = false;
        $this->grid['path'] = [];
        $this->grid['changed_strips'] = [];
        $this->log("DOING INITIAL REDUCTION", $this->debug);
        if (!$this->reduceGridNew([], !$this->simple_reduction)) {
            $this->cellsNew = $originalState;
        }
    }

    protected function reduceGridNew($strip_ids_to_process = [], $use_advanced_reduction)
    {
        if (empty($strip_ids_to_process)) {
            $strips = $this->stripsNew;
        } else {
            $strips = [];
            foreach($strip_ids_to_process as $idx) {
                $strips[$idx] = $this->stripsNew[$idx];
            }
        }

        while (!empty($strips) && !$this->done) {
            $this->changed_strips = [];
            foreach ($strips as $idx => $strip) {
                if (!$this->reduceStripNew($strip, $use_advanced_reduction)) {
                    $this->log("problem reducing strip $idx");
                    $this->problem_strips[$idx] = $strip;
                    return false;
                }
            }

            if (empty($this->changed_strips)) {
                break;
            }

            $strips = [];
            foreach($this->changed_strips as $idx) {
                $strips[$idx] = $this->stripsNew[$idx];
            }
        }

        return $this->cellsNew;
    }

    protected function reduceStripNew($strip, $use_advanced_reduction)
    {
        $sum = $strip->getTotal();
        $len = $strip->getLen();
        $dir = $strip->getDir();
        $used_numbers = [];
        if ($dir === 'h') {
            $start = $strip->getStartCol();
            $row = $strip->getStartRow();
        } else {
            $start = $strip->getStartRow();
            $col = $strip->getStartCol();
        }

        $undecided_cells = [];
        $decided_cells = [];
        for ($k = $start; $k < $start + $len; $k++) {
            $i = $dir === 'v' ? $k : $row; 
            $j = $dir === 'h' ? $k : $col;
            $idx = $i * $this->width + $j;
            $cell = $this->cellsNew[$idx];
            if (count($cell->getChoices()) === 1) {
                $num = current($cell->getChoices());
                $sum -= $num;
                $used_numbers[] = $num;
                $decided_cells[] = $cell;
            } else {
                $undecided_cells[] = $cell;
            }
        }

        if (empty($undecided_cells)) { // nothing to do
            return true;
        }

        $size = $len - count($used_numbers);
        $choices = $this->getValues($sum, $size, $used_numbers);
        $still_undecided_cells = [];

        foreach ($undecided_cells as $idx => $cell) {
            $pv = $cell->getChoices();
            $new_pv = array_values(array_intersect($pv, $choices));
            if (count($undecided_cells) === 2) {
                $other_guy = $undecided_cells;
                unset($other_guy[$idx]);
                $other = current($other_guy); // ok ok better way needed
                $sum = $strip->getTotal();
                foreach ($decided_cells as $dc) {
                    $sum -= current($dc->getChoices());
                }
                $new_pv = $this->reduceDupletNew($sum, $cell, $other);
            }
            if (empty($new_pv)) {
                return false;
            }
            if (count($new_pv) < count($pv)) {
                $cell->setChoices($new_pv);
                $arr_idx = $cell->getRow() * $this->width + $cell->getCol();
                $this->cellsNew[$arr_idx] = $cell;
                $this->addCellsStripsToChanged($cell);
            }
            if (count($new_pv) === 1) {
                $num = current($new_pv);
                $sum -= $num;
                $used_numbers[] = $num;
                $size--;
                $choices = $this->getValues($sum, $size, $used_numbers);
            } else {
                $still_undecided_cells[] = $cell;
            }
        }

        return $use_advanced_reduction ? $this->advancedStripReductionNew($still_undecided_cells, $sum, $used_numbers) : true;
    }

    protected function advancedStripReductionNew($cells, $sum, $used)
    {
        if (!$this->reduceByComplementNew($cells, $sum, $used)) {
            return false;
        }

        return $this->reduceByPigeonHole($cells, $sum, $used);
    }

    protected function reduceByComplementNew($cells, $sum, $used)
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
                    $this->cellsNew[$arr_idx] = $cell;
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

        return $this->reduceByDisjointGroups($cells, $sum, $disjointGroups);
    }

    protected function reduceByDisjointGroups($cells, $sum, $disjointGroups)
    {
        if (empty($disjointGroups)) {
            return true;
        }

        // get the sum of the groups
        $groups_total = 0;
        foreach ($disjointGroups as $group) {
            $cell = current($group);
            foreach ($cell->getChoices() as $choice) {
                $groups_total += $choice;
            }
            foreach ($group as $cell) {
                $this->removeByRowAndCol($cells, $cell);
            }
        }

        $sum -= $groups_total;

        // $used can be empty as those vals should already have been removed from $cells
        $pv = $this->getValues($sum, count($cells), []);
        foreach ($cells as $cell) {
            $newChoices = array_values(array_intersect($cell->getChoices(), $pv));
            $idx = $this->getIndexByRowAndCol($cell);
            $this->cellsNew[$idx]->setChoices($newChoices);
        }

        return true;
    }

    protected function reduceDupletNew($sum, $cell, $other)
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
        $ids = [];
        $ids[] = $cell->getStripH();
        $ids[] = $cell->getStripV();
        foreach ($ids as $id) {
            if (!in_array($id, $this->changed_strips)) {
                $this->changed_strips[] = $id;
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

        $pv = $this->getValues($sum, count($cells), $used);

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
        $this->cellsNew[$arr_idx] = $cell;
        $this->addCellsStripsToChanged($cell);
        return true;
    }

    public function getApiResponse()
    {
        $cells = [];
        foreach ($this->cells as $idx => $cell) {
            if ($this->cellsNew[$idx]->isDataCell()) {
                $cell['choices'] = array_values($this->cellsNew[$idx]->getChoices());
                $cell['display'] = implode('', $cell['choices']);
            }
            $cells[] = $cell;
        }

        $grid = ['cells' => $cells, 'error' => false];
        if (!empty($this->problem_strips)) {
            $grid['error'] = true;
            $grid['message'] = 'problem reducing';
        }
        return $grid;
    }
}