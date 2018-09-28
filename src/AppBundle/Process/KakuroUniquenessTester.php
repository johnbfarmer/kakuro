<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;

class KakuroUniquenessTester extends KakuroReducer
{
    protected
        $cells,
        $height,
        $width,
        $hasError = false,
        $hasUniqueSolution = true;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        // $this->cells = $this->parameters['uiChoices'];
        $this->height = $this->parameters['height'];
        $this->width = $this->parameters['width'];
    }

    protected function execute()
    {
        $this->table_builder = new BuildTables(
            ['number_set' => $this->number_set,], $this->em
        );
        $this->gridObj = $this->getGrid();
        $this->cells = $this->gridObj->getCells();
        $this->gridObj->calculateStrips();
        $this->stripsNew = $this->gridObj->getStrips();
        if (!empty($this->cells)) {
            foreach ($this->cells as $cell) {
                if ($cell->isDataCell()) {
                    $this->gridObj->setStripsForCell($cell);
                    $pv = $cell->getPossibleValues();
                    $idx = $cell->getRow() * $this->width + $cell->getCol();
                    $choices = $this->uiChoices[$idx]['choices'];
                    $choices = !empty($choices) ? array_values(array_intersect($choices, $pv)) : $pv;
                    $this->cells[$idx]->setChoices($choices);
                }
            }
        }

        if (!$this->reduce($this->stripsNew, !$this->simpleReduction)) {
            $this->fails = true;
        }

        $gridForResponse = $this->getGridForResponse();
        $this->cells = $gridForResponse['cells'];
        $this->hasError = $gridForResponse['error'];
        foreach ($this->cells as $cell) {
            if (count($cell['choices']) > 1) {
                $this->hasUniqueSolution = false;
            }
        }
    }

    protected function getGrid()
    {
        $grid = new Grid();
        $grid->setHeight($this->height);
        $grid->setWidth($this->width);
        foreach($this->uiChoices as $cell) {
            $c = new Cell();
            $c->setGrid($grid);
            $c->setLocation($cell['row'], $cell['col']);
            $c->setDataCell($cell['is_data']);
            if (!$cell['is_data']) {
                $c->setLabelH($cell['display'][1]);
                $c->setLabelV($cell['display'][0]);
            }
            $grid->addCell($c);
        }

        return $grid;
    }

    public function getApiResponse()
    {
        return [
            'grid' => $this->cells,
            'hasUniqueSolution' => $this->hasUniqueSolution,
            'hasError' => $this->hasError,
        ];
    }
}