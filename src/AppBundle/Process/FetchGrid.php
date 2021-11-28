<?php

namespace AppBundle\Process;

class FetchGrid extends BaseGrid
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
    }

    protected function execute()
    {
        $sql = 'SELECT name, cell_key, height, width FROM grids WHERE id = ?';
        $rec = $this->fetch($sql, false, [$this->id]);
        $this->name = $rec['name'];
        $this->height = (int)$rec['height'];
        $this->width = (int)$rec['width'];
        if (empty($rec['cell_key'])) {
            $sql = 'SELECT `row`, col, concat(label_h, "/", label_v) AS display FROM cells WHERE grid_id = ?';
            $cells = $this->fetchAll($sql, false, [$this->id]);
            $cellKeyArr = [];
            $a = [];
            $h = $this->height;     
            $w = $this->width;

            foreach ($cells as $cell) {
                $r = (int)$cell['row'];
                $c = (int)$cell['col'];
                if (!isset($a[$r])) {
                    $a[$r] = [];
                }
                $a[$r][$c] = $cell['display'];
            }

            for ($i=0;$i<$h;$i++) {
                for ($j=0;$j<$w;$j++) {
                    $cellKeyArr[] = isset($a[$i][$j]) ? $a[$i][$j] : '';
                }
            }

            $cellKey = implode(',', $cellKeyArr);
            $sql = 'UPDATE grids SET cell_key = ? WHERE id = ?';
            $this->exec($sql, false, [$cellKey, $this->id]);
        } else {
            $cellKey = $rec['cell_key'];
        }
        $cellKeyArr = explode(',', $cellKey);
        $cells = [];
        foreach ($cellKeyArr as $idx => $cell) {
            $this->log($cell);
            $isData = empty($cell);
            $label = !$isData ? explode('/', $cell) : null;
            $labelV = !$isData ? $label[0] : null;
            $labelH = !$isData ? $label[1] : null;
            $cells[] = [
                'display' => $isData ? null : [empty($labelH) ? '' : $labelH, empty($labelV) ? '' : $labelV],
                'is_data' => $isData,
                'idx' => $idx,
                'row' => floor($idx / $this->width),
                'col' => $idx % $this->width,
                'choices' => [],
            ];
        }

        $this->response = [
            'name' => $this->name,
            'height' => $this->height,
            'width' => $this->width,
            'cells' => $cells,
        ];
    }

    public function getResponse()
    {
        return $this->response;
    }
}
