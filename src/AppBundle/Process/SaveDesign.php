<?php

namespace AppBundle\Process;

class SaveDesign extends BaseGrid
{
    protected
        $name,
        $id,
        $asCopy,
        $saveStructure = true,
        $isUnique = 2, // 0, 1, 2 (not uq, uq, unk)
        $libraryIndex = 0;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);

        if (!empty($this->parameters['id'])) {
            $this->id = $this->parameters['id'];
        }
        if (!empty($this->parameters['name'])) {
            $this->name = $this->parameters['name'];
        }
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }
        if (!empty($this->parameters['height'])) {
            $this->height = $this->parameters['height'];
        }
        if (!empty($this->parameters['width'])) {
            $this->width = $this->parameters['width'];
        }
        $this->asCopy = !empty($this->parameters['asCopy']);
        if (!empty($this->parameters['isUnique'])) {
            $this->isUnique = $this->parameters['isUnique'];
        }
        if (!empty($this->parameters['libraryIndex'])) {
            $this->libraryIndex = $this->parameters['libraryIndex'];
        }
        if (empty($this->height) || empty($this->width)) {
            $this->saveStructure = false;
        }
    }

    protected function execute()
    {
        
        if ($this->saveStructure) {
            if ($this->asCopy) {
                $this->handleCopy();
            }

            $id = $this->id ?: 'null';
            $uqVal = (int)$this->isUnique;
            $timestamp = date('Y-m-d H:i:s');

            $sql = '
            INSERT INTO grids
            (id, name, height, width, is_unique, library_index, updated_at, created_at)
            VALUES
            (' . $id 
                . ', "' . $this->name 
                . '", ' . $this->height 
                . ', ' . $this->width 
                . ', ' . $uqVal 
                . ', ' . $this->libraryIndex 
                . ', "' . $timestamp 
                . '", "' . $timestamp  
                . '")
            ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            height = VALUES(height),
            width = VALUES(width),
            is_unique = VALUES(is_unique),
            library_index = VALUES(library_index),
            updated_at = VALUES(updated_at)';

            $this->exec($sql);
            $id = $this->lastInsertId();
            $this->id = $id;
            if (empty($id)) {
                throw new \Exception('Unable to get last id');
            }

            $sql = '
            DELETE FROM cells
            WHERE grid_id =' . $id;

            $this->exec($sql);

            $insert = [];
            foreach ($this->cells as $cell) {
                if (empty($cell['is_data'])) {
                    if (!empty($cell['display'])) {
                        $insert[] = '(' . $id . ',' . $cell['row'] . ',' . $cell['col'] . ',' . $cell['display'][1] . ',' . $cell['display'][0] . ')';
                    }
                }
            }

            $sql = '
            INSERT INTO cells
            (grid_id, row, col, label_h, label_v)
            VALUES
            ' . implode(',', $insert) .'
            ON DUPLICATE KEY UPDATE
            label_h = VALUES(label_h),
            label_v = VALUES(label_v),
            updated_at = VALUES(updated_at)';

            $this->exec($sql);
        } else {
            $id = $this->id;
        }

        $sql = '
        INSERT IGNORE INTO solutions
        (grid_id)
        VALUES
        (' . $id . ');';

        $this->exec($sql);
        $solutionId = $this->lastInsertId();
        if (empty($solutionId)) {
            $sql = '
        SELECT id FROM solutions
        WHERE grid_id = ' . $id . '
        LIMIT 1;';

            $this->exec($sql);
            $solutionId = $this->fetch($sql)['id'];
            if (empty($solutionId)) {
                throw new \Exception('Unable to get solution id');
            }

            $sql = '
        DELETE FROM solution_choices
        WHERE solution_id =' . $solutionId;

            $this->exec($sql);
        }

        $insert = [];
        foreach ($this->cells as $cell) {
            if (!empty($cell['is_data'])) {
                if (!empty($cell['choices'])) {
                    $insert[] = '(' . $solutionId . ',' . $cell['row'] . ',' . $cell['col'] . ',' . $cell['choices'][0] . ')';
                }
            }
        }

        $sql = '
        INSERT INTO solution_choices
        (solution_id, row, col, choice)
        VALUES
        ' . implode(',', $insert) .'
        ON DUPLICATE KEY UPDATE
        choice = VALUES(choice)';

        $this->exec($sql);
    }

    protected function handleCopy()
    {
        $sql = '
        SELECT count(1) AS ct FROM grids
        WHERE name = "' . $this->name .'"'; 

        $record = $this->fetch($sql);
        if ($record['ct'] > 0) {
            $this->name .= ' (copy)';
        }

        $this->id = null;
    }

    public function getResponse()
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
        ];
    }
}
