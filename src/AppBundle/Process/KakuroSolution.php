<?php

namespace AppBundle\Process;

class KakuroSolution extends BaseGrid
{
    protected
        $gridId,
        $result = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);

        if (!empty($this->parameters['id'])) {
            $this->gridId = $this->parameters['id'];
        }
    }

    public function getResult() {
        return $this->result;
    }

    protected function execute()
    {
        $sql = '
        select height, width, name
        from grids
        WHERE id = ' . $this->gridId;

        $record = $this->fetch($sql);
        $h = $record['height'];
        $w = $record['width'];
        $name = $record['name'];
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
            // if ($i < $w || !($i % $w)) {
            if (empty($choices[$i])) {
                $cells[] = ['choices' => [], 'is_editable' => false, 'is_data' => false, 'idx' => $i];
            } else {
                $cells[] = ['choices' => [(int)$choices[$i]], 'is_editable' => true, 'is_data' => true, 'idx' => $i];
            }
        }

        $this->result['height'] = $h;
        $this->result['width'] = $w;
        $this->result['cells'] = $cells;   
        $this->result['name'] = $name;   
    }
}
