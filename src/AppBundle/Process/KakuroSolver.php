<?php

namespace AppBundle\Process;

class KakuroSolver extends KakuroReducer
{
    protected function execute()
    {
        $this->reductionLevel = 4;
        $this->maxChoicesForProbing = 2;
        $this->maxNestingLevelForProbing = 1;
        parent::execute();
        $this->result = '';
        if ($this->fails) {
            return $this->storeResult(0);
        } else {
            // $this->result = $this->display(3);
            $a = [];
            foreach ($this->cells as $idx => $cell) {
                if ($cell->getCol() < 1) { continue; }
                if ($cell->getRow() < 1) { continue; }
                if ($this->isBlank($idx, true)) {
                    $c = '.';
                } else {
                    $ch = $cell->getChoices();
                    if (count($ch) > 1) {
                        return $this->storeResult(0);
                    }
                    $c = implode('', $cell->getChoices());
                }
                $a[] = $c;
            }

            $this->result = implode(',', $a);
            $this->storeResult(1);

        }
    }

    protected function storeResult($u = 0)
    {
        $this->log($this->parameters['grid']->getId().' '.$u, true);
        $sql = 'UPDATE grids SET solution_key=?, is_unique=? WHERE id=?';
        $this->exec($sql, true, [$this->result, $u, $this->parameters['grid']->getId()]);
    }
}