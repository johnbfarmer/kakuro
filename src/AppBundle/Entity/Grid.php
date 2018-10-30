<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Process\BuildTables; // move to helper?

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\GridRepository")
 * @ORM\Table(name="grids")
 */
class Grid
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    
    /**
     * @ORM\Column(type="string", length=4)
     */
    private $name;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $height = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $width = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $difficulty = 5;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $libraryIndex = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $show = 0;

    /**
     * @ORM\OneToMany(targetEntity="Cell", mappedBy="grid", orphanRemoval=true, cascade={"persist"})
     * @ORM\OrderBy({"row" = "ASC", "col" = "ASC"})
     */
    private $cells;

    /**
     * @ORM\OneToMany(targetEntity="Solution", mappedBy="grid", orphanRemoval=true, cascade={"persist"})
     * @ORM\OrderBy({"row" = "ASC", "col" = "ASC"})
     */
    private $solutions;

    private $strips;
    private $number_set = [1,2,3,4,5,6,7,8,9]; // TBI take as parameter
    private $pvFinder;

    public function __construct()
    {
        $this->cells = new ArrayCollection();
        $this->strips = new ArrayCollection();
        $this->solutions = new ArrayCollection();
        $this->pvFinder = new BuildTables(['number_set' => $this->number_set], null);
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight()
    {
        return $this->height;
    }

    public function setWidth($width)
    {
        $this->width = $width;

        return $this;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function setDifficulty($difficulty)
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getDifficulty()
    {
        return $this->difficulty;
    }

    public function setShow($show)
    {
        $this->show = $show;
        return $this;
    }

    public function getShow()
    {
        return $this->show;
    }

    public function setLibraryIndex($libraryIndex)
    {
        $this->libraryIndex = $libraryIndex;
        return $this;
    }

    public function getLibraryIndex()
    {
        return $this->libraryIndex;
    }

    public function getCells()
    {
        return $this->cells;
    }

    public function getSolutions()
    {
        return $this->solutions;
    }

    public function getStrips()
    {
        return $this->strips;
    }

    public function addCell($cell)
    {
        $this->cells[] = $cell;
        $cell->setGrid($this);
        return $this;
    }

    public function removeAllCells()
    {
        $this->cells = new ArrayCollection();
        return $this;
    }

    public function addStrip($strip, $idx)
    {
        $this->strips->set($idx, $strip);
        return $this;
    }

    public function addSolution($solution)
    {
        $this->solutions[] = $solution;
        $solution->setGrid($this);
        return $this;
    }

    public function setStripsForCell($cell)
    {
        if ($cell->isDataCell()) {
            foreach ($this->strips as $idx => $strip) {
                if ($cell->getRow() >= $strip->getStartRow() && $cell->getRow() <= $strip->getStopRow()) {
                    if ($cell->getCol() >= $strip->getStartCol() && $cell->getCol() <= $strip->getStopCol()) {
                        if ($strip->getDir() === 'h') {
                            $cell->setStripH($idx);
                        }
                        if ($strip->getDir() === 'v') {
                            $cell->setStripV($idx);
                        }
                    }
                }
            }
            if (!$cell->getStripH() || !$cell->getStripV()) {
                $x = 'break';
            }
        }
    }

    public function getForApi()
    {
        $default_cell = [
            'display' => null,
            'is_data' => true,
            'choices' => [],
        ];
        $height = $this->height;
        $width = $this->width;
        $cells = array_fill(0, $width * $height, $default_cell);
        foreach ($this->cells as $cell) {
            $a = $cell->getForApi($width);
            $idx = $a['idx'];
            $a['choices'] = [];
            unset($a['idx']);
            $cells[$idx] = $a;
        } 

        return [
            'height' => $height,
            'width' => $width,
            'cells' => array_values($cells),
        ];
    }

    public function calculateStrips()
    {
        $id = 0;
        $previous_idx = null;
        foreach ($this->cells as $cell) {
            if ($cell->isDataCell()) {
                continue;
            }
            if (!is_null($previous_idx)) {
                $stop = $cell->getRow() === $this->strips[$previous_idx]->getStartRow() ? $cell->getCol() - 1 : $this->width - 1;
                $this->strips[$previous_idx]->setStopCol($stop);
                $this->strips[$previous_idx]->calculateLen();
                $previous_idx = null;
            }
            $sum = $cell->getLabelH();
            if ($sum) {
                $strip = new Strip();
                $idx = $id++;
                $strip->setId($idx);
                $strip->setDir('h');
                $strip->setTotal($sum);
                $strip->setStartRow($cell->getRow());
                $strip->setStopRow($cell->getRow());
                $strip->setStartCol($cell->getCol() + 1);
                $this->strips->set($idx, $strip);
                $previous_idx = $idx;
            }
        }

        if (!is_null($previous_idx)) {
            $this->strips[$previous_idx]->setStopCol($this->width - 1);
            $this->strips[$previous_idx]->calculateLen();
            $previous_idx = null;
        }

        $iterator = $this->cells->getIterator();
        $iterator->uasort(function ($first, $second) {
            $f = $first->getCol();
            $s = $second->getCol();
            if ($f === $s) {
                return $first->getRow() < $second->getRow() ? -1 : 1;
            }
            return $f < $s ? -1 : 1;
        });
        $cells = new ArrayCollection(iterator_to_array($iterator));

        foreach ($cells as $cell) {
            if ($cell->isDataCell()) {
                continue;
            }
            if (!is_null($previous_idx)) {
                $stop = $cell->getCol() === $this->strips[$previous_idx]->getStartCol() ? $cell->getRow() - 1 : $this->height - 1;
                $this->strips[$previous_idx]->setStopRow($stop);
                $this->strips[$previous_idx]->calculateLen();
                $previous_idx = null;
            }
            $sum = $cell->getLabelV();
            if ($sum) {
                $strip = new Strip();
                $idx = $id++;
                $strip->setId($idx);
                $strip->setDir('v');
                $strip->setTotal($sum);
                $strip->setStartCol($cell->getCol());
                $strip->setStopCol($cell->getCol());
                $strip->setStartRow($cell->getRow()+1);
                $this->strips->set($idx, $strip);
                $previous_idx = $idx;
            }
        }

        if (!is_null($previous_idx)) {
            $this->strips[$previous_idx]->setStopRow($this->height - 1);
            $this->strips[$previous_idx]->calculateLen();
            $previous_idx = null;
        }

        foreach($this->strips as $strip) {
            $strip->setPossibleValues($this->pvFinder->findValues($strip->getTotal(), $strip->getLen(), []));
        }
    }

    public function getForProcessing()
    {
        if (!$this->pvFinder) {
            $this->pvFinder = new BuildTables(['number_set' => $this->number_set], null);
        }
        $this->strips = new ArrayCollection();
        $this->calculateStrips();
        $default_cell = null;
        $height = $this->height;
        $width = $this->width;
        $cells = array_fill(0, $width * $height, $default_cell);
        foreach ($this->cells as $cell) {
            $idx = $cell->getRow() * $width + $cell->getCol();
            $cells[$idx] = $cell;
        }

        foreach ($cells as $idx => $cell) {
            if (!$cell) {
                $cell = new Cell();
                $col = $idx % $width;
                $row = floor($idx / $width);
                $cell->setCol($col);
                $cell->setRow($row);
                $cell->setDataCell($col && $row);
                $cell->setGrid($this);
            }

            $cells[$idx] = $cell;
        }

        $this->cells = [];
        foreach ($cells as $cell) {
            $this->setStripsForCell($cell);
            $cell->calculatePossibleValues();
            $this->cells[] = $cell;
        }

        return $this->cells;
    }

    public function getPossibleValues($target, $size, $used = [])
    {
        return $this->pvFinder->findValues($target, $size, $used);
    }

    public function dumpTable()
    {
        return $this->pvFinder->dumpTable();
    }
}
