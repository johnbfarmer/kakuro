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
        $this->clearLog();
        $this->cellsNew = $this->gridObj->getCells();
        $this->stripsNew = $this->gridObj->getStrips();
        $this->tryCell($this->testIdx, $this->testVal);
        $this->result = [
            'hasSolution' => false,
            'solution' => null,
        ];
    }

    protected function tryCell($idx, $val)
    {
        $cell = $this->cellsNew[$idx];
        $cell->setChoices([$cell->getChoice()]);
        $strips = $cell->getStripObjects();
        if ($this->calculateChoices($strips)) {
            $this->result['hasSolution'] = true;
        }
    }

    protected function calculateChoices($strips)
    {
        while (!$strips->isEmpty()) {
            $this->changedStrips->clear();
            foreach ($strips as $strip) {
                $cells = $this->getCellsForStrip($strip);
                $pv = $this->getPossibleValuesForStrip($strip, $cells)['values'];
                foreach ($cells as $cell) {
                    if ($cell->getIdx() != $this->testIdx) {
                        $choices = $cell->getChoices();
                        if (empty($choices)) {
                            $choices = $pv;
                        }
                        $choices = array_values(array_intersect($pv, $choices));
                        $cell->setChoices($choices);
                        $this->addCellsStripsToChanged($cell);
                        $this->cellsNew[$cell->getIdx()] = $cell;
                    }
                }
            }

            $strips = $this->changedStrips;
        }

        return $this->isSolved();
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
        $this->displayChoices();
        foreach($this->stripsNew as $strip) {
            $cells = $this->getCellsForStrip($strip);
            $cellSum = 0;
            foreach ($cells as $cell) {
                if (count($cell->getChoices() !== 1)) {
                    return false;
                }
                $cellSum += current($cell->getChoices());
            }
            if ($cellSum != $strip->getTotal()) {
                return false;
            }

            return true;
        }
    }

    public function getResult()
    {
        return $this->result;
    }
}