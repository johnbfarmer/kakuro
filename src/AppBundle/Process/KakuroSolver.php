<?php

namespace AppBundle\Process;

class KakuroSolver extends KakuroReducer
{
    protected function execute()
    {
        parent::execute();
        if ($this->fails) {
            $this->result = "not able to solve";
        } else {
            $this->result = "yeah";//$this->display(3);
        }
    }
}