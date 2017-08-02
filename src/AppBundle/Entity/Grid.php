<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Process\BuildTables; // move to helper?

/**
 * @ORM\Entity
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
     * @ORM\Column(type="integer")
     */
    private $height = 0;
    
    /**
     * @ORM\Column(type="integer")
     */
    private $width = 0;

    /**
     * @ORM\OneToMany(targetEntity="Cell", mappedBy="grid", orphanRemoval=true, cascade={"persist"})
     * @ORM\OrderBy({"row" = "ASC", "col" = "ASC"})
     */
    private $cells;

    private $strips = [];
    private $number_set = [1,2,3,4,5,6,7,8,9];
    private $pvFinder;

    public function __construct()
    {
        $this->cells = new ArrayCollection();
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

    public function getStrips()
    {
        return $this->strips;
    }

    protected function setStripsForCell($cell)
    {
        if ($cell->isDataCell()) {
            foreach ($this->strips as $strip) {
                if ($cell->getRow() >= $strip->getStartRow() && $cell->getRow() <= $strip->getStopRow()) {
                    if ($cell->getCol() >= $strip->getStartCol() && $cell->getCol() <= $strip->getStopCol()) {
                        if ($strip->getDir() === 'h') {
                            $cell->setStripH($strip);
                        }
                        if ($strip->getDir() === 'v') {
                            $cell->setStripV($strip);
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
        $height = $this->height + 1;
        $width = $this->width + 1;
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

    protected function calculateStrips()
    {
        $id = 0;
        $previous_idx = null;
        foreach ($this->cells as $cell) {
            if (!is_null($previous_idx)) {
                $stop = $cell->getRow() === $this->strips[$previous_idx]->getStartRow() ? $cell->getCol() - 1 : $this->width;
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
                $strip->setStartCol($cell->getCol()+1);
                $this->strips[$idx] = $strip;
                $previous_idx = $idx;
            }
        }

        if (!is_null($previous_idx)) {
            $this->strips[$previous_idx]->setStopCol($this->width);
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
            if (!is_null($previous_idx)) {
                $stop = $cell->getCol() === $this->strips[$previous_idx]->getStartCol() ? $cell->getRow() - 1 : $this->height;
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
                $this->strips[$idx] = $strip;
                $previous_idx = $idx;
            }
        }

        if (!is_null($previous_idx)) {
            $this->strips[$previous_idx]->setStopRow($this->height);
            $this->strips[$previous_idx]->calculateLen();
            $previous_idx = null;
        }

        foreach($this->strips as $strip) {
            $strip->setPossibleValues($this->pvFinder->findValues($strip->getTotal(), $strip->getLen(), []));
        }

        $x = $this->strips;
        $y = 1;
    }

    public function getForProcessing()
    {
        if (!$this->pvFinder) {
            $this->pvFinder = new BuildTables(['number_set' => $this->number_set], null);
        }
        $this->calculateStrips();
        $default_cell = null;
        $height = $this->height + 1;
        $width = $this->width + 1;
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
}
