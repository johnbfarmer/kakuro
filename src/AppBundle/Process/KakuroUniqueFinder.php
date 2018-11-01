<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class KakuroUniqueFinder extends BaseKakuro
{
    protected
        $width,
        $height,
        $grid,
        $limit,
        $gridsTested = 0,
        $startIdx,
        $showNext,
        $libraryIndex = 1,
        $continue,
        $output;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->height = $parameters['height'] + 1;
        $this->width = $parameters['width'] + 1;
        $this->limit = isset($parameters['limit']) ? $parameters['limit'] : null;
        $this->startIdx = isset($parameters['start-index']) ? $parameters['start-index'] : null;
        $this->showNext = !empty($parameters['show-next']);
        $this->continue = !empty($parameters['continue']);
        $this->output = $parameters['output'];
    }

    protected function execute()
    {
        if (($this->height -1) * ($this->width - 1) > 9) {
            $this->write('This is for finding all unique kakuros for a given shape. max LXW I can try is 9');
            return false;
        }

        $this->grid = $this->getGrid();
// $this->draw();exit;
        $this->runRoutine();
    }

    protected function runRoutine()
    {
        // all grids, set up, print if uq
        while ($this->grid) {
            $this->nextGrid();
            if (!$this->grid) {
                break;
            }
            if (!$this->calculateSums()) {
                continue;
            }
            if ($this->showNext) {
                $this->draw();
                break;
            }

            $tester = KakuroUniquenessTester::autoExecute([
                'uiChoices' => $this->grid,
                'height' => $this->height,
                'width' => $this->width,
            ]);

            $isUnique = $tester->getApiResponse()['reachedProbeLimit']
                ? 2
                : ($tester->getApiResponse()['hasUniqueSolution'] ? 1 : 0);

            $this->store($isUnique);
            $this->libraryIndex++;
            $this->draw();

            if ($this->limit !== null && ++$this->gridsTested >= $this->limit) {
                $this->log('Limit Reached', true);
                $this->grid = null;
            }

            $this->nextGrid();
        }
    }

    protected function nextGrid()
    {
        // next one in the alphabet
        $cellIdx = $this->width * $this->height - 1; // make dynamic, last cell
        $this->increment($cellIdx);

        if (!$this->grid) {
            return;
        }

        // if it doesn't follow the rules, next
        if (!$this->gridProperlyAligned()) {
            $this->nextGrid();
        }
    }

    protected function gridProperlyAligned()
    {
        // row 1 must be ordered
        $i = $this->width + 1; // first data cell
        while ($i < 2 * $this->width - 1) {
            if ($this->grid[$i]['choices'][0] >= $this->grid[$i + 1]['choices'][0]) {
                return false;
            }
            $i++;
        }

        // col 1 must be ordered
        $i = $this->width + 1;
        while ($i <= ($this->height - 1) * ($this->width)) {
            if ($this->grid[$i]['choices'][0] >= $this->grid[$i + $this->width]['choices'][0]) {
                return false;
            }
            $i += $this->width;
        }

        return true;
    }

    protected function increment($cellIdx)
    {
        // for recursion control
        if ($cellIdx < 1) {
            $this->grid = null;
            return;
        }

        if (!$this->grid[$cellIdx]['is_data']) {
            $this->increment($cellIdx - 1);
            return;
        }

        if ($this->grid[$cellIdx]['choices'][0] >= 9) {
            $this->grid[$cellIdx]['choices'][0] = 1;
            $this->increment($cellIdx - 1);
        } else {
            $this->grid[$cellIdx]['choices'][0]++;
        }
    }

    protected function getGridPresets() {
        if ($this->continue) {
            $this->startIdx = $this->lastLibraryIndex();
        }
        if ($this->startIdx) {
            $this->libraryIndex = $this->startIdx + 1;
            $solution = KakuroSolution::autoExecute(['libraryIndex' => $this->startIdx, 'height' => $this->height, 'width' => $this->width]);
            $cells = $solution->getResult()['cells'];
// $this->log(json_encode($cells));exit;
            if (!empty($cells)) {
                $presets = [];
                foreach($cells as $cell) {
                    if (!$cell['is_data']) {
                        $presets[] = 0;
                    } else {
                        $presets[] = $cell['choices'][0];
                    }
                }

                return $presets;
            }
        }

        return [];
    }

    protected function lastLibraryIndex()
    {
        $sql = '
        select MAX(library_index) AS idx
        from grids
        WHERE height = ' . $this->height . '
        AND width = ' . $this->width;

        $record = $this->fetch($sql);

        return $record['idx'];
    }

    protected function getGrid()
    {
        $presets = $this->getGridPresets();
// $this->log(json_encode($presets));exit;
        $grid = [];
        for ($i = 0; $i < $this->height * $this->width; $i++) {
            $r = (int)floor($i / $this->width);
            $c = $i % $this->width;
            if ($i < $this->width || !($i % $this->width)) {
                $grid[] = [
                    'choices' => [], 
                    'is_editable' => false, 
                    'is_data' => false, 
                    'idx' => $i, 
                    'col' => $c,
                    'row' => $r, 
                    'display' => [0,0],
                    'strips' => [],
                ];
            } else {
                $val = $c == 1 ? $r : ($r == 1 ? $c : 1);
                if (!empty($presets)) {
                    $val = $presets[$i];
                }

                $grid[] = [
                    'choices' => [$val], 
                    'is_editable' => true, 
                    'is_data' => true, 
                    'idx' => $i, 
                    'col' => $c,
                    'row' => $r, 
                    'display' => [0,0], 
                    'strips' => [
                        'v' => '1_' . $c . '_v',
                        'h' => $r . '_1_h',
                    ],
                ];
            }
        }

        return $grid;
    }

    protected function calculateSums()
    {
        if (!$this->grid) {
            return true;
        }

        // get total and check for repeats
        foreach ($this->grid as $idx => $cell) {
            if (!$cell['is_data']) {
                $sum = 0;
                $valuesArray = [];
                $i = $idx + $this->width;
                // vertical
                while (!empty($this->grid[$i]) && $this->grid[$i]['is_data']) {
                    $val = $this->grid[$i]['choices'][0];
                    if (in_array($val, $valuesArray)) {
                        return false;
                    }
                    $valuesArray[] = $val;
                    $sum += $val;
                    $i += $this->width;

                }
                $this->grid[$idx]['display'][0] = $sum;
                $sum = 0;
                $valuesArray = [];
                $i = $idx + 1;
                // horizontal
                while (!empty($this->grid[$i]) && $this->grid[$i]['is_data']) {
                    $val = $this->grid[$i]['choices'][0];
                    if (in_array($val, $valuesArray)) {
                        return false;
                    }
                    $valuesArray[] = $val;
                    $sum += $val;
                    $i++;
                }
                $this->grid[$idx]['display'][1] = $sum;
            }
        }

        // the grid is valid, return true
        return true;
    }

    protected function store($isUnique)
    {
        $name = $this->getName();
        $parameters = [
            'name' => $name, 
            'cells' => $this->grid, 
            'height' => $this->height, 
            'width' => $this->width, 
            'isUnique' => $isUnique,
            'libraryIndex' => $this->libraryIndex,
        ];
        SaveDesign::autoExecute($parameters);
    }

    protected function getName()
    {
        return 'lib_' . ($this->height - 1) . 'x' . ($this->width - 1) . '_' . substr(md5(serialize($this->grid)), 0, 7);
    }

    protected function write($msg)
    {
        $this->output->writeln($msg);
    }

    protected function draw()
    {
        $line = '';
        foreach ($this->grid as $cell) {
            if ($cell['is_data']) {
                $line .= $cell['choices'][0] . ' ';
            }

            if ($cell['col'] >= $this->width - 1) {
                $this->write($line);
                $line = '';
            }
        }
    }
}
