<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;
use AppBundle\Entity\Strip;
use AppBundle\Entity\Solution;

class BuildKakuroSolution extends BaseKakuro
{
    protected 
        $width,
        $height,
        $cells = [],
        $cellTypes = [],
        $cellChoices = [],
        $density_constant,
        $density_randomness = 0.3,
        $symmetry = false,
        $grid,
        $sums = [],
        $rank = 0,
        $idx = 0,
        $idxToChange = 0,
        $timesThru = 0,
        $maxTimesThru = 5,
        $restarts = 0,
        $maxRestarts = 5,
        $lastChoice = [],
        $frameId,
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
        if (!empty($this->parameters['grid'])) {
            $this->gridObj = $this->parameters['grid'];
        }
        $this->width = $this->gridObj->getWidth();
        $this->height = $this->gridObj->getHeight();
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }
        if (!empty($this->parameters['max-times-thru'])) {
            $this->maxTimesThru = $this->parameters['max-times-thru'];
        }
        if (!empty($this->parameters['max-restarts'])) {
            $this->maxRestarts = $this->parameters['max-restarts'];
        }
        $this->grid = [
            'cells' => []
        ];
    }

    public function execute()
    {
        $this->addNumbers();
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
            if (!$this->isNonDataCell($i) && !in_array($i, $this->setOrder)) {
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

    protected function addNumbers()
    {
$this->log('add nbrs ', true);
        while (!$this->finished) {
            $idxs = $this->idxsWithNoChoice;
$this->log('top of add nbrs loop'.json_encode($this->idxsWithNoChoice), true);
            if (empty($idxs)) {
                $idxs = array_keys($this->cells);
                $this->shuffle($idxs);
                foreach ($idxs as $idx) {
                    // add simple strips where possible
                    $this->addStrip($idx);
                }
            }

            try {
                $idxs = $this->sortUnsetCellsByAvailable($idxs);
            } catch (\Exception $e) {
                $this->removeStrips($this->idxToChange);
            }

            foreach ($idxs as $idx) {
                // fill in the blanks
                if (!$this->addNumber($idx)) {
                    continue 2;
                }
            }

            // make sure every choice is filled in (should not be necessary... but we are hitting it)
            $this->idxsWithNoChoice = [];
            foreach ($this->cells as $idx => $cell) {
                if (!$this->isNonDataCell($idx)) {
                    $choice = $cell->getChoice();
                    if (empty($choice)) {
                        $this->idxsWithNoChoice[] = $idx;
                    }
                }
            }

            if (!empty($this->idxsWithNoChoice)) {
                continue;
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
        if (!$this->isNonDataCell($idx)) {
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
        if (!empty($this->cellChoices[$idx]) || $this->isNonDataCell($idx)) {
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
$this->log("nothing available at $idx ".$cell->dump(), true);
            // return $this->changeLastUnforcedNumber();
            $this->removeStrips($idx);
            return false;
        } else {
            return $this->selectValue($idx, $available);
        }
    }

    protected function getTaken($idx)
    {
        $strips = $this->findMyStrips($idx);
        $available = $this->number_set;
        $taken = !empty($this->forbiddenValues[$idx]) ? $this->forbiddenValues[$idx] : [];

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
        try {
            $available = $this->filterNumsThatCauseSwap2($cell, $available);
        } catch (\Exception $e) {
            throw $e;
        }
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

        // for now, let's do parallel contiguous only:
        $idx = $cell->getIdx();
        $strips = $this->findMyStrips($idx, false);
        if (empty($strips)) {
            return $available;
        }

        $neighbors = $this->getNeighboringCoordinates($idx);
        foreach ($neighbors as $pos => $nbr) {
            $strips[$pos] = $nbr ? $this->findMyStrips($nbr) : ['h' => [], 'v' => []];
        }

        foreach ($available as $candidateValue) {
            $this->selectValue($idx, [$candidateValue]);
            $s = $this->findMyStrips($idx, false);
            $strips['h'] = $s['h'];
            $strips['v'] = $s['v'];
            $x = [];
            $x['top'] = $this->interesectByValue([$strips['h'], $strips['top']['h']]);
            $x['bottom'] = $this->interesectByValue([$strips['h'], $strips['bottom']['h']]);
            $x['left'] = $this->interesectByValue([$strips['v'], $strips['left']['v']]);
            $x['right'] = $this->interesectByValue([$strips['v'], $strips['right']['v']]);

            foreach ($x as $pos => $y) {
                if (count($y) > 2) { // case count 2 is already handled
                    $z = 'dbg';
                    $a1 = [];
                    $a2 = [];
                    foreach ($y as $pair) {
                        if (in_array($pos, ['top', 'bottom'])) {
                            $a1[] = $pair[0]->getCol();
                            $a2[] = $pair[1]->getCol();
                        } else {
                            $a1[] = $pair[0]->getRow();
                            $a2[] = $pair[1]->getRow();
                        }
                    }

                    if (empty(array_diff($a1, $a2))) {
                        $this->log("filter2 not possible $idx $candidateValue" , true);
                        $this->unsetValue($available, $candidateValue);
                    }
                }
            }
        }

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

    protected function interesectByValue($strips) // takes 2 strips
    {
        $cells = [];
        $strips = array_values($strips);
        if (empty($strips[0]) || empty($strips[1])) {
            return [];
        }

        foreach ($strips[0] as $cell) {
            $choice = $cell->getChoice();
            if (!$choice) {
                continue;
            }
            foreach ($strips[1] as $vCell) {
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

    protected function available($idx)
    {
        if (!empty($this->cellChoices[$idx]) || $this->isNonDataCell($idx)) {
            return [];
        }
        $taken = $this->getTaken($idx);
        $available = array_values(array_diff($this->number_set, $taken));
        $available = $this->filterNumsThatCauseNonUnique($this->cells[$idx], $available);
// if empty here we have to act! otherwise confused with blanks & takens
        if (empty($available)) {
            $this->idxToChange = $idx;
            throw new \Exception("nothing available for $idx");
        }
        return $available;
    }

    protected function sortUnsetCellsByAvailable($idxs)
    {
        $availableCount = [];
        foreach ($idxs as $idx) {
            $available = $this->available($idx);
            if (empty($available)) { // taken or blank or no sé qué
                continue;
            }

            $availableCount[$idx] = count($available);
        }

        asort($availableCount);

        return array_keys($availableCount);
    }

    protected function makeEasilyReducible()
    {
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
            if ($this->isNonDataCell($idx)) {
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
            $this->removeStrips($idxToChange);
            return false;
        } else {
$this->log('yes easily reduc', true);
            $this->solvable = true;
            return true;
        }
    }

    protected function removeStrips($idx)
    {
        $strips = $this->findMyStrips($idx);
        $idxs = [$idx];
        foreach($strips as $strip) {
            if (!$this->isSimpleStrip($strip)) { // TBI, but what if both strips are simple? then you remove nothing...
                foreach ($strip as $cell) {
                    if (!in_array($cell->getIdx(), $idxs)) {
                        $idxs[] = $cell->getIdx();
                    }
                }
            }
        }

        // wipe out choice history
        $this->lastChoice = [];
        $this->forbiddenValues = [$idx => [$this->cellChoices[$idx]]];

        foreach ($idxs as $idx) {
            $this->cellChoices[$idx] = null;
            $this->cells[$idx]->setChoice(null);
            $this->unsetValue($this->setOrder, $idx);
        }
        $this->idxsWithNoChoice = $idxs;
    }

    protected function isSimpleStrip($strip)
    {
        return false; // TBI
    }

    protected function testUnique()
    {
        return true; // this is no longer needed here. if we get this far, adv reduction can solve the puzzle and 
        // uniqueness is assured
        if (empty($this->cellChoices)) {
            $this->log('empty choices', true);
            return false;
        }
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

    protected function calculateStrips()
    {
        $stripIdx = 0;
        foreach ($this->cells as $idx => $cell) {
            if ($this->isNonDataCell($idx)) {
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
            if ($this->isNonDataCell($idx, true)) {
                $c = '.';
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

    protected function findMyStrips($idx, $include_unknown = true)
    {
        if ($this->isNonDataCell($idx)) {
            return ['h' => [], 'v'=>[]]; // aint got no strips
        }

        $h = $this->findMyHStrip($idx, $include_unknown);
        $v = $this->findMyVStrip($idx, $include_unknown);

        return ['h' => $h, 'v'=>$v];
    }

    protected function findMyHStrip($idx, $include_unknown = true)
    {
        while (!$this->isNonDataCell(--$idx, !$include_unknown)) {
            //
        }

        return $this->stripToTheRight($idx, $include_unknown);
    }

    protected function findMyVStrip($idx, $include_unknown = true)
    {
        $idx -= $this->width;
        while (!$this->isNonDataCell($idx, !$include_unknown)) {
            $idx -= $this->width;
        }

        return $this->stripBelow($idx, $include_unknown);
    }

    protected function stripToTheRight($idx, $include_unknown = false, $arr = [])
    {
        $cell = $this->cells[$idx];
        if (!$this->isNonDataCell($idx, $include_unknown)) {
            $arr[] = $cell;
        }

        if ($cell->getCol() < $this->width - 1) {
            return $this->isNonDataCell($idx + 1, $include_unknown) ? $arr : $this->stripToTheRight($idx + 1, $include_unknown, $arr);
        }

        return $arr;
    }

    protected function stripBelow($idx, $include_unknown = false, $arr = [])
    {
        $cell = $this->cells[$idx];
        if (!$this->isNonDataCell($idx, $include_unknown)) {
            $arr[] = $cell;
        }

        if ($cell->getRow() < $this->height - 1) {
            return $this->isNonDataCell($idx + $this->width, $include_unknown) ? $arr : $this->stripBelow($idx + $this->width, $include_unknown, $arr);
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
                return [[1,2,3,4,5,6,7,8,9]];
        }

        return [[1],[2],[3],[4],[5],[6],[7],[8],[9]];
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
        if ($this->restarts++ > $this->maxRestarts) {
            $this->log('quittin', true);
            $this->finished = true;
        }
    }

    public function isSolvable()
    {
        return $this->solvable;
    }

    protected function save()
    {
        // only anchors get written to the db
$this->log('unique solution found', true);
$this->display(3);
        $cells = [];
        foreach ($this->gridObj->getCells() as $idx => $cell) {
            if ($this->isNonDataCell($idx)) {
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