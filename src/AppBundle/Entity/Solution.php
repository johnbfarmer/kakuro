<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="solutions")
 */
class Solution
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
    private $row;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $col;
    
    /**
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $choice;
    
    /**
     * @ORM\Column(name="grid_id", type="integer", options={"unsigned"=true})
     */
    private $gridId = 0;

    /**
     * @ORM\ManyToOne(targetEntity="Grid", inversedBy="solutions", cascade={"persist"})
     * @ORM\JoinColumn(name="grid_id", referencedColumnName="id")
     */
    private $grid;

    public function getId()
    {
        return $this->id;
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

    public function setChoice($choice)
    {
        $this->choice = $choice;

        return $this;
    }

    public function getChoice()
    {
        return $this->choice;
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

    public function setGrid($grid = null)
    {
        $this->grid = $grid;

        return $this;
    }

    public function getGrid()
    {
        return $this->grid;
    }
}
