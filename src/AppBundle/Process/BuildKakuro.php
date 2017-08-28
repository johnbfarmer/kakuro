<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;
use AppBundle\Entity\Strip;
use AppBundle\Entity\Solution;

class BuildKakuro extends BaseKakuro
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
        $timesThru = 0,
        $maxTimesThru = 5,
        $restarts = 0,
        $maxRestarts = 5,
        $lastChoice = [],
        $frameId,
        $idxsWithNoChoice = [],
        $setOrder = [],
        $forbiddenValues = [],
        $testResult,
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
    }

    public function execute()
    {
        parent::execute();
        $this->createKakuro();
    }

    public function createKakuro()
    {
        $this->gridObj = new Grid();
        $this->em->persist($this->gridObj);
        $parameters = $this->parameters;
        $parameters['grid'] = $this->gridObj;
        $name = $this->em->getRepository('AppBundle:Grid')->getNextUniqueGridName();
        $this->gridObj->setName($name);
        $this->cells = BuildKakuroFrame::autoExecute($parameters)->getCells();
        // $this->gridObj->setWidth($this->width);
        // $this->gridObj->setHeight($this->height);
        $parameters['cells'] = $this->cells;
        $success = BuildKakuroSolution::autoExecute($parameters)->isSolvable();
        if ($success) {
            $this->save();
        } else {
            throw new \Exception("No luck");
        }
    }

    protected function save()
    {
        $this->log('unique solution found -- saving', true);
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