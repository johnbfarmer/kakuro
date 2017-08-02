<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="saved_choices")
 */
class SavedChoice
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
     * @ORM\Column(type="string", length=25)
     */
    private $choices;
    
    /**
     * @ORM\Column(name="saved_grid_id", type="integer", options={"unsigned"=true})
     */
    private $savedGridId = 0;

    /**
     * @ORM\ManyToOne(targetEntity="SavedGrid", inversedBy="choices", cascade={"persist"})
     * @ORM\JoinColumn(name="saved_grid_id", referencedColumnName="id")
     */
    private $savedGrid;

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

    public function setChoices($choices)
    {
        $this->choices = $choices;

        return $this;
    }

    public function getChoices()
    {
        return $this->choices;
    }

    public function setSavedGridId($savedGridId)
    {
        $this->savedGridId = $savedGridId;

        return $this;
    }

    public function getSavedGridId()
    {
        return $this->savedGridId;
    }

    public function setSavedGrid(SavedGrid $savedGrid = null)
    {
        $this->savedGrid = $savedGrid;

        return $this;
    }

    public function getSavedGrid()
    {
        return $this->savedGrid;
    }
}
