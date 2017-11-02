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
        $this->file = !empty($parameters['file']) ? $parameters['file'] : null;
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
        if ($this->file) {
            $parameters['fromFile'] = true;
            $this->cells = $parameters['cells'] = $this->readInputFile();
        } else {
            $name = $this->em->getRepository('AppBundle:Grid')->getNextUniqueGridName();
            $this->gridObj->setName($name);
            $this->cells = BuildKakuroFrame::autoExecute($parameters)->getCells();
            $parameters['cells'] = $this->cells;
        }
        $success = BuildKakuroSolution::autoExecute($parameters)->isSolvable();
        if ($success) {
            $this->save();
        } else {
            throw new \Exception("No luck");
        }
    }

    protected function readInputFile()
    {
        $f = fopen($this->file, 'r');
        $this->gridObj->setName(pathinfo($this->file, PATHINFO_FILENAME));
        $h = 0;
        $fileCells = [];
        $anchors = [];
        $cells = [];
        $sum = 0;
        $lastRow = 0;
        $lastCol = 0;
        while ($ln = fgets($f)) {
            $arr = explode('  ', trim($ln));
            if (empty($arr)) {
                continue;
            }
            $fileCells[] = $arr;
            $w = count($arr);
            $i = $h++;
            $anchors[$i] = [];

            foreach ($arr as $j => $cell) {
                if ($cell === '.') {
                    $anchors[$lastRow][$lastCol] = ['label_h' => $sum];
                    $sum = 0;
                    $lastRow = $i;
                    $lastCol = $j;
                } else {
                    $sum += $cell == 'X' ? 0 : $cell;
                }
            }
        }

        $anchors[$lastRow][$lastCol] = ['label_h' => $sum];

        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $cell = $fileCells[$i][$j];
                if ($cell === '.') {
                    $anchors[$lastRow][$lastCol]['label_v'] = $sum;
                    $sum = 0;
                    $lastRow = $i;
                    $lastCol = $j;
                } else {
                    $sum += $cell == 'X' ? 0 : $cell;
                }
            }
        }

        $anchors[$lastRow][$lastCol]['label_v'] = $sum;

        $this->gridObj->setWidth($w);
        $this->gridObj->setHeight($h);
        for ($i=0; $i < $h; $i++) {
            for ($j=0; $j < $w; $j++) {
                $cell = new Cell();
                $this->gridObj->addCell($cell);
                $cell->setLocation($i, $j);
                $idx = $cell->getIdx();
                if (!empty($anchors[$i][$j])) {
                    $cell->setDataCell(false);
                } else {
                    $cell->setDataCell(true);
                    $choice = $fileCells[$i][$j];
                    if ($choice !== 'X') {
                        $cell->setChoice($fileCells[$i][$j]);
                    }
                }
                $cells[$idx] = $cell;
            }
        }

        return $cells;
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