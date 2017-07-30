<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class LoadSavedGrid extends BaseGrid
{
    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['grid_name'])) {
            $this->grid_name = $this->parameters['grid_name'];
        }
        if (!empty($this->parameters['saved_grid_name'])) {
            $this->saved_grid_name = $this->parameters['saved_grid_name'];
        }
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }
    }

    protected function execute()
    {
        $sql = '
        SELECT id FROM grids
        WHERE saved_grid_name = "' . $this->grid_name . '"';
        $grid = $this->fetch($sql);
        if (empty($grid)) {
            throw new \Exception('Bad grid id in save grid');
        }
        $id = $grid['id'];
        $sql = '
        SELECT * FROM saved_choices
        WHERE grid_id = ' . $id;
        $cell_choices = $this->fetchAll($sql);
        $grid = GridHelper::getGrid($this->grid_name);
        $height = $grid['height'];
        $width = $grid['width'];
        foreach ($cell_choices as $choices) {
            $row = $choices['row'];
            $col = $choices['col'];
            foreach ($grid['cells'] as $idx => $cell) {
                $row = floor($idx/$width);
                $col = $idx % $width;
                if ($choices['row'] == $row && $choices['col'] == $col) {
                    $grid['cells'][$idx]['choices'] = array_map('intval', explode(',', $choices['choices']));
                }
            }
        }

        $this->grid = $grid;
    }

    public function getGrid()
    {
        return $this->grid;
    }
}