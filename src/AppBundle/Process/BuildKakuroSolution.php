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
        $idxsToProcess = [],
        $idxsInitialStrips = [],
        $neighborStrips = [],
        $neighborSubstrips = [],
        $substrips = [],
        $setOrder = [],
        $forbiddenValues = [],
        $readyForTesting = false,
        $solutionCounts = [],
        $solutionCandidateHashes = [], // keep track to avoid repeating solution candidates
        $solutionScore = 2147483647,
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
        // $this->grid = [
        //     'cells' => []
        // ];
    }

    public function execute()
    {
        if (!empty($this->parameters['fromFile'])) {
            $this->handlePresets();
        }
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
        // $this->idxsWithNoChoice = $idxs;
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
$ctr = 0;
        while (!$this->finished) {
            if ($ctr++ > 5 && $this->timesThru++ > $this->maxTimesThru) {
                $this->resetNumbers();
                $ctr = 0;
            }
            $idxs = $this->getIdxsToProcess();
$this->log('top of add nbrs loop'.json_encode($idxs), true);

            // if nothing is set, add simple strips
            if (empty($this->cellChoices)) {
                $idxs = array_keys($this->cells);
                $this->shuffle($idxs);
                foreach ($idxs as $idx) {
                    $this->addStrip($idx);
                }
$this->log('done adding strips', true);
                $this->idxsToProcess = [];
                $idxs = $this->getIdxsToProcess();
            }
$this->display(3, 'here we are');
            $this->readyForTesting = false;
            foreach ($idxs as $idx) {
                // fill in the blanks
                if (!$this->addNumber($idx)) {
                    continue 2;
                }

                if ($this->readyForTesting) {
                    break;
                }
$this->display(3);
            }

            // make sure every choice is filled in (should not be necessary... but we are hitting it)
            $this->idxsToProcess = [];
            $idxsWithNoChoice = $this->getIdxsToProcess();
            if (!empty($idxsWithNoChoice)) {
                continue;
            }

            $this->calculateStrips();

            if (!$this->makeEasilyReducible()) {
                continue;
            }

            if ($this->solvable) {
                $this->classify();
                return true;
            }
        }

        return false;
    }

    protected function getIdxsToProcess()
    {
        if (!empty($this->idxsToProcess)) {
            return $this->idxsToProcess;
        }

        $idxsWithNoChoice = [];
        foreach ($this->cells as $idx => $cell) {
            if (!$this->isNonDataCell($idx)) {
                $choice = $cell->getChoice();
                if (empty($choice)) {
                    $idxsWithNoChoice[] = $idx;
                }
            }
        }

        return $idxsWithNoChoice;
    }

    protected function addStrip($idx, $stepTwo = false)
    {
        if (!$this->isNonDataCell($idx)) {
            $strips = $this->findMyStrips($idx);
            $stripStatus = [];
            foreach ($strips as $i => $s) {
                $status = ['allEmpty' => true, 'allTaken' => true];
                foreach ($s as $cell) {
                    if (!empty($this->cellChoices[$cell->getIdx()])) {
                        $status['allEmpty'] = false;
                    } else {
                        $status['allTaken'] = false;
                    }
                }

                $stripStatus[$i] = $status;
            }

            // get the strip to process
            // both empty, shorter; one empty, use it; both nonempty + !steptwo go away; else use shorter*; never allTaken
            if ($stripStatus['h']['allEmpty'] === $stripStatus['v']['allEmpty']) {
                $stripIdx = count($strips['h']) < count($strips['v']) ? (
                        !$stripStatus['h']['allTaken'] ? 'h' : (
                            !$stripStatus['v']['allTaken'] ? 'v' : null)
                    ) : null;

                // return if all taken or both nonempy and not step 2:
                if (empty($stripIdx) || !$stripStatus['h']['allEmpty'] && !$stepTwo) {
                    return false;
                }
            } else {
                if ($stripStatus['h']['allEmpty']) {
                    $stripIdx = 'h';
                } else {
                    $stripIdx = 'v';
                }
            }

            $strip = $strips[$stripIdx];

            if ($this->fillStrip($strip)) {
// $this->log('strip added '. $idx, true);
// $this->display(3, 'strip (1)');
                return true;
            }

            $otherStripIdx = $stripIdx === 'h' ? 'v' : 'h';
            if (!$stripStatus[$otherStripIdx]['allTaken'] && ($stepTwo || $stripStatus[$otherStripIdx]['allEmpty'])) {
                if ($this->fillStrip($strip)) {
// $this->log('strip added '. $idx, true);
// $this->display(3, 'strip (2)');
                    return true;
                }
            }
        }
// $this->log('no strip added '. $idx, true);
// $this->display(3, 'no strip');

        return false;
    }

    protected function fillStrip($strip)
    {
        $ct = count($strip);
        if ($ct > 5) {
            return false; // no point in adding large strips here
        }
        $ss = $this->getSimpleStrips($ct);
        $this->shuffle($ss);
        foreach ($ss as $s) {
// $this->log('testing strip '.json_encode($s), true);
            // $stripAdded = false;
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
                // $this->selectValue($cell->getIdx(), [$choice], ['initialStrip' => true]);
                $this->selectValue($cell->getIdx(), [$choice], ['initialStrip' => false, 'log' => true]);
            }
            // what still here?
// $this->log('added strip '.json_encode($s), true);
            // $stripAdded = true;
            // break;
            foreach ($strip as $cell) {
                $this->idxsInitialStrips[] = $cell->getIdx();
            }
            return true;
        }

        // return $stripAdded;
        $this->clearSelection($strip);
        return false;
    }

    protected function stripChoicesArray($strip)
    {
        $arr = [];
        foreach ($strip as $cell) {
            $val = $cell->getChoice() ?: 'X';
            $arr[] = $val;
        }

        return $arr;
    }

    protected function addNumber($idx)
    {
        if ($this->isNonDataCell($idx)) {
            return true;
        }

        if (!empty($this->cellChoices[$idx])) {
            return $this->replaceValue($idx);
        }

        $cell = $this->cells[$idx];

$this->log('add nbr '.$cell->dump(), true);

        $taken = $this->getTaken($idx);
        $available = array_values(array_diff($this->number_set, $taken));
        $available = $this->filterNumsThatCauseNonUnique($cell, $available);

        if (empty($available)) {
$this->log("nothing available at $idx ".$cell->dump(), true);
            // return $this->changeLastUnforcedNumber();
            $this->removeStrips($idx);
            return false;
        } else {
            return $this->selectValue($idx, $available, ['log' => true]);
        }
    }

    protected function replaceValue($idx)
    {
        $cell = $this->cells[$idx];

$this->log('replace val '.$cell->dump(), true);

        $previousVal = $this->cellChoices[$idx];

        $taken = $this->getTaken($idx);
        $taken[] = $previousVal;
        $available = array_values(array_diff($this->number_set, $taken));
        $available = $this->filterNumsThatCauseNonUnique($cell, $available);

        if (empty($available)) {
$this->log("nothing available at $idx ".$cell->dump(), true);
            // restore previous value
            $this->selectValue($idx, [$previousVal]);
            return true;
        } else {
            // test all until one improves the solution set
            return $this->testValues($idx, $available, ['log' => true]);
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
                if ($tmp_idx == $idx) {
                    continue; // if choice is set for the target, don't count it
                }
                $val = !empty($this->cellChoices[$tmp_idx]) ? $this->cellChoices[$tmp_idx] : 0;
                if (!in_array($val, $taken)) {
                    $taken[] = $val;
                }
            }
        }

        return $taken;
    }

    protected function getTakenIgnore($idx, $ignore)
    {
        $strips = $this->findMyStrips($idx);
        $ignoreIdxs = [];
        foreach ($ignore as $ignoreCell) {
            $ignoreIdxs[] = $ignoreCell->getIdx();
        }
        $available = $this->number_set;
        $taken = [];

        foreach ($strips as $strip) {
            foreach ($strip as $stripCell) {
                $tmpIdx = $stripCell->getIdx();
                if ($tmpIdx == $idx || in_array($tmpIdx, $ignoreIdxs)) {
                    continue;
                }
                $val = !empty($this->cellChoices[$tmpIdx]) ? $this->cellChoices[$tmpIdx] : 0;
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
        if (empty($commonValuedCellPairs)) { // FIX -- consider diagonal in 
            // 1 2
            // 7 8
            // 9 X <-- 7 fails
            return $available;
        }

        foreach ($commonValuedCellPairs as $pair) {
            $intersection = $this->interesect($pair, $cell);
            if ($intersection) {
                if($intersection->getIdx() == $idx) {
                    continue;
                }
                $intersectionValue = $intersection->getChoice();
                if (!$intersectionValue) {
                    continue;
                }
// $this->log($idx . ' pair '.$pair[0]->getChoice(), true);
                $this->unsetValue($available, $intersection->getChoice());
$this->log('f1 '.$this->cells[$idx]->dump().' cannot have ' .$intersection->getChoice(), true);
                // we also cannot have pair - iV + choice available
                // a  b    b  a
                // b  X    a  Y = b+X-a
                $pairValue = $pair[0]->getChoice();
                $diff = $pairValue - $intersectionValue;
                foreach ($available as $candidateValue) {
                    if (in_array($candidateValue + $diff, $available)) {
                        $this->unsetValue($available, $candidateValue);
// $this->log('type 2 '.$idx.' cannot have ' .$candidateValue, true);
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

        // note this let pass:
        // 8 6
        // 7 9 
        // so it is verrrry baaad. we only consider intersection of values between the strips. need to look at swapping in general.

        // for now, let's do parallel contiguous only:
        $idx = $cell->getIdx();
// $this->log('avail '.$cell->dump().' '.json_encode($available), true);
        $strips = $this->findMyStrips($idx, false);
        if (empty($strips)) {
            return $available;
        }

        $neighbors = $this->getNeighboringCoordinates($idx);
        foreach ($neighbors as $pos => $nbr) {
            $strips[$pos] = $nbr ? $this->findMyStrips($nbr) : ['h' => [], 'v' => []];
        }

        foreach ($available as $candidateValue) {
// $this->log('f2 candidate '.$candidateValue, true);
            $this->selectValue($idx, [$candidateValue]);
            $s = $this->findMyStrips($idx, false);
            $strips['h'] = $s['h'];
            $strips['v'] = $s['v'];
            $intersections = [];
            $intersections['top'] = $this->interesectByValue([$strips['h'], $strips['top']['h']]);
            $intersections['bottom'] = $this->interesectByValue([$strips['h'], $strips['bottom']['h']]);
            $intersections['left'] = $this->interesectByValue([$strips['v'], $strips['left']['v']]);
            $intersections['right'] = $this->interesectByValue([$strips['v'], $strips['right']['v']]);

            foreach ($intersections as $pos => $y) {
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

            // get the sum with candidateValue. Does it match the sum of a parallel substrip? need to test all substrips
            foreach ($s as $dir => $strip) {
// $this->log($dir.' : '.$this->getStripIdx($strip), true);
                if (empty($strip)) {
                    throw new \Exception("Empty Strip");
                }

                // get parallel substrips
                $vertical = $dir === 'v';
                $neighborSubstrips = $this->getNeighborSubstrips($strip, $cell, $vertical);
                if (empty($neighborSubstrips)) {
                    continue;
                }
// $this->log("ns for ".$this->cells[$idx]->dump(). ' '.$this->getStripIdx($strip), true);
// foreach ($neighborSubstrips as $ns) {
//     $this->log($this->getStripIdx($ns), true);
// }

                // $this->log('ss for '.$this->getStripIdx($strip).':', true);
                $fixedDim = $vertical ? $strip[0]->getCol() : $strip[0]->getRow();
                foreach ($neighborSubstrips as $ns) {
                    $this->sortCells($ns);
                    $mySubstrip = [];
// $this->log($this->getStripIdx($ns), true);
                    $nsSum = 0;
                    $sum = 0; // perhaps find a way to avoid re-calculating this
                    $nsFixedDim = $vertical ? $ns[0]->getCol() : $ns[0]->getRow();
                    foreach ($ns as $nsCell) {
                        $nsChoice = $nsCell->getChoice();
                        if (empty($nsChoice)) {
                            continue 2;
                        }
                        $nsSum += $nsChoice;
                        $freeDim = $vertical ? $nsCell->getRow() : $nsCell->getCol();
                        $i = $vertical ? $freeDim * $this->width + $fixedDim : $fixedDim * $this->width + $freeDim;
                        $choice = $this->cells[$i]->getChoice();
                        if (empty($choice)) {
                            continue 2;
                        }
                        $sum += $choice;
                        $mySubstrip[] = $this->cells[$i];
                    }
// $this->log($nsSum, true);
// $this->log($sum, true);
                    if ($sum === $nsSum) {
                        $this->unsetValue($available, $candidateValue);
                        $this->log($candidateValue . ' is not allowed', true);
                        continue 3;
                    }

                    // sep 27 rule
                    if (count($ns) == 2) { // let's see if 2x2 is enough
                        $xInPositionZero = $mySubstrip[0]->getChoice() == $candidateValue;
                        $c = $xInPositionZero ? $ns[0] : $ns[1];
                        $b = $xInPositionZero ? $ns[1] : $ns[0];
                        $a = $xInPositionZero ? $mySubstrip[1] : $mySubstrip[0];
                        if ($vertical) {
                            $tmp = $a;
                            $a = $c;
                            $c = $tmp;
                        }
                        // $this->log("abc $a $b $c", true);
                        $takenX = $this->getTakenIgnore($idx, array_merge($ns, $mySubstrip));
                        $takenA = $this->getTakenIgnore($a->getIdx(), array_merge($ns, $mySubstrip));
                        $takenB = $this->getTakenIgnore($b->getIdx(), array_merge($ns, $mySubstrip));
                        $takenC = $this->getTakenIgnore($c->getIdx(), array_merge($ns, $mySubstrip));
                        $validX = array_values(array_diff($this->number_set, $takenX));
                        $validA = array_values(array_diff($this->number_set, $takenA));
                        $validB = array_values(array_diff($this->number_set, $takenB));
                        $validC = array_values(array_diff($this->number_set, $takenC));
                        if ($this->failTests($candidateValue, $a->getChoice(), $b->getChoice(), $c->getChoice(), $validX, $validA, $validB, $validC)) {
                            $this->log($candidateValue . ' is not allowed (latest)', true);
$this->log($a->dump().', '.$b->dump().', '.$c->dump(), true);
                            $this->unsetValue($available, $candidateValue);
                            continue 3;
                        }
                    }
                }
            }
        }

        return $available;
    }

    protected function failTests($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        if ($this->failTest1($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
            return true;
        }
        if ($this->failTest2($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
            return true;
        }
        if ($this->failTest3($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
            return true;
        }
        if ($this->failTest4($X, $a, $b, $c, $setX, $setA, $setB, $setC)) {
            return true;
        }

        return false;
    }

    protected function failTest1($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // H-strip permutation
        // X valid (a)
        // a valid (X)
        // a+b-X valid in setB
        // b+c-(a+b)+X -> c-a+X valid in setC
        // X != a+b-X -> X != (a+b)/2
        // a+b-X != c-a+X -> X != (2a+b-c)/2
        // c-a+X != a -> X != 2a-c

        if (!in_array($X, $setA)) {
            return false;
        }

        if (!in_array($a, $setX)) {
            return false;
        }

        if (!in_array($a+$b-$X, $setB)) {
            return false;
        }

        if (!in_array($X+$c-$a, $setC)) {
            return false;
        }

        if ($X == ($a+$b)/2) {
            return false;
        }

        if ($X == 2*$a-$c) {
            return false;
        }

        if ($X == 2*$a-2*$b-$c) {
            return false;
        }

        return true;
    }

    protected function failTest2($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // V-strip permutation
        // c valid (X)
        // X valid (c)
        // b+c-X valid
        // a-c+X valid
        // X != (b+c)/2
        // X != (2c+b-a)/2
        // X != 2c-a

        if (!in_array($c, $setX)) {
            return false;
        }

        if (!in_array($X, $setC)) {
            return false;
        }

        if (!in_array($b+$c-$X, $setB)) {
            return false;
        }

        if (!in_array($X+$a-$c, $setA)) {
            return false;
        }

        if ($X == ($c+$b)/2) {
            return false;
        }

        if ($X == 2*$c-$a) {
            return false;
        }

        if ($X == (2*$c+$b-$a)/2) {
            return false;
        }

        return true;
    }

    protected function failTest3($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // H-neighbor permutation
        // c valid (b)
        // b valid (c)
        // a+b-c valid for a's position
        // X+c-b valid for X's position
        // c != (a+b)/2
        // X != 2b-c
        // X != 2b-2c+a

        if (!in_array($c, $setB)) {
            return false;
        }

        if (!in_array($b, $setC)) {
            return false;
        }

        if (!in_array($a+$b-$c, $setA)) {
            return false;
        }

        if (!in_array($X+$c-$b, $setX)) {
            return false;
        }

        if ($c == ($a+$b)/2) {
            return false;
        }

        if ($X == 2*$b-$c) {
            return false;
        }

        if ($X == 2*$b-2*$c+$a) {
            return false;
        }

        return true;
    }

    protected function failTest4($X, $a, $b, $c, $setX, $setA, $setB, $setC)
    {
        // V-neighbor permutation
        // b valid (a)
        // a valid (b)
        // b+c-a valid (c)
        // X+a-b valid (X)
        // a != (b+c)/2
        // X != 2b-a
        // X != 2b-2a+c

        if (!in_array($b, $setA)) {
            return false;
        }

        if (!in_array($a, $setB)) {
            return false;
        }

        if (!in_array($c+$b-$a, $setC)) {
            return false;
        }

        if (!in_array($X+$a-$b, $setX)) {
            return false;
        }

        if ($a == ($c+$b)/2) {
            return false;
        }

        if ($X == 2*$b-$a) {
            return false;
        }

        if ($X == 2*$b-2*$a+$c) {
            return false;
        }

        return true;
    }

    protected function getNonfixedPermutations($strip)
    {
// $this->log('getNonfixedPermutations '.count($strip), true);
        switch (count($strip)) {
            case 2:
                $a = [[1,0]];
                break;
            case 3:
                $a = [[1,2,0], [2,0,1]];
                break;
            case 4:
                $a = [[3,0,1,2],[3,2,1,0],[3,2,0,1],[2,0,3,1],[2,3,0,1],[2,3,1,0],[1,0,3,2],[1,2,3,0],[1,3,0,2]];
                break;
            default:
                $a = [];
        }

        return $a; // just the raw permutation 

        // if (empty($a)) {
        //     return [$strip];
        // }

        // $return = [];
        // foreach ($a as $perm) {
        //     $s = [];
        //     foreach ($perm as $idx) {
        //         $s[] = $strip[$idx];
        //     }

        //     $return[] = $s;
        // }

        // return $return;
    }

    protected function getNeighborSubstrips($strip, $cell, $vertical)
    {
        $stripIdx = $this->getStripIdx($strip);
        $cellIdx = $cell->getIdx();
        $idx = $cellIdx . '___' . $stripIdx;
        if (isset($this->neighborSubstrips[$idx])) {
            return $this->neighborSubstrips[$idx];
        }
        $neighborStrips = $this->getNeighborStrips($strip, $vertical);
        if (empty($neighborStrips)) {
            return [];
        }
// $this->log('getNeighborSubstrips '.$cell->dump().' '.$this->getStripIdx($strip), true);
        // we only want substrips that contain the target cell, so get all without it and add it back to each one below
        $stripWithoutTarget = [];
        foreach ($strip as $c) {
            if ($c->getIdx() !== $cell->getIdx()) {
                $stripWithoutTarget[] = $c;
            }
        }

        $return = [];
        $substrips = $this->findMySubstrips($stripWithoutTarget);

        foreach ($neighborStrips as $ns) {
            foreach ($substrips as $substrip) {
// $this->log('substrip '.$this->getStripIdx($substrip), true);
                $substrip[] = $cell;
                $projection = $this->getSetProjectionOntoStrip($ns, $substrip, $vertical);
                if (!empty($projection)) {
                    $return[] = $projection;
// $this->log('projection '.$this->getStripIdx($projection), true);
                }
            }
        }

        $this->neighborSubstrips[$idx] = $return;
        return $return;
    }

    protected function getSetProjectionOntoStrip($strip, $sss, $vertical)
    {
        if (empty($strip[0])) {
            throw new \Exception("Bad Index 1");
        }
        $fixedDim = $vertical ? $strip[0]->getCol() : $strip[0]->getRow();

        $freeDims = [];
        foreach ($strip as $cell) {
            $freeDims[] = $vertical ? $cell->getRow() : $cell->getCol();
        }

        $return = [];

// if ($vertical) {
// $this->log('strip '.$fixedDim.','.json_encode($freeDims), true);
// // foreach ($return as $c) {
// // $this->log($c->dump(), true);}
// $this->log('sss ', true);
// foreach ($sss as $c) {
// $this->log($c->dump(), true);}}
        foreach ($sss as $cell) {
            $freeDim = $vertical ? $cell->getRow() : $cell->getCol();
            if (!in_array($freeDim, $freeDims)) {
                return [];
            }

            $idx = $vertical ? $freeDim * $this->width + $fixedDim : $fixedDim * $this->width + $freeDim;
            $return[] = $this->cells[$idx];
        }

        return $return;
    }

    protected function getNeighborStrips($strip, $vertical)
    {
        if (empty($strip[0])) {
            throw new \Exception("Bad Index 2");
        }
        $idx = $this->getStripIdx($strip);
        if (isset($this->neighborStrips[$idx])) {
            return $this->neighborStrips[$idx];
        }
        $this->neighborStrips[$idx] = $vertical ? $this->getNeighborStripsV($strip) : $this->getNeighborStripsH($strip);
        return $this->neighborStrips[$idx];
    }

    protected function getStripIdx($strip)
    {
        $a = [];
        foreach ($strip as $cell) {
            $row = $cell->getRow();
            $col = $cell->getCol();
            $a[] = $row . '_' . $col;
        }

        return implode('__', $a);
    }

    protected function getNeighborStripsH($strip)
    {
        // each cell, get perp strips. create a box with all posible parallel strip fragmnets
        $stripRow = $strip[0]->getRow();
        $minRow = $stripRow;
        $maxRow = $stripRow;
        $minCol = $strip[0]->getCol();
        $maxCol = $strip[count($strip) - 1]->getCol();
        foreach ($strip as $cell) {
            $strips = $this->findMyStrips($cell->getIdx());
            $perpendicularStrip = $strips['v'];
            if (empty($perpendicularStrip[0])) {
                throw new \Exception("Bad Index 3");
            }
            $x = $perpendicularStrip[0]->getRow();
            if ($x < $minRow) {
                $minRow = $x;
            }
            $lastIdx = count($perpendicularStrip) - 1;
            $y = $perpendicularStrip[$lastIdx]->getRow();
            if ($y > $maxRow) {
                $maxRow = $y;
            }
        }

        $currentStrip = [];
        $return = [];
        for ($i = $minRow; $i <= $maxRow; $i++) {
            if (!empty($currentStrip)) {
                $return[] = $currentStrip;
                $currentStrip = [];
            }

            if ($i == $stripRow) {
                continue;
            }

            for ($j = $minCol; $j <= $maxCol; $j++) {
                // if nonblank and connects to strip, store
                $idx = $i * $this->width + $j;
                $cell = $this->cells[$idx];
                if ($cell->isDataCell() && $this->connectsH($idx, $strip)) {
                    $currentStrip[] = $cell;
                } else {
                    if (!empty($currentStrip)) {
                        $return[] = $currentStrip;
                        $currentStrip = [];
                    }
                }
            }
        }

        if (!empty($currentStrip)) {
            $return[] = $currentStrip;
            $currentStrip = [];
        }

        return $return;
    }

    protected function getNeighborStripsV($strip)
    {
        // each cell, get perp strips. create a box with all posible parallel strip fragmnets
$this->log('ssss '. $this->getStripIdx($strip), true);
        $stripCol = $strip[0]->getCol();
        $minCol = $stripCol;
        $maxCol = $stripCol;
        $minRow = $strip[0]->getRow();
        $maxRow = $strip[count($strip) - 1]->getRow();
        foreach ($strip as $cell) {
            $strips = $this->findMyStrips($cell->getIdx());
            $perpendicularStrip = $strips['h'];
            if (empty($perpendicularStrip[0])) {
                throw new \Exception("Bad Index 4");
            }
            $x = $perpendicularStrip[0]->getCol();
            if ($x < $minCol) {
                $minCol = $x;
            }
            $lastIdx = count($perpendicularStrip) - 1;
            $y = $perpendicularStrip[$lastIdx]->getCol();
            if ($y > $maxCol) {
                $maxCol = $y;
            }
        }

        $currentStrip = [];
        $return = [];

        for ($j = $minCol; $j <= $maxCol; $j++) {
            if ($j == $stripCol) {
                continue;
            }
            if (!empty($currentStrip)) {
// $this->log("clear", true);
                $return[] = $currentStrip;
                $currentStrip = [];
            }
            for ($i = $minRow; $i <= $maxRow; $i++) {
                $idx = $i * $this->width + $j;
// $this->log("($i,$j)", true);
                $cell = $this->cells[$idx];
                if ($cell->isDataCell() && $this->connectsV($idx, $strip)) {
                    $currentStrip[] = $cell;
// $this->log("add", true);
                } else {
                    if (!empty($currentStrip)) {
// $this->log("clear", true);
                        $return[] = $currentStrip;
                        $currentStrip = [];
                    }
                }
            }
        }
        return $return;
    }

    protected function connectsH($idx, $strip)
    {
        foreach ($strip as $cell) {
            if ($cell->getCol() == $idx % $this->width) {
                $projection = $cell;
                break;
            }
        }

        if (!isset($projection)) {
            return false;
        }

        $s = $this->findMyStrips($idx)['v'];
        foreach ($s as $cell) {
            if ($cell->getRow() == $projection->getRow()) {
                return true;
            }
        }

        return false;
    }

    protected function connectsV($idx, $strip)
    {
        foreach ($strip as $cell) {
            if ($cell->getRow() == floor($idx / $this->width)) {
                $projection = $cell;
                break;
            }
        }

        if (!isset($projection)) {
            return false;
        }

        $s = $this->findMyStrips($idx)['h'];
        foreach ($s as $cell) {
            if ($cell->getCol() == $projection->getCol()) {
                return true;
            }
        }

        return false;
    }

    protected function filterNumsThatCauseSwap3($cell, $available)
    {
        // each posible val (a) -- temporarily set cell to val a, consider his strips:
        //     find common vals, for each one (b):
        //         (add b's strips if they contain a
        //             each "a" cell in those strips, add their strips if they contain b) recurse
        //         count the a's and b's in the strips. if ==, can't set to a
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

    protected function findMySubstrips($strip)
    {
        $idx = $this->getStripIdx($strip);
        if (isset($this->substrips[$idx])) {
            return $this->substrips[$idx];
        }
        $count = count($strip);
        $members = pow(2,$count); 
        $return = array(); 
        for ($i = 0; $i < $members; $i++) { 
            $b = sprintf("%0".$count."b",$i); 
            $out = array(); 
            for ($j = 0; $j < $count; $j++) { 
                if ($b{$j} == '1') $out[] = $strip[$j]; 
            } 
            if (count($out) >= 1) { 
                $this->sortCells($out);
                $return[] = $out; 
            } 
        } 

        $this->substrips[$idx] = $return;
        return $return; 
    }

    protected function clearSelection($cells)
    {
        foreach ($cells as $cell) {
            $idx = $cell->getIdx();
            if (in_array($idx, $this->idxsInitialStrips)) {
                continue;
            }
            $this->cellChoices[$idx] = null;
            $this->cells[$idx]->setChoice(null);
            $this->unsetValue($this->setOrder, $idx);
        }
    }

    protected function handlePresets()
    {
        foreach ($this->cells as $idx => $cell) {
            if ($cell->isDataCell()) {
                $this->cellChoices[$idx] = $cell->getChoice();
            }
        }
    }

    protected function selectValue($idx, $choices, $options = [])
    {
        $log = !empty($options['log']);
        $initialStrip = !empty($options['initialStrip']);
$this->log('sv '.$this->cells[$idx]->dump().' '.json_encode($choices), $log);
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
        if ($initialStrip) {
            $this->idxsInitialStrips[] = $idx;
        }
$this->log('set '.$this->cells[$idx]->dump().' to '. $val, $log);
        return true;
    }

    protected function getBestChoice($idx, $choices)
    {
        $strips = $this->findMyStrips($idx);
        $selectionType = 'random'; // high, low
        foreach ($strips as $strip) {
            $decidedSum = 0;
            $decided = [];
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
$this->log('choices to complete ss '.json_encode($choicesToCompleteSimpleStrip), true);
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
$selectionType = 'random'; // minimize repetitive loops
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

    protected function testValues($idx, $choices)
    {
$this->log('tv '.$idx.' '.json_encode($choices), true);
        $scoreToBeat = $this->solutionScore;
        foreach ($choices as $choice) {
            $this->cellChoices[$idx] = $choice;
            $this->cells[$idx]->setChoice($choice);
            if ($this->alreadyTested()) {
                continue;
            }
            $counts = $this->testSolutionCandidate();
            if (empty($counts)) {
                // found uq solution
                $this->solvable = true;
                return true;
            }
            $total = array_sum($counts);
            if ($total < $scoreToBeat) {
                $this->readyForTesting = true;
                $best = $choice;
                $scoreToBeat = $total;
            }
        }
        if (!isset($best)) {
            return true;
        }
        $this->cellChoices[$idx] = $best;
        $this->cells[$idx]->setChoice($best);
        return $this->readyForTesting;
    }

    protected function testSolutionCandidate()
    {
        KakuroReducer::autoExecute([
            'grid' => $this->gridObj,
            'simpleReduction' => false,
        ]);

        $counts = []; // idx => count
        foreach($this->cells as $cell) {
            $idx = $cell->getIdx();
            if ($this->isNonDataCell($idx)) {
                continue;
            }
            $choices = $cell->getChoices();
            $choiceCount = count($choices);
            if ($choiceCount > 1) {
$this->log($this->cells[$idx]->dump().' has ' . $choiceCount . ' choices', true);
                $counts[$idx] = $choiceCount;
            }
        }

        if (!empty($counts)) {
            asort($counts);
            $this->storeTestResult($counts);
            return $counts;
        } else {
            $this->solvable = true;
            return null;
        }
    }

    protected function storeTestResult($counts)
    {
        $hash = $this->getSolutionCandidateHash();
        $this->solutionCandidateHashes[$hash] = array_sum($counts);
    }

    protected function storeCurrentSolutionCounts($counts)
    {
        $this->solutionCounts = $counts;
        $this->solutionScore = array_sum($counts);
    }

    protected function getSolutionCandidateHash()
    {
        return implode('_', $this->cellChoices);
    }

    protected function alreadyTested()
    {
        $hash = $this->getSolutionCandidateHash();
        return !empty($this->solutionCandidateHashes[$hash]);
    }

    protected function makeEasilyReducible()
    {
        if ($this->timesThru++ > $this->maxTimesThru) {
            $this->resetNumbers();
            return false;
        }
$this->log('test easily reduc', true);
$this->display(3);

        $counts = $this->testSolutionCandidate();
        if (!empty($counts)) {
            asort($counts);
            $this->storeCurrentSolutionCounts($counts);
            $this->idxsToProcess = array_keys($counts);
            return false;
        } else {
$this->log('yes easily reduc', true);
            $this->solvable = true;
            return true;
        }
    }

    protected function fixByThrees($idxs)
    {
        // of my strips find allsubstrips of size 3
        // if I don't have any, go back and get me another idx (better to pass you an array then huh?) yeh
        // for all my 3-substrips, get the parallel connected guys. each 3-substrip + parallel nbr set is to be fixed as follows:
        // get the shortest strip (if more than one, rules to come). make it ss (if not possible rules to come)
        while ($idx = array_pop($idxs)) {
            $strips = $this->findMyStrips($idx);
            foreach ($strips as $dir => $strip) {
                $neighborSubstrips = $this->getNeighborSubstrips($strip, $this->cells[$idx], $dir == 'v');
                foreach ($neighborSubstrips as $ns) {
                    $this->log($this->getStripIdx($ns), true);
                }
            }
exit;
        }
    }

    protected function changeToSimpleStripsWherePossible($idxs)
    {
        while ($idx = array_pop($idxs)) {
            $strips = $this->findMyStrips($idx);
            foreach($strips as $strip) {
$this->log("check ".$this->cells[$idx]->dump(). ' '. $this->getStripIdx($strip), true);
                if (!$this->isSimpleStrip($strip)) {
$this->log("fix ".$this->cells[$idx]->dump(). ' '. $this->getStripIdx($strip), true);
exit;
                }
            }
        }
        
    }

    protected function removeStrips($idx)
    {
        $strips = $this->findMyStrips($idx);
        $idxs = [$idx];
        foreach($strips as $strip) {
            if (!$this->isSimpleStrip($strip)) { // TBI, but what if both strips are simple? then you remove nothing...
                foreach ($strip as $cell) {
                    $i = $cell->getIdx();
                    // if (!in_array($i, $idxs) && !in_array($i, $this->idxsInitialStrips)) { // keepInitial TBI
                    if (!in_array($i, $idxs)) {
                        $idxs[] = $i;
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
    }

    protected function isSimpleStrip($strip)
    {
        $len = count($strip);
        $vals = [];
        foreach ($strip as $cell) {
            $vals[] = $cell->getChoice();
        }
        sort($vals);
        $ss = $this->getSimpleStrips($len);
        foreach ($ss as $simpleStrip) {
            sort($simpleStrip);
            foreach ($vals as $idx => $choice) {
                if ($choice != $simpleStrip[$idx]) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    protected function classify()
    {
        $parameters = [
            'grid' => $this->gridObj,
        ];

        $classifier = KakuroClassifier::autoExecute($parameters, null);
        $this->gridObj->setDifficulty($classifier->getDifficulty());
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

    public function display($padding = 10, $header = 'choice')
    {
        $str = "\n$header\n" . $this->displayChoicesHeader();

        foreach ($this->cells as $idx => $cell) {
            if ($this->isNonDataCell($idx, true)) {
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

    protected function getChoiceArray($strip)
    {
        $a = [];
        foreach ($strip as $cell) {
            $a[] = $cell->getChoice();
        }

        return $a;
    }

    protected function resetNumbers() {
        $this->log('restartin', true);
        // $this->idxsWithNoChoice = [];
        $this->idxsInitialStrips = [];
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