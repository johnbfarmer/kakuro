<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class UniquenessTester extends GridReducer
{
    protected 
        $testIdx,
        $testVal;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->testIdx = $this->parameters['testIdx'];
        $this->testVal = $this->parameters['testVal'];
    }

    protected function execute()
    {
        $this->cellsNew = $this->gridObj->getCells();
        $this->stripsNew = $this->gridObj->getStrips();
        $this->result = [
            'hasSolution' => false,
            'solution' => null,
        ];
$this->log($this->displayChoicesHeader(), true);
        $this->tryCell($this->testIdx, $this->testVal);
    }

    protected function tryCell($idx, $val)
    {
        foreach ($this->cellsNew as $cell) {
            $cell->setChoices([]);
        }
        $cell = $this->cellsNew[$idx];
        $cell->setChoices([$this->testVal]);
        $strips = $cell->getStripObjects();
        if ($this->calculateChoices($strips)) {
            $this->result['hasSolution'] = true;
            return;
        }
    }

    protected function calculateChoices($strips)
    {
        while (!$strips->isEmpty()) {
$ctr = 0;
if ($ctr++ > 200) {
    return false;
}
            $this->changedStrips->clear();
            foreach ($strips as $strip) {
                $cells = $this->getCellsForStrip($strip);
                $pv = $this->getPossibleValuesForStrip($strip, $cells)['values'];
                foreach ($cells as $cell) {
                    if ($cell->getIdx() != $this->testIdx) {
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
$this->log($cell->getIdx() . ' ' . $strip->getTotal() . ' not possible', true);
                            return false;
                        }
                        if ($changed || count($newChoices) < count($choices)) {
// $this->log('set choices ' . $cell->getIdx() . ' ' . json_encode($newChoices), true);
                            $cell->setChoices($newChoices);
                            $this->addCellsStripsToChanged($cell);
                            $this->cellsNew[$cell->getIdx()] = $cell; // not needed?
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
                    return true; // too hard man
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
        $x = $this->cellsNew[$this->testIdx]->getChoice();
        return "{$this->testIdx} try {$this->testVal} (was $x)\n\n";
    }

    public function getResult()
    {
        return $this->result;
    }
}