<?php

namespace AppBundle\Entity;

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
     * @ORM\Column(type="integer")
     */
    private $row = 0;
    
    /**
     * @ORM\Column(type="integer")
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
    protected $grid;

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

    public function getForApi($width)
    {
        return [
            'display' => [(int)$this->label_v, (int)$this->label_h],
            'is_data' => false,
            'idx' => $this->row * $width + $this->col,
        ];
    }
}
