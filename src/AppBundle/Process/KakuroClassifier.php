<?php

namespace AppBundle\Process;

class KakuroClassifier extends KakuroReducer
{
    protected
        $difficulty = 5;

    protected function execute()
    {
        $this->simpleReduction = true;
        parent::execute();
        if ($this->fails) {
            $this->result = "not able to solve with simple reduction";
        } else {
            // see % solved or choice ratio
            $this->simpleReduction = false;
            $this->display(3);
            $cellCount = 0;
            $cellCountSolved = 0;
            foreach ($this->cells as $idx => $cell) {
                if ($cell->isDataCell()) {
                    $cellCount++;
                    $choices = $cell->getChoices();
                    if (count($choices) == 1) {
                        $cellCountSolved++;
                    }
                }
            }

            $pctSolved = $cellCountSolved / $cellCount;
            $unsolved = $cellCount - $cellCountSolved;
            $this->log($unsolved, true);
            $this->difficulty = min(1 + ceil($unsolved / 12), 10);
            parent::execute();
            if ($this->fails) {
                throw new \Exception("failed to reduce");
            } else {
                $this->display(3);

            }
        }
    }

    public function getDifficulty()
    {
        return $this->difficulty;
    }
}