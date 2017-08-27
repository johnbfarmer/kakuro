<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="cells")
 */
class Cell
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $gridId;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $row = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $col = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $label_h = 0;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $label_v = 0;

    /**
     * @ORM\ManyToOne(targetEntity="Grid", inversedBy="cells", cascade={"persist"})
     * @ORM\JoinColumn(name="grid_id", referencedColumnName="id")
     */
    private $grid;

    private $choices = []; // for UI
    private $possibleValues = [];
    private $dataCell = false;
    private $strips = ['h' => null, 'v' => null];
    private $idx;
    private $choice; // for builder

    public function getId()
    {
        return $this->id;
    }

    public function setGridId($gridId)
    {
        $this->gridId = $gridId;

        return $this;
    }

    public function getGridId()
    {
        return $this->gridId;
    }

    public function setGrid($grid)
    {
        $this->grid = $grid;

        return $this;
    }

    public function getGrid()
    {
        return $this->grid;
    }

    public function setRow($row)
    {
        $this->row = $row;

        return $this;
    }

    public function getRow()
    {
        return $this->row;
    }

    public function setCol($col)
    {
        $this->col = $col;

        return $this;
    }

    public function getCol()
    {
        return $this->col;
    }

    public function setLabelH($labelH)
    {
        $this->label_h = $labelH;

        return $this;
    }

    public function getLabelH()
    {
        return $this->label_h;
    }

    public function setLabelV($labelV)
    {
        $this->label_v = $labelV;

        return $this;
    }

    public function getLabelV()
    {
        return $this->label_v;
    }

    public function setChoices($choices)
    {
        $this->choices = $choices;

        return $this;
    }

    public function getChoices($use_pv = true)
    {
        return $this->choices;
    }

    public function getStrips()
    {
        return $this->strips;
    }

    public function setStripH($idx)
    {
        $this->strips['h'] = $idx;

        return $this;
    }

    public function getStripH()
    {
        return $this->strips['h'];
    }

    public function setStripV($idx)
    {
        $this->strips['v'] = $idx;

        return $this;
    }

    public function getStripV()
    {
        return $this->strips['v'];
    }

    public function setDataCell($dataCell)
    {
        $this->dataCell = $dataCell;

        return $this;
    }

    public function isDataCell()
    {
        return $this->dataCell;
    }

    public function getPossibleValues()
    {
        return $this->possibleValues;
    }

    public function setChoice($choice)
    {
        $this->choice = $choice;

        return $this;
    }

    public function getChoice()
    {
        return $this->choice;
    }

    public function getStripObjects()
    {
        $gridStrips = $this->grid->getStrips();
        $strips = new ArrayCollection();
        $strips->add($gridStrips[$this->strips['h']]);
        $strips->add($gridStrips[$this->strips['v']]);
        return $strips;
    }

    public function calculateRowAndCol()
    {
        $width = $this->grid->getWidth();
        if ($this->idx) {
            $this->row = floor($idx / $width);
            $this->col = $width % $idx;
        }

        return $this;
    }

    public function calculateIdx()
    {
        $this->idx = $this->row * $this->grid->getWidth() + $this->col;
        return $this;
    }

    public function setIdx($idx)
    {
        $this->idx = $idx;
        return $this;
    }

    public function getIdx()
    {
        return $this->idx;
    }

    public function setLocation($i, $j)
    {
        $this->setRow($i);
        $this->setCol($j);
        return $this->calculateIdx();
    }

    public function calculatePossibleValues()
    {
        if ($this->isDataCell()) {
            $gridStrips = $this->grid->getStrips();
            $stripH = $gridStrips[$this->strips['h']];
            $stripV = $gridStrips[$this->strips['v']];
            $pv = array_values(array_intersect($stripH->getPossibleValues(), $stripV->getPossibleValues()));
            sort($pv);
            $this->possibleValues = $pv;
        }
    }

    public function getForApi($width)
    {
        return [
            'display' => [(int)$this->label_v, (int)$this->label_h],
            'is_data' => false,
            'idx' => $this->row * $width + $this->col,
            'row' => $this->row,
            'col' => $this->col,
        ];
    }

    public function dump()
    {
        return '(' . $this->row.','.$this->col.'), '.json_encode($this->choices);
    }
}
