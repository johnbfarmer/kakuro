<?php

namespace AppBundle\Process;

class KakuroSolution extends BaseGrid
{
    protected
        $gridId,
        $libraryIndex,
        $height,
        $width,
        $result = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);

        if (!empty($this->parameters['id'])) {
            $this->gridId = $this->parameters['id'];
        }

        if (!empty($this->parameters['libraryIndex'])) {
            $this->libraryIndex = $this->parameters['libraryIndex'];
        }

        if (!empty($this->parameters['height'])) {
            $this->height = $this->parameters['height'];
        }

        if (!empty($this->parameters['width'])) {
            $this->width = $this->parameters['width'];
        }
    }

    public function getGridIdByLibrarayIndex() {
        $sql = '
        select id
        from grids
        WHERE library_index = ' . $this->libraryIndex . '
        AND height = ' . $this->height . '
        AND width = ' . $this->width;

        $record = $this->fetch($sql, true);

        return $record['id'];
    }

    public function getResult() {
        return $this->result;
    }

    protected function execute()
    {
        if ($this->libraryIndex) {
            $this->gridId = $this->getGridIdByLibrarayIndex();
        }

        $sql = '
        select height, width, name, solution_key
        from grids
        WHERE id = ' . $this->gridId;

        $record = $this->fetch($sql);
        $h = $record['height'];
        $w = $record['width'];
        $name = $record['name'];
        $solutionString = $record['solution_key'];
        $cells = [];
        
        $sql = '
        select C.row, C.col, C.choice
        from solution_choices C
        inner join solutions S ON S.id = C.solution_id
        inner join grids G ON G.id = S.grid_id
        WHERE G.id = ' . $this->gridId;

        $records = $this->fetchAll($sql);

        foreach ($records as $record) {
            $idx = $record['row'] * $w + $record['col'];
            $choices[$idx] = $record['choice'];
        }

        for ($i=0; $i<$w*$h; $i++) {
            $isEditable = $i > $w && $i % $w;
            if (empty($choices[$i])) {
                $cells[] = ['choices' => [], 'is_editable' => $isEditable, 'is_data' => false, 'idx' => $i];
            } else {
                $cells[] = ['choices' => [(int)$choices[$i]], 'is_editable' => $isEditable, 'is_data' => true, 'idx' => $i];
            }
        }

        $this->result['height'] = $h;
        $this->result['width'] = $w;
        $this->result['cells'] = $cells;   
        $this->result['name'] = $name;   
    }
}
