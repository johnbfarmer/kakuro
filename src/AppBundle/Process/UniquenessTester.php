<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class UniquenessTester extends KakuroReducer
{
    protected 
        $testIdx,
        $testVal,
        $originalVal,
        $choiceCount = 0;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->testIdx = $this->parameters['testIdx'];
        $this->testVal = $this->parameters['testVal'];
    }

    protected function execute()
    {
        $this->cells = $this->gridObj->getCells();
        $this->originalVal = $this->cells[$this->testIdx]->getChoice();
        $this->stripsNew = $this->gridObj->getStrips();
        $this->result = [
            'hasSolution' => false,
            'notReducible' => false,
            'solution' => null,
        ];
$this->log($this->displayChoicesHeader(), true);
        $this->tryCell($this->testIdx, $this->testVal);
    }

    protected function tryCell($idx, $val)
    {
        foreach ($this->cells as $cell) {
            $cell->setChoices([]);
        }
        $cell = $this->cells[$idx];
        $cell->setChoices([$this->testVal]);
        $this->UiChoices[$idx] = $this->testVal;
        $strips = $cell->getStripObjects();
        if ($this->reduce($strips, true)) {
            $this->result['hasSolution'] = $this->isSolved();
            return;
        }
    }

    protected function calculateChoices($strips)
    {
        while (!$strips->isEmpty()) {
$ctr = 0;
if ($ctr++ > 2000) {
    return false;
}
            $this->changedStrips->clear();
            foreach ($strips as $strip) {
$this->log('strip '.$strip->dump(), true);
                $cells = $this->getCellsForStrip($strip);
                $pv = $this->getPossibleValuesForStrip($strip, $cells)['values'];
                foreach ($cells as $cell) {
                    if ($cell->getIdx() != $this->testIdx) {
                        $choices = $cell->getChoices();
                        $choiceCount = count($choices);
                        if ($choiceCount === 1) {
                            continue;
                        }
                        $changed = false;
                        if (empty($choices)) {
                            $choices = $pv;
                            $changed = true;
                        }
                        $newChoices = array_values(array_intersect($pv, $choices));
                        $newChoiceCount = count($newChoices);
                        if (empty($newChoices)) {
// $this->log($cell->getIdx() . ' ' . $strip->getTotal() . ' not possible', true);
                            return false;
                        }
                        if ($changed || $newChoiceCount < $newChoiceCount) {
                            $cell->setChoices($newChoices);
$this->log('set choices '.$cell->dump().' '.json_encode($newChoices), true);
                            $this->addCellsStripsToChanged($cell);
                            $this->cells[$cell->getIdx()] = $cell; // not needed?
                        }
                    }
                }
                if (!$this->adv($strip, $cells)) {
$this->log($cell->getIdx() . ' adv fails', true);
                    return false;
                }
// $this->displayChoices(3);
            }

            $strips = clone $this->changedStrips;
        }

        return $this->isSolved();
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

    public function getValues($target, $size, $used = [])
    {
        return $this->gridObj->getPossibleValues($target, $size, $used);
    }

    protected function addCellsStripsToChanged($cell)
    {
        foreach ($cell->getStripObjects() as $strip) {
            if (!$this->changedStrips->contains($strip)) {
                $this->changedStrips[] = $strip;
            }
        }
    }

    protected function isSolved()
    {
$this->log('check if solution', true);
$this->displayChoices(3);
        foreach($this->stripsNew as $strip) {
            $cells = $this->getCellsForStrip($strip);
            $cellSum = 0;
            foreach ($cells as $cell) {
                if (count($cell->getChoices()) !== 1) {
$this->log('can\'t tell! '.$cell->getIdx().' has too many choices: '.json_encode($cell->getChoices()), true);
                    $this->result['notReducible'] = true;
                    return false; // too hard man
                }
                $cellSum += current($cell->getChoices());
            }
            if ($cellSum != $strip->getTotal()) {
$this->log('nope!', true);
                return false;
            }
$this->log('shore is!', true);
            return true;
        }
    }

    protected function displayChoicesHeader()
    {
        $x = $this->cells[$this->testIdx]->getChoice();
        return "{$this->testIdx} try {$this->testVal} (was $x)\n\n";
    }

    public function getResult()
    {
        return $this->result;
    }
}