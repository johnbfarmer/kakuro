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
        $timesThru = 0,
        $maxTimesThru = 5,
        $restarts = 0,
        $maxRestarts = 5,
        $lastChoice = [],
        $idxsWithNoChoice = [],
        $setOrder = [],
        $forbiddenValues = [],
        $testResult,
        $minimum_strip_size = 2,
        $notReducible = false,
        $finished = false,
        $totalChoiceCount = 0,
        $dataCellCount = 0,
        $solvable = false,
        $island = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->width = !empty($this->parameters['width']) ? $this->parameters['width'] : 12;
        $this->height = !empty($this->parameters['height']) ? $this->parameters['height'] : 12;
        $this->density_constant = !empty($this->parameters['density']) ? $this->parameters['density'] : 0.8;
        $this->symmetry = !empty($this->parameters['symmetry']);
        if (!empty($this->parameters['maxTimesThru'])) {
            $this->maxTimesThru = $this->parameters['maxTimesThru'];
        }
        if (!empty($this->parameters['maxRestarts'])) {
            $this->maxRestarts = $this->parameters['maxRestarts'];
        }
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
        $name = $this->em->getRepository('AppBundle:Grid')->getNextUniqueGridName();
        $this->gridObj = new Grid();
        $this->gridObj->setName($name);
        $this->gridObj->setWidth($this->width);
        $this->gridObj->setHeight($this->height);
        $this->em->persist($this->gridObj);
        $this->buildInitialFrame();
        $this->buildFrame();
        $this->initializeForSettingVals();
        if ($this->addNumbers()) {
            $this->save();
        } else {
            throw new \Exception("No luck");
        }
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
                return $this->changeLastUnforced($idx);
            }
        }
$this->display(3, true);
// exit;
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
            return $this->buildFrame($idx + 1);
        }
    }

    protected function changeLastUnforcedNumber()
    {
$this->log($this->lastChoice, true);
        $idx = $this->getPreviousCellWithChoices();
        if (!$idx) {
            $this->resetNumbers();
            return false;
        }
$this->log("last unforced = $idx", true);
        if (empty($this->forbiddenValues[$idx])) {
            $this->forbiddenValues[$idx] = [];
        }
        $val = $this->cellChoices[$idx];
$this->log("$idx $val is forbidden", true);
        $this->forbiddenValues[$idx][] = $val;
        $idxs = [$idx];
        $s = array_flip($this->setOrder)[$idx];
$this->log("remove from setOrder anything after $s", true);
        for ($i = $s; $i < count($this->setOrder); $i++) {
            unset($this->setOrder[$i]);
        }
$this->log('set order '.json_encode(array_values($this->setOrder)), true);
        for ($i = 1; $i < count($this->cells); $i++) {
            if (!$this->isBlank($i) && !in_array($i, $this->setOrder)) {
                if ($i != $idx) {
                    $this->forbiddenValues[$i] = [];
                    $idxs[] = $i;
                }
                $this->cellChoices[$i] = null;
            }
        }
$this->log('fv '.json_encode($this->forbiddenValues), true);
        $this->idxsWithNoChoice = $idxs;
        return false;
    }

    protected function getPreviousCellWithChoices()
    {
        try {
            $idx = array_pop($this->lastChoice);
        } catch (\Exception $e) {
            $idx = null;
        }
        return $idx;
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
        $this->totalChoiceCount = count($this->number_set) * $this->width * $this->height;
    }

    protected function addNumbers()
    {
$this->log('add nbrs '.json_encode($this->idxsWithNoChoice), true);
        while (!$this->finished) {
            $idxs = $this->idxsWithNoChoice;
            if (empty($idxs)) {
                $idxs = array_keys($this->cells);
                $this->shuffle($idxs);
                foreach ($idxs as $idx) {
                    // add simple strips where possible
                    $this->addStrip($idx);
                }
            }

            foreach ($idxs as $idx) {
                // fill in the blanks
                if (!$this->addNumber($idx)) {
                    continue 2;
                }
            }

            $this->calculateStrips();
            if (!$this->makeEasilyReducible()) {
                continue;
            }
            if ($this->solvable) {
                if ($this->testUnique()) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function addStrip($idx)
    {
        if (!$this->isBlank($idx)) {
            $strips = $this->findMyStrips($idx);
            $strip = count($strips['h']) < count($strips['v']) ? $strips['h'] : $strips['v'];
            foreach ($strip as $cell) {
                if (!empty($this->cellChoices[$cell->getIdx()])) {
                    return; // for now
                }
            }

            $ss = $this->getSimpleStrips(count($strip));
            $this->shuffle($ss);
            foreach ($ss as $s) {
                $this->shuffle($s);
                foreach ($strip as $i => $cell) {
                    $taken = $this->getTaken($cell->getIdx());
                    $available = array_values(array_diff($this->number_set, $taken));
                    $available = $this->filterNumsThatCauseNonUnique($cell, $available);
                    $choice = $s[$i];
                    if (!in_array($choice, $available)) {
                        $this->clearSelection($strip);
                        continue 2;
                    }
                    // temporarily set for filterNumsThatCauseNonUnique
                    $this->selectValue($cell->getIdx(), [$choice]);
                }
                // what still here?
$this->log('add ss '.json_encode($s), true);
                // foreach ($strip as $i => $cell) { // no need
                //     $choice = $s[$i];
                //     $this->selectValue($cell->getIdx(), [$choice]);
                // }

                return;
            }
        }
    }

    protected function addNumber($idx)
    {
        if (!empty($this->cellChoices[$idx]) || $this->isBlank($idx)) {
            return true;
        }
        $cell = $this->cells[$idx];

$this->log('add nbr '.$idx, true);
$this->log($cell->dump(), true);
$this->display(3);

        $taken = $this->getTaken($idx);
        $available = array_values(array_diff($this->number_set, $taken));
        $available = $this->filterNumsThatCauseNonUnique($cell, $available);

        if (empty($available)) {
$this->log("nothing available", true);
            return $this->changeLastUnforcedNumber();
        } else {
            return $this->selectValue($idx, $available);
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
        $available = $this->filterNumsThatCauseSwap($cell, $available);
        // $available = $this->filterNumsThatCauseSwap2($cell, $available);
        $available = $this->filterNumsThatCauseSwap3($cell, $available);
        return $available;
    }

    protected function filterNumsThatCauseSwap($cell, $available)
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

        foreach ($commonValuedCellPairs as $pair) {
// $this->display(3);
            $intersection = $this->interesect($pair, $cell);
            if ($intersection) {
                if($intersection->getIdx() == $idx) {
                    continue;
                }
                $intersectionValue = $intersection->getChoice();
                if (!$intersectionValue) {
                    continue;
                }
$this->log($idx . ' pair '.$pair[0]->getChoice(), true);
                $this->unsetValue($available, $intersection->getChoice());
$this->log('note '.$idx.' cannot have ' .$intersection->getChoice(), true);
                // we also cannot have pair - iV + choice available
                // a  b    b  a
                // b  X    a  Y = b+X-a
                $pairValue = $pair[0]->getChoice();
                $diff = $pairValue - $intersectionValue;
                foreach ($available as $candidateValue) {
                    if (in_array($candidateValue + $diff, $available)) {
                        $this->unsetValue($available, $candidateValue);
$this->log('type 2 '.$idx.' cannot have ' .$candidateValue, true);
                    }
                }
            }
        }

        return $available;
    }

    protected function filterNumsThatCauseSwap2($cell, $available)
    {
        // search multiple interchangeable in parallel strips like
        // 4 6 8 7 1 5 3
        // 5 8 3 9 6 2 1
        // can be
        // 4 8 3 7 6 5 1
        // 5 6 8 9 1 2 3
        // TBI
        return $available;
    }

    protected function filterNumsThatCauseSwap3($cell, $available)
    {
        // each posible val (a) -- temporarily set cell to val a, consider his strips:
        //     find common vals, for each one (b):
        //         (add b's strips if they contain a
        //             each "a" cell in those strips, add their strips if they contain b) recurse
        //         count the a's and b's in the strips. if ==, can't set to a
$this->display(3);
        $idx = $cell->getIdx();
        $strips = $this->findMyStrips($idx, false);
        if (empty($strips)) {
            return $available;
        }

        $commonValuedCellPairs = $this->interesectByValue($strips);
        if (empty($commonValuedCellPairs)) {
            return $available;
        }

        $cells = new ArrayCollection();
        foreach ($available as $choice) {
            $this->selectValue($idx, [$choice]);
            $cells->clear();
            $cells->add($cell);
            foreach ($commonValuedCellPairs as $pair) {
                // foreach ($pair as $p) {
                    $cells = $this->findConnectedStripsMutuallyContaining($cell, $pair, $cells);
                // }

            }

            if ($cells->count() > 2 && !($cells->count() % 2)) {
                $this->unsetValue($available, $choice);
$this->log('type 3 '.$idx.' cannot have ' .$choice, true);
            }

            $this->clearSelection([$cell]);
        }

        return $available;
    }

    protected function findConnectedStripsMutuallyContaining($cell1, $pair, $cells)
    {
        $val1 = $cell1->getChoice();
        foreach ($pair as $cell) {
            if ($cells->contains($cell)) {
                continue;
            }
        // cell2 get strips. see if both contain cell1 val. if yes, add to cells (if not already there) and recurse
            $idx = $cell->getIdx();
            $pairForRecurse = [];
            $strips = $this->findMyStrips($idx, false);
            foreach ($strips as $strip) {
                foreach ($strip as $c) {
                    $found = false;
                    $choice = $c->getChoice();
                    if ($choice == $val1) {
                        $found = true;
                        $pairForRecurse[] = $c;
                        continue 2; // next strip
                    }
                }
                if (!$found) {
                    continue 2; // went thru all cells, value not there. next cell in the pair please
                }
            }

            $cells->add($cell);
            $cells = $this->findConnectedStripsMutuallyContaining($cell, $pairForRecurse, $cells);
        }

        return $cells;
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

    protected function clearSelection($cells)
    {
        foreach ($cells as $cell) {
            $idx = $cell->getIdx();
            $this->cellChoices[$idx] = null;
            $this->cells[$idx]->setChoice(null);
            $this->unsetValue($this->setOrder, $idx);
        }
    }

    protected function selectValue($idx, $choices)
    {
$this->log('sv '.$idx.' '.json_encode($choices), true);
        if (count($choices) > 1) {
            $this->lastChoice[] = $idx;
            sort($choices);
            $index = $this->getBestChoice($idx, $choices);
        }

        if (count($choices) == 1) {
            $this->unsetValue($this->lastChoice, $idx);
            $index = 0;
        }

        $choices = array_values($choices); // JIC assoc array
        $val = $choices[$index];
        $this->cellChoices[$idx] = $val;
        $this->cells[$idx]->setChoice($val);
        $this->setOrder[] = $idx;
$this->log("set $idx to $val", true);
        return true;
    }

    protected function getBestChoice($idx, $choices)
    {
        $strips = $this->findMyStrips($idx);
        $selectionType = 'random'; // high, low
        foreach ($strips as $strip) {
            $decidedSum = 0;
            $decided = [];
            $num = count($strip);
            foreach ($strip as $cell) {
                if (!empty($this->cellChoices[$cell->getIdx()])) {
                    $choice = $this->cellChoices[$cell->getIdx()];
                    $decidedSum += $choice;
                    $decided[] = $choice;
                }
            }
            if (!empty($decided)) {
                // see if we can complete a simple strip
                $valsToCompleteSimpleStrip = $this->completeSimpleStrip(count($strip), $decided);
                if (!empty($valsToCompleteSimpleStrip)) {
                    $choicesToCompleteSimpleStrip = array_values(array_intersect($choices, $valsToCompleteSimpleStrip));
                    if (!empty($choicesToCompleteSimpleStrip)) {
                        $i = rand(1, count($choicesToCompleteSimpleStrip)) - 1;
                        $val = $choicesToCompleteSimpleStrip[$i];
$this->log('to complete ss try '.$val, true);
                        return array_flip($choices)[$val];
                    }
                }

                // no? then see if high or low might be better (this don't help much)
                $decidedAvg = $decidedSum / count($decided);
                if ($decidedAvg >= 5) {
                    if ($selectionType !== 'low') {
                        $selectionType = 'high';
                    } else {
                        $selectionType = 'random';
                    }
                } else {
                    if ($selectionType !== 'high') {
                        $selectionType = 'low';
                    } else {
                        $selectionType = 'random';
                    }
                }
            }
        }
$this->log($idx.' '.json_encode($choices). ' '.$selectionType, true);
        switch ($selectionType) {
            case 'random':
                return rand(1, count($choices)) - 1;
            case 'high':
                return count($choices) - 1;
            case 'low':
                return 0;
        }
    }

    protected function completeSimpleStrip($len, $decided)
    {
        $ss = $this->getSimpleStrips($len);
        $this->shuffle($ss);
        foreach ($ss as $strip) {
            $diff = array_diff($decided, $strip);
            if (empty($diff)) {
                return array_values(array_diff($strip, $decided));
            }
        }

        return [];
    }

    protected function makeEasilyReducibleByStrips($idx)
    {
        // find strips this size that have few choices
        $strips = $this->findMyStrips($idx);
        // favor shorter
        $strip = count($strips['h']) < count($strips['v']) ? $strips['h'] : $strips['v'];
        $stripLen = count($strip);
        $simpleStrips = $this->getSimpleStrips($stripLen);
        foreach ($simpleStrips as $choices) {
            // can we get the choices into these cells?
            $ch = [];
            foreach ($strip as $cell) {
                $i = $cell->getIdx();
                $this->cellChoices[$i] = 0;
                $ch[$i] = [];
            }
            foreach ($strip as $cell) {
                $i = $cell->getIdx();
                $taken = $this->getTaken($i);
                // 1st see which choices work for which cells
                foreach ($choices as $choice) {
                    if (!in_array($choice, $taken)) {
                        $available = array_values(array_diff($this->number_set, $taken));
                        $available = $this->filterNumsThatCauseNonUnique($cell, $available);
                        if (in_array($choice, $available)) {
                            $ch[$i][] = $choice;
                        }
                    }
                }
            }

            // now go thru the choices. if any are empty, next simpleStrip. sort by count.
            // all choices must be accounted for 
            $sorter = [1 => []];
            $counter = [];
            foreach ($ch as $i => $c) {
                if (empty($c)) {
                    continue 2;
                }
                $ct = count($c);
                if (empty($sorter[$ct])) {
                    $sorter[$ct] = [];
                }
                $sorter[$ct][] = $i;
                foreach ($c as $choice) {
                    if (!isset($counter[$choice])) {
                        $counter[$choice] = [];
                    }
                    $counter[$choice][] = $i;
                }
            }
            if (count($counter) < $stripLen) {
                continue; // one or more choices have no home
            }
            foreach ($counter as $choice => $idxs) {
                if (count($idxs) === 1) {
                    $i = current($idxs);
                    if (!in_array($i, $sorter[1])) {
                        $sorter[1][] = $i;
                    }
                }
            }
            $used = [];
            foreach ($sorter as $idxs) {
                foreach ($idxs as $i) {
                    if (!empty($this->cellChoices[$i])) {
                        continue;
                    }
                    $c = array_values(array_diff($ch[$i], $used));
                    if (empty($c)) {
                        continue 3; // no choices
                    }
                    foreach ($c as $choice) {
                        if (!in_array($choice, $used)) {
                            $used[] = $choice;
                            $this->selectValue($i, [$choice]); // REFINE. may need to consider all permutations
                            break;
                        }
                    }
                }
            }

            return; // if you got here, you did it
        }
    }

    protected function makeEasilyReducible()
    {
        // need a more powerful function that KakuroReducer::reduce since larger grids do not reduce all the way with currect techniques. perhaps a 3rd arg that probes (guesses)
// $this->solvable = true;return;
if ($this->timesThru++ > $this->maxTimesThru) {
    $this->resetNumbers();
    return false;
}
$this->log('test easily reduc', true);
$this->display(3);
        $parameters = [
            'grid' => $this->gridObj,
            'simpleReduction' => false,
        ];
        KakuroReducer::autoExecute($parameters);

        $mostChoices = 1;
        foreach($this->cells as $cell) {
            $idx = $cell->getIdx();
            if ($this->isBlank($idx)) {
                continue;
            }
            $choices = $cell->getChoices();
            $choiceCount = count($choices);
$this->log($idx.' has ' . $choiceCount . ' choices', true);
            if ($choiceCount > $mostChoices) {
                $mostChoices = $choiceCount;
                $idxToChange = $idx;
            }
        }

        if ($mostChoices > 1) {
            $idx = $idxToChange;
            $strips = $this->findMyStrips($idx);
            $highestChoiceRatio = 0;
            $idxs = [$idx];
            foreach($strips as $strip) {
                if (!$this->isSimpleStrip($strip)) {
                    foreach ($strip as $c) {
                        $idxs[] = $c->getIdx();
                    }
                }
            }
            // wipe out choice history
            $this->lastChoice = [];
            $this->forbiddenValues = [$idx => [$this->cellChoices[$idx]]];

            foreach ($idxs as $idx) {
                $this->cellChoices[$idx] = null;
                $this->unsetValue($this->setOrder, $idx);
            }
            $this->idxsWithNoChoice = $idxs;
            return false;
        } else {
$this->log('yes easily reduc', true);
            $this->solvable = true;
            return true;
        }
    }

    protected function isSimpleStrip($strip)
    {
        return false; // TBI
    }

    protected function simplify($idx, $choices)
    {
        // try all vals (less current) pick the one with fewest total choices
$this->log("$idx -- simplification " . json_encode($choices), true);
        $cell = $this->cells[$idx];
        $choices = $this->filterNumsThatCauseNonUnique($cell, $choices);
        if (empty($choices)) {
            // throw new \Exception('no choices');
            // choose other cell to reduce
            $this->forbiddenValues[$idx] = [];
            return;
            // $this->makeEasilyReducible();
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
$this->log($lowestCount .' <? ' . $this->totalChoiceCount, true);
        if ($lowestCount < $this->totalChoiceCount) {
            $this->totalChoiceCount = $lowestCount;
$this->log('choice ratio ', true);
$this->log(round($lowestCount / $this->dataCellCount, 2), true);
            $this->selectValue($idx, [$newVal]);
            $this->calculateStrips();
            // $this->makeEasilyReducible();
        } else {
            // choose other cell to reduce
            $this->forbiddenValues[$idx] = [];
            $this->makeEasilyReducibleByStrips($idx);
        }
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

        // if ($this->notReducible) {
            $this->resetNumbers();
        // }

        return false;
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
                    return true;
                }
            }
        }
        
        $this->setUnknown($idx);
        $col = $idx % $this->width;
        $row = floor($idx / $this->width);

        if ($col > 2) {
            // does my left nbr have a blank left nbr?
            if (!$this->isBlank($idx - $this->width) && $this->isBlank($idx - 2 * $this->width)) {
                return true;
            }
        }

        if ($col == 2) {
            // is my left nbr non-blank?
            if (!$this->isBlank($idx - $this->width)) {
                return true;
            }
        }

        if ($row > 2) {
            // does my top nbr have a blank top nbr?
            if (!$this->isBlank($idx - 1) && $this->isBlank($idx - 2)) {
                return true;
            }
        }

        if ($row == 2) {
            // is my top nbr non-blank?
            if (!$this->isBlank($idx - 1)) {
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

        // list($creates_island, $path) = $this->walkToEdges($idx, ['blank']);
        // $island = $creates_island ? $this->getIsland($path) : [];
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

    // if we set idx blank, get the path of blanks created
    protected function walkToEdges($idx, $val = 0)
    {
        $path = $this->buildWeb($idx, ['blank']);
        $edges = [];
        foreach ($path as $idx) {
            $i = $idx % $this->width;
            $j = floor($idx / $this->width);
            if ($i == 1 || $i == $this->width - 1 || $j == 1 || $j == $this->height - 1) {
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

    protected function calculateStripTotal($strip)
    {
        $sum = 0;
        foreach ($strip as $cell) {
            $sum += $this->cellChoices[$cell->getIdx()];
        }

        return $sum;
    }

    protected function getSimpleStrips($len)
    {
        switch ($len) {
            case 2:
                return [[1,2],[1,3],[8,9],[7,9]];
            case 3:
                return [[1,2,3],[1,2,4],[7,8,9],[6,8,9]];
            case 4:
                return [[1,2,3,4],[1,2,3,5],[5,7,8,9],[6,7,8,9]];
            case 5:
                return [[1,2,3,4,5],[1,2,3,4,6],[5,6,7,8,9],[4,6,7,8,9]];
            case 6:
                return [[1,2,3,4,5,6],[1,2,3,4,5,7],[4,5,6,7,8,9],[3,5,6,7,8,9]];
            case 7:
                return [[1,2,3,4,5,6,7],[1,2,3,4,5,6,8],[3,4,5,6,7,8,9],[2,4,5,6,7,8,9]];
            case 8:
                return [[1,2,3,4,5,6,7,8],[1,2,3,4,5,6,7,9],[1,2,3,4,5,6,8,9],[1,2,3,4,5,9,7,8],
                    [1,2,3,4,9,6,7,8],[1,2,3,9,5,6,7,8],[1,2,9,4,5,6,7,8],[1,9,3,4,5,6,7,8],[9,2,3,4,5,6,7,8]];
            case 9:
                return [1,2,3,4,5,6,7,8,9];
        }

        return [];
    }

    protected function resetNumbers() {
        $this->log('restartin', true);
        $this->idxsWithNoChoice = [];
        $this->lastChoice = [];
        $this->forbiddenValues = [];
        $this->cellChoices = [];
        $this->setOrder = [];
        $this->timesThru = 0;
        foreach ($this->cells as $cell) {
            $cell->setChoice(null);
        }
        if ($this->restarts++ > 5) {
            $this->log('quittin', true);
            $this->finished = true;
        }
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