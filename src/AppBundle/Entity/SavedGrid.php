<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table(name="saved_grids")
 */
class SavedGrid
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;
    
    /**
     * @ORM\Column(name="grid_id", type="integer")
     */
    private $gridId = 0;

    /**
     * @ORM\ManyToOne(targetEntity="Grid", cascade={"persist"})
     * @ORM\JoinColumn(name="grid_id", referencedColumnName="id")
     */
    private $grid;

    /**
     * @ORM\OneToMany(targetEntity="SavedChoice", mappedBy="savedGrid", orphanRemoval=true, cascade={"persist"})
     * @ORM\OrderBy({"row" = "ASC", "col" = "ASC"})
     */
    private $choices;

    public function __construct()
    {
        $this->choices = new ArrayCollection();
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

    public function setGrid($grid)
    {
        $this->grid = $grid;

        return $this;
    }

    public function getGrid()
    {
        return $this->grid;
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

    public function getForApi()
    {
        $grid = $this->grid->getForApi();
        foreach ($this->choices as $savedChoice) {
            $idx = $savedChoice->getRow() * ($this->grid->getWidth() + 0) + $savedChoice->getCol();
            $grid['cells'][$idx]['choices'] = explode(',', $savedChoice->getChoices());
        }

        $grid['name'] = $this->name;
\AppBundle\Helper\GridHelper::log($grid);

        return $grid;
    }
}
