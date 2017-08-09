<?php

namespace AppBundle\Process;

class SaveGrid extends BaseGrid
{
    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['id'])) {
            $this->grid_id = $this->parameters['id'];
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
        SELECT id FROM saved_grids
        WHERE name = "' . $this->saved_grid_name . '"';
        $saved_grid = $this->fetch($sql);

        if (empty($saved_grid)) {
            $grid_id = $this->grid_id;
            $sql = '
        INSERT INTO saved_grids
        (grid_id, name)
        VALUES
        (' . $grid_id . ', "' . $this->saved_grid_name . '")';
            $this->exec($sql);
            $id = $this->lastInsertId();
            if (empty($id)) {
                throw new \Exception('Unable to get last id');
            }
        } else {
            $id = $saved_grid['id'];
        }

        $sql = '
        DELETE FROM saved_choices
        WHERE saved_grid_id =' . $id;
        $this->exec($sql);

        $insert = [];
        foreach ($this->cells as $cell) {
            if (!empty($cell['choices'])) {
                $insert[] = '(' . $id . ',' . $cell['row'] . ',' . $cell['col'] . ',"' . implode(',', $cell['choices']) . '")';
            }
        }

        $sql = '
        INSERT INTO saved_choices
        (saved_grid_id, row, col, choices)
        VALUES
        ' . implode(',', $insert) .'
        ON DUPLICATE KEY UPDATE
        choices = VALUES(choices)';
        $this->exec($sql);
    }
}