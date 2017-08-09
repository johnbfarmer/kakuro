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
        $originalState = $this->gridObj->getForProcessing();
        $this->cellsNew = $originalState;
        $this->stripsNew = $this->gridObj->getStrips();
        $this->tryCell($this->testIdx, $this->testVal);
    }

    protected function tryCell($idx, $val)
    {
        $this->result = false;
    }

    public function getResult()
    {
        return $this->result;
    }
}