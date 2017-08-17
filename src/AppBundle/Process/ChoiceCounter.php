<?php

namespace AppBundle\Process;

class ChoiceCounter extends KakuroReducer
{
    protected 
        $targetIdx,
        $targetValue,
        $choiceCount = 0;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->targetIdx = $this->parameters['targetIdx'];
        $this->targetValue = $this->parameters['targetValue'];
    }

    protected function execute()
    {
        $this->cells = $this->gridObj->getCells();
        $this->stripsNew = $this->gridObj->getStrips();
        $this->result = [
            'choiceCount' => 0,
        ];
$this->log($this->displayChoicesHeader(), true);
        $this->changeCell($this->targetIdx, $this->targetValue);
    }

    protected function changeCell($idx, $val)
    {
        foreach ($this->cells as $cell) {
            $cell->setChoices([]);
        }
        $cell = $this->cells[$idx];
        $previousValue = $cell->getChoice($val);
        $diff = $val - $previousValue;
        $cell->setChoice($val);
        // $cell->setChoices([$val]);
        $strips = $cell->getStripObjects();
        foreach ($strips as $strip) {
            $previousTotal = $strip->getTotal();
            $this->stripsNew[$strip->getId()]->setTotal($previousTotal + $diff);
        }
        if ($this->reduce($strips, true)) {
            $choiceCount = 0;
            foreach ($this->cells as $cell) {
                $choiceCount += count($cell->getChoices());
            }
            $this->result['choiceCount'] = $choiceCount;
        }
    }

    protected function displayChoicesHeader()
    {
        $x = $this->cells[$this->targetIdx]->getChoice();
        return "choice counter {$this->targetIdx} try {$this->targetValue} (was $x)\n\n";
    }

    public function getResult()
    {
        return $this->result;
    }
}