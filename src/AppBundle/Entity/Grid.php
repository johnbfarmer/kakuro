<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

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
     */
    private $cells;

    public function __construct()
    {
        $this->cells = new ArrayCollection();
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

    public function getForApi()
    {
        $default_cell = [
            'display' => null,
            'is_data' => true,
            'choices' => [],
        ];
        $cells = array_fill(0, $this->width * $this->height, $default_cell);
        foreach ($this->cells as $cell) {
            $a = $cell->getForApi($this->width);
            $cells[$a['idx']] = $a;
        } 

        return [
            'height' => $this->height,
            'width' => $this->width,
            'cells' => array_values($cells),
        ];
    }
}
