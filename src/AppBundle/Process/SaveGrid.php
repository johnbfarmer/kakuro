<?php

namespace AppBundle\Process;

class SaveGrid extends BaseGrid
{
    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['grid_name'])) {
            $this->grid_name = $this->parameters['grid_name'];
        }
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }
    }

    protected function execute()
    {
        $sql = '
        SELECT id FROM grids
        WHERE name = "' . $this->grid_name . '"';
        $grid = $this->fetch($sql);
        if (empty($grid)) {
            throw new \Exception('Bad grid id in save grid');
        }
        $id = $grid['id'];
        $insert = [];
        foreach ($this->cells as $cell) {
            if (!empty($cell['choices'])) {
                $insert[] = '(' . $id . ',' . $cell['row'] . ',' . $cell['col'] . ',"' . implode(',', $cell['choices']) . '")';
            }
        }
        $sql = '
        INSERT INTO saved_choices
        (grid_id, row, col, choices)
        VALUES
        ' . implode(',', $insert) .'
        ON DUPLICATE KEY UPDATE
        choices = VALUES(choices)';
        $this->exec($sql);
    }
}