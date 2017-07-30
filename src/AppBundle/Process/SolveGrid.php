<?php

namespace AppBundle\Process;

use AppBundle\Helper\GridHelper;

class SolveGrid extends BaseGrid
{
    protected 
        $changed = false,
        $current_path = [],
        $problem_strips = [],
        $solutions_desired = 2,
        $max_paths = 25,
        $cache_size = 1000,
        $grid_cache = [],
        $saved_grids = [],
        $avoid_picks = [],
        $use_advanced_reduction = true,
        $use_advanced_threshold = 50,
        $simple_reduction,
        $reduce_only,
        $last_output_time;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['grid_name'])) {
            $this->grid_name = $this->parameters['grid_name'];
        } else {
            $this->sums = !empty($this->parameters['sums']) ? $this->parameters['sums'] : [];
            $this->hsums = $this->sums['h'];
            $this->vsums = $this->sums['v'];
        }
        if (!empty($this->parameters['solutions'])) {
            $this->solutions_desired = $this->parameters['solutions'];
        }
        if (!empty($this->parameters['batch-size'])) {
            $this->max_paths = $this->parameters['batch-size'];
        }
        if (!empty($this->parameters['buffer-size'])) {
            $this->cache_size = $this->parameters['buffer-size'];
        }
        if (!empty($this->parameters['time-limit'])) {
            $this->time_limit = $this->parameters['time-limit'];
        }
        if (!empty($this->parameters['cells'])) {
            $this->cells = $this->parameters['cells'];
        }

        $this->simple_reduction = !empty($this->parameters['simple_reduction']);
        $this->reduce_only = !empty($this->parameters['reduce_only']);
        $this->start_time = microtime(true);
        $this->last_output_time = $this->start_time;
    }

    protected function execute()
    {
        parent::execute();
        if ($this->grid_name) {
            // $this->readInputFile();
            $this->readInputFromDb();
            // $this->log('File read. Alternatively call with args ' . json_encode($this->hsums). ' ' . json_encode($this->vsums));
        }
        $this->timeCheck();
        $this->log("VALIDATING", $this->debug);
        $this->validate();
        $this->log("CALCULATING STRIPS", $this->debug);
        $this->init();
        $this->openGridInBrowser();

        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if (!$this->isBlank($i,$j)) {
                    $pv = $this->possibleValues($i, $j);
                    if (empty($pv)) {
                        $this->display($this->grid);
                        $this->log($this->displaySolutions(), true);
                        return;
                    }

                    if (!empty($this->cells)) {
                        $idx = ($i+1) * ($this->width + 1) + ($j+1);
                        if (!empty($this->cells[$idx]['choices'])) {
                            $xxx = $this->cells[$idx]['choices'];
                            $pv = array_values(array_intersect($xxx, $pv));
                        }
                    }
                    $this->grid['cells'][$i][$j]['choices'] = $pv;
                }
            }
        }

        $this->timeCheck();
        $this->done = false;
        $this->ctr = 0;
        $this->grid['path'] = [];
        $this->grid['key'] = $this->getPathKey($this->grid['path']);
        $this->grid['changed_strips'] = [];
        $this->display($this->grid);
        $this->log("DOING INITIAL REDUCTION", $this->debug);
        $this->grid = $this->reduceGrid($this->grid, [], !$this->simple_reduction);
        $this->timeCheck();
        // if (empty($this->grid)) {
            // throw new \Exception("Initial Reduction fails");
        // }

        if ($this->reduce_only) {
            return;
        }

        $this->paths[] = $this->grid;
        $this->display($this->grid);

        $this->routine();
        $this->log($this->displaySolutions(), true);
    }

    protected function init()
    {
        // correctly populate h/v sums, size, and grid
        foreach ($this->hsums as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($cell === 0) {
                    $this->grid['cells'][$i][$j] = ['blank' => true];
                } else {
                    $this->grid['cells'][$i][$j] = ['blank' => false];
                    $this->nonempty_cell_count++;
                }
            }
        }

        $strips_index = 0;
        foreach ($this->grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if (!$this->isBlank($i,$j)) {
                    if ($j > 0 && !$this->isBlank($i,$j-1)) {
                            $hidx = $this->grid['cells'][$i][$j-1]['strip_indices']['h'];
                            $hstrip = $this->strips[$hidx];
                    } else {
                        $hstrip = GridHelper::getStrip($i, $j, $this->grid['cells'], 'h', $this->hsums[$i][$j]);
                        $hidx = $strips_index++;
                        $hstrip['idx'] = $hidx;
                        $this->strips[$hidx] = $hstrip;
                    }
                    if ($i > 0 && !$this->isBlank($i-1,$j)) {
                            $vidx = $this->grid['cells'][$i-1][$j]['strip_indices']['v'];
                            $vstrip = $this->strips[$vidx];
                    } else {
                        $vstrip = GridHelper::getStrip($i, $j, $this->grid['cells'], 'v', $this->vsums[$i][$j]);
                        $vidx = $strips_index++;
                        $vstrip['idx'] = $vidx;
                        $this->strips[$vidx] = $vstrip;
                    }

                    $this->grid['cells'][$i][$j]['strip_indices'] = [
                        'h' => $hidx,
                        'v' => $vidx,
                    ];
                    $this->grid['cells'][$i][$j]['choices'] = [];
                    $this->grid['cells'][$i][$j]['i'] = $i;
                    $this->grid['cells'][$i][$j]['j'] = $j;
                    $this->grid['cells'][$i][$j]['use_advanced_reduction'] = true;
                }
            }
        }
    }

    public function getApiResponse()
    {
        $cells = [];
        foreach ($this->cells as $idx => $cell) {
            if (!array_key_exists('row', $cell)) {
                throw new \Exception("No 'row' idx: $idx" . json_encode($cell));
            }
            $i = $cell['row'] - 1;
            $j = $cell['col'] - 1;
            if (!empty($this->grid['cells'][$i][$j]['choices'])) {
                $cell['choices'] = array_values($this->grid['cells'][$i][$j]['choices']);
                $cell['display'] = implode('', $cell['choices']);
            }
            $cells[] = $cell;
        }
// $this->log($cells);
        $grid = ['cells' => $cells, 'error' => false];
        if (!empty($this->problem_strips)) {
            $grid['error'] = true;
            $grid['message'] = 'problem reducing';
        }
        return $grid;
    }

    protected function pickTargetCell($grid)
    {
        // of the cells with fewest choices, pick the one with the smallest strips
        $best_count = 100;
        $best_cell = null;
        $best_strip_count = 100;
        $candidates = [];
        $pool = $grid['cells'];

        // $avoid_picks = $grid['path'];

        foreach ($pool as $i => $row) {
            foreach ($row as $j => $cell) {
                if (!$this->isBlank($i,$j)) {
                    // if (!empty($avoid_picks)) {
                        foreach ($this->current_path as $path_cell) {
                            if ($i == $path_cell['i'] && $j == $path_cell['j']) {
                                continue 2;
                            }
                        }
                    // }
                    $ct = count($cell['choices']);
                    if ($ct > 1 && $ct <= $best_count) { // 1's are handled on the fly
                        if ($ct < $best_count) {
                            $candidates = [];
                        }
                        $best_count = $ct;
                        $candidates[] = $cell;
                    }
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $strip_count_h = $this->strips[$candidate['strip_indices']['h']]['len'];
            $strip_count_v = $this->strips[$candidate['strip_indices']['v']]['len'];
            $sum = $strip_count_h + $strip_count_v;
            if ($sum < $best_strip_count) {
                 $best_strip_count = $sum;
                 $best_cell = $candidate;
            }
        }

        // array_push($this->current_path, [$best_cell['i'], $best_cell['j']]);
        // array_push($this->current_path, $best_cell);
// var_dump($this->current_path);exit;

        return $best_cell;
    }

    protected function getMyStripCells($i, $j, $sgx)
    {
        $cells = [];
        $cell = $sgx['cells'][$i][$j];

        $strips = [
            'h' => $this->strips[$cell['strip_indices']['h']],
            'v' => $this->strips[$cell['strip_indices']['v']],
        ];

        foreach ($strips as $strip) {
            $len = $strip['len'];
            $dir = $strip['dir'];
            if ($dir === 'h') {
                $start = $strip['start'][1];
                $row = $strip['start'][0];
            } else {
                $start = $strip['start'][0];
                $col = $strip['start'][1];
            }
            for ($k = $start; $k < $start + $strip['len']; $k++) {
                $c_col = $dir === 'h' ? $k : $col; 
                $c_row = $dir === 'v' ? $k : $row; 
                $cell = $sgx['cells'][$c_row][$c_col];
                if (!isset($cells[$c_row][$c_col])) {
                    $cells[$c_row][$c_col] = $cell;
                }
            }
        }

        return $cells;
    }

    protected function difficultyScore($grid)
    {
        if (empty($grid['cells'])) {
            return 100;
        }
        $ct = 0;
        foreach ($grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if (!$this->isBlank($i,$j)) {
                    $ct += count($cell['choices']) - 1;
                }
            }
        }

        if (empty($this->nonempty_cell_count)) {
            return 100;
        }
        if (count($this->number_set) <2) {
            return 100;
        }

        // this will be 0 if all cells have only 1 pv; 100 if whole ns in each cell
        return round(100 * $ct / ($this->nonempty_cell_count * (count($this->number_set) - 1)), 2);
    }

    protected function checkSolution($grid)
    {
        if (!$this->solutionExists($grid) && $this->isSolution($grid)) {
            $this->addSolution($grid);
            return true;
        }

        return false;
    }

    protected function checkStrip($strip, $sgx)
    {
        $total = $strip['total'];
        $len = $strip['len'];
        $dir = $strip['dir'];
        $used_numbers = [];
        if ($dir === 'h') {
            $start = $strip['start'][1];
            $row = $strip['start'][0];
        } else {
            $start = $strip['start'][0];
            $col = $strip['start'][1];
        }

        for ($k = $start; $k < $start + $len; $k++) {
            $i = $dir === 'v' ? $k : $row; 
            $j = $dir === 'h' ? $k : $col; 
            $cell = $sgx['cells'][$i][$j];
            if (count($cell['choices']) === 1) {
                $num = current($cell['choices']);
                $total -= $num;
                if (!in_array($num, $used_numbers)) {
                    $used_numbers[] = $num;
                } else {
                    $this->log("strip with $i $j has repeated $num");
                    return false;
                }
            } else {
                $this->log("wrong # choices $i $j");
                return false;
            }
        }

        if ($total !== 0) {
            $this->log("strip total $i $j off by $total");
            return false;
        }

        return true;
    }

    protected function routine()
    {
        while (!empty($this->paths)) {
            $this->log("PASS #" . ++$this->ctr, $this->debug);
            $new_paths = [];
            $cell = null;
            $grid = $this->paths[0];
            if ($this->ctr >= 76 && $this->ctr <= 78) {
                $y = json_encode($grid['cells'][3][1]);
                $x = json_encode($grid['cells'][4][1]);
                $this->log($y);
                $this->log($x);
            }
            $this->log('GRID ' . $grid['key']);
            $this->log('DIFFICULTY ' . $grid['difficulty']);
            if (is_null($cell)) {
                $cell = $this->pickTargetCell($grid); // yeah, favoring the first one. improve with data
                if (is_null($cell)) {
                    return !empty(count($this->solutions));
                }
                $i = $cell['i'];
                $j = $cell['j'];
                // $this->avoid_picks[] = [$i, $j];
            }

            $strip_ids = $cell['strip_indices'];
            $choices = $grid['cells'][$i][$j]['choices'];
            $choices_to_remove = [];
            $best_new_difficulty = 100;

            foreach ($choices as $idx => $choice) {
                $g = $grid;
                // $c = $cell;
                $cell['choice'] = $choice;
                $segment = $i . '_' . $j . '_' . $choice;
                $this->log("choice $segment");
                if (in_array($segment, $g['path'])) {
                    throw new \Exception("jbf -- Error Processing Request", 1);
                }
                $g['path'][] = $segment;
                $g['cells'][$i][$j]['choices'] = [$choice];
                $path = $this->getPathKey($g['path']);
                $g['key'] = $path;
                $new_grid = $this->reduceGrid($g, $strip_ids, true);
                if (empty($new_grid)) {
                    $choices_to_remove[] = $choice;
                    continue;
                }
                $new_paths[$path] = $new_grid;
                if ($new_grid['difficulty'] < $best_new_difficulty) {
                    $best_new_difficulty = $new_grid['difficulty'];
                    $this->paths[0] = $new_grid;
                    $best_cell = $cell;
                }

                $this->display($new_paths[$path]);
                if (!$this->timeCheck()) {
                    return true;
                }
            }

            if (empty($new_paths)) {
                $this->paths[0] = $this->removePreviousSelection($grid);
            } else {
                $this->unsetValues($choices, $choices_to_remove);
                $best_cell['choices'] = $choices;
                array_push($this->current_path, $best_cell);
                $this->paths[0]['cells'][$i][$j]['choices'] = $choices;
            }
        }
    }

    protected function removePreviousSelection($grid)
    {
        if (empty($grid['path'])) {
            throw new \Exception ('Handle this...');
        }
        $cell = array_pop($this->current_path);
        $i = $cell['i'];
        $j = $cell['j'];
        $choice = $cell['choice'];
        $key = $this->getCurrentPathKey();
$this->log("pop $i $j $choice path-size: ".count($this->current_path));
        // if (isset($this->saved_grids[$key])) {
        //     $grid = $this->saved_grids[$key];
        // } else {
        // }
        $this->log("remove choice $i $j $choice");
        $g = $this->buildGridFromPath(explode('__', $key));
        if (!$g) {
            return $this->removePreviousSelection($grid);
        }
        $choices = $grid['cells'][$i][$j]['choices'];
        $this->unsetValue($choices, $choice);
        if (empty($choices)) {
            return $this->removePreviousSelection($grid);
        }
        $grid['cells'][$i][$j]['choices'] = $choices;
        // unset($this->saved_grids[$key]);
        $g = $this->reduceGrid($grid, [], true, true);
        if(!$g) {
            return $this->removePreviousSelection($grid);
        }
        return $g;
    }

    protected function buildGridFromPath($path)
    {
        // $this->log('bbk coming soon');exit;
        $grid = $this->grid;
        foreach ($path as $segment) {
            $exp_seg = explode('_', $segment);
            $i = $exp_seg[0];
            $j = $exp_seg[1];
            $choice = $exp_seg[2];
            $grid['cells'][$i][$j]['choices'] = [$choice];
            $grid['path'][] = $segment;
        }
        $grid = $this->reduceGrid($grid, [], true, true);
        return $grid;
    }

    protected function store($grids)
    {
        if (empty($grids)) {
            return;
        }

        $number_stored = 0;

        foreach ($grids as $idx => $grid) {
            if ($this->meetsStorageCriteria($grid)) {
                $this->grid_cache[$idx] = $grid;
                $number_stored++;
            }
        }
        $this->log(count($grids) . ' grids sent for storage', $this->debug);
        $this->log('stored ' . $number_stored . ' grids', $this->debug);
        $this->log('store now has ' . count($this->grid_cache) . ' grids', $this->debug);
    }

    protected function meetsStorageCriteria($grid)
    {
        // TBI
        return count($this->grid_cache) < $this->cache_size;
    }

    protected function meetsPullFromStorageCriteria($grid)
    {
        // TBI
        return true;
    }

    protected function pullStored($count)
    {
        $this->log($count . ' grids requested from storage', $this->debug);
        $available = count($this->grid_cache);
        $return = [];
        $this->log($available . ' grids in storage', $this->debug);
        foreach ($this->grid_cache as $idx => $grid) {
$this->log($idx . ' has difficulty ' . $grid['difficulty']);
            if (count($return) < $count && $this->meetsPullFromStorageCriteria($grid)) {
                $return[$idx] = $grid;
                unset($this->grid_cache[$idx]);
            }
        }

        $this->log(count($return) . ' grids pulled from storage', $this->debug);

        return $return;
    }

    protected function filterPaths($grids, $numyawant = 6, $max_difficulty = 0)
    {
        if (empty($numyawant) || $numyawant >= count($grids)) {
            return $grids;
        }

        if (!empty($max_difficulty)) {
            foreach ($grids as $key => $grid) {
                if ($grid['difficulty'] > $max_difficulty) {
                    unset($grids[$key]);
                }
            }
        }

        $this->aaSort($grids, ['difficulty' => 'asc']);

        $return = array_slice($grids, 0, $numyawant);
        $this->store($grids);

        return $return;
    }

    protected function reduceGrid($sgx, $strip_ids_to_process = [], $use_advanced_reduction = true, $skip_cache = false)
    {
        if ($use_advanced_reduction) {
            $this->log('using advanced reduction');
        }
        $this->timeCheck();

        // if not new here, pull result from memory
        // TBI -- what if adv redux was not used beofre and now the flag is set to true?
        $key = $sgx['key'];
        if (!$skip_cache && isset($this->saved_grids[$key])) {
            return $this->saved_grids[$key];
        }

        $sgx = $this->reduceStrips($sgx, $strip_ids_to_process, $use_advanced_reduction);
        if (!$sgx) {
            $this->saved_grids[$key] = false;
            return false;
        }

        $difficulty = $this->difficultyScore($sgx);
        if ($difficulty < 0.1) {
            if (!$this->checkSolution($sgx)) {
                $difficulty = 100;
            }
        }

        $sgx['difficulty'] = $difficulty;
        $this->saved_grids[$key] = $sgx;
        return !empty($sgx) ? $sgx : false;
    }

    protected function reduceStrips($sgx, $strip_ids_to_process = [],  $use_advanced_reduction = true)
    {
        $this->changed = true;
        if (empty($strip_ids_to_process)) {
            $strips = $this->strips;
        } else {
            $strips = [];
            foreach($strip_ids_to_process as $idx) {
                $strips[$idx] = $this->strips[$idx];
            }
        }

        while (!empty($strips) && !$this->done) {
            $this->changed = false;
            foreach ($strips as $idx => $strip) {
// $this->log("reducing strip $idx: " . json_encode($strip));
                $sgx = $this->reduceStrip($strip, $sgx, $use_advanced_reduction);
// $this->display($sgx);
                if ($sgx === false) {
                    $this->log("problem reducing strip $idx");
                    $this->problem_strips[$idx] = $strip;
                    $this->log($this->problem_strips);
                    return false;
                }
// $this->display($sgx);
            }
// $this->log('jbf strips chgd(g): '.count($sgx['changed_strips']));
            if (empty($sgx['changed_strips'])) {
                break;
            }

            $strips = [];
            foreach($sgx['changed_strips'] as $idx) {
                $strips[$idx] = $this->strips[$idx];
            }
            $sgx['changed_strips'] = [];
// $this->log('jbf strips next loop: '.count($strips));
        }

        return $sgx;
    }

    protected function reduceStrip($strip, $sgx, $use_advanced_reduction = true)
    {
        // set up vars
        $sum = $strip['total'];
        $len = $strip['len'];
        $dir = $strip['dir'];
        $used_numbers = [];
        if ($dir === 'h') {
            $start = $strip['start'][1];
            $row = $strip['start'][0];
        } else {
            $start = $strip['start'][0];
            $col = $strip['start'][1];
        }

        // see if anything to do, get ready if so
        $undecided_cells = [];
        $decided_cells = [];
        for ($k = $start; $k < $start + $len; $k++) {
            $i = $dir === 'v' ? $k : $row; 
            $j = $dir === 'h' ? $k : $col;
            if (!isset($sgx['cells'][$i][$j])) {
$this->log("e $i $j grid val not set");
                return false;
            }
            $cell = $sgx['cells'][$i][$j];
            if (count($cell['choices']) === 1) {
                $num = current($cell['choices']);
                $sum -= $num;
                $used_numbers[] = $num;
                $decided_cells[] = $cell;
            } else {
                $undecided_cells[] = $cell;
            }
        }

        
        if (empty($undecided_cells)) { // nothing to do
            return $sgx;
        }

        $size = $len - count($used_numbers);
        $choices = $this->getValues($sum, $size, $used_numbers);
        $still_undecided_cells = [];

        foreach ($undecided_cells as $cell) {
            $pv = $cell['choices'];
            $new_pv = array_values(array_intersect($pv, $choices));
            if (count($undecided_cells) === 2) {
                $sum = $strip['total'];
                foreach ($decided_cells as $dc) {
                    $sum -= current($dc['choices']);
                }
                $new_pv = $this->reduceDuplet($sum, $cell, $undecided_cells);
            }
            if (empty($new_pv)) {
$this->log('e '.$cell['i'].' '.$cell['j']);
$this->log($cell);
$this->log($pv);
$this->log($choices);
                return false;
            }
            if (count($new_pv) < count($pv)) {
                $sgx =  $this->updateChoices($sgx, $cell, $new_pv);
                $cell['choices'] = $new_pv;
            }
            if (count($new_pv) === 1) {
                $num = current($new_pv);
                $sum -= $num;
                $used_numbers[] = $num;
                $size--;
                $choices = $this->getValues($sum, $size, $used_numbers);
            } else {
                $still_undecided_cells[] = $cell;
            }
        }

        if (empty($still_undecided_cells)) {
            if (!$this->checkStrip($strip, $sgx)) {
$this->log("e strip fails check");
                return false;
            }
            return $sgx;
        }

        return $use_advanced_reduction ? $this->advancedStripReduction($sgx, $still_undecided_cells, $sum, $used_numbers) : $sgx;
    }

    protected function reduceDuplet($sum, $cell, $cells)
    {
        $choices = $cell['choices'];
        if (count($cells) === 2) {
            $pv = [];
            $cell_idx = $cells[0]['i'] === $cell['i'] && $cells[0]['j'] === $cell['j'] ? 0 : 1;
            $other_idx = $cell_idx ? 0 : 1;
            foreach($choices as $idx => $choice) {
                if (in_array($sum - $choice, $cells[$other_idx]['choices'])) {
                    $pv[] = $choice;
                }
            }

            if (empty($pv)) {
                $x = 1;
            }
            return $pv;
        }

        return $choices;
    }

    protected function advancedStripReduction($grid, $cells, $sum, $used)
    {
// $this->log('advancedStripReduction');
        $cells = $this->refreshCellChoices($grid, $cells); // in case they got stale
        $grid = $this->reduceByComplement($grid, $cells, $sum, $used);

        if (empty($grid)) {
            return false;
        }
        $cells = $this->refreshCellChoices($grid, $cells);
        return $this->reduceByPigeonHole($grid, $cells, $sum, $used);
    }

    // if there are two free cells, check the other cell's choices to reduce yours
    // example if the sum is 7 and one has 234 the other's choices are limited to 345
    protected function reduceByComplement($grid, $cells, $sum, $used)
    {
        if (count($cells) < 2) {
            return $grid;
        }
        // each value in each cell -- is the complement possible?
        foreach ($cells as $indx => $cell) {
            $complement = $cells;
            unset($complement[$indx]);
            foreach ($cell['choices'] as $idx => $v) {
// $this->log("see if $v ($sum) possible in " . $cell['i'] .' '.$cell['j'], true);
// $this->log("choose and sum is ".($sum-$v), true);
                if (!$this->isPossible($complement, $sum - $v, array_merge($used, [$v]))) {
// $this->log("$v not possible in " . $cell['i'] .' '.$cell['j'], true);
                    unset($cell['choices'][$idx]);
                    if (empty($cell['choices'])) {
                        return false;
                    }
                    $grid = $this->updateChoices($grid, $cell, $cell['choices']);
                }
            }
        }

        return $grid;
    }

    // if there is a set of n choices in n cells with no other choices, that set can be removed from the other cells in the strip
    // example 3 cells with option 23 24 23 -- the middle cell cannot contain 2
    protected function reduceByPigeonHole($grid, $cells, $sum, $used)
    {
        // each cell, my choices C; separate cells into groups Y and N based on criteria:
        // 'do you have nothing outside of C' if count(Y) == count(C) remove C from N; fail is count(Y) > count(C)
        foreach ($cells as $cell) {
            $c = $cell['choices'];
            $y = [];
            $n = [];
            foreach ($cells as $inner) {
                if (empty(array_diff($inner['choices'], $c))) {
                    $y[] = $inner;
                } else {
                    $n[] = $inner;
                }
            }
            if (empty($n)) {
                continue;
            }
            if (count($y) < count($c)) {
                continue;
            }
            if (count($y) > count($c)) {
                return false;
            }
            $grid = $this->removeChoicesFromCells($grid, $n, $c);
            if (empty($grid)) {
                return false;
            }
        }

        return $grid;
    }

    protected function isPossible($cells, $sum, $used)
    {
        $pv = $this->getValues($sum, count($cells), $used);

        foreach ($cells as $cell) {
            if (empty(array_values(array_intersect($cell['choices'], $pv)))) {
                return false;
            }
        }

        // if there are 2 in the set, test for complement:
        if (count($cells) === 2) {
            $cells = array_values($cells);
            foreach ($cells[0]['choices'] as $choice) { // one way is sufficient
                $complement = $sum - $choice;
                if (in_array($complement, $cells[1]['choices'])) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    protected function removeChoicesFromCells($grid, $cells, $choices)
    {
        if (!is_array($cells)) {
            $cells = [$cells];
        }

        foreach ($cells as $cell) {
            $grid = $this->removeChoices($grid, $cell, $choices);
            if (empty($grid)) {
                return false;
            }
        }

        return $grid;
    }

    protected function refreshCellChoices($grid, $cells)
    {
if (empty($grid)) {
    $this>log("no grid");exit;
}
if (empty($cells)) {
    $this>log("no cells");exit;
}
        $c = [];
        foreach ($cells as $cell) {
            $grid_choices = $grid['cells'][$cell['i']][$cell['j']]['choices'];
            $cell['choices'] = array_values(array_intersect($grid_choices, $cell['choices']));
            $c[] = $cell;
        }

        return $c;
    }

    protected function addSolution($grid)
    {
        if (!$this->solutionExists($grid)) {
            $key = $this->collapse($grid);
            $this->solutions[$key] = $grid;
            $this->log('add solution ' . $key);
            $this->log('path ' . $this->getPathKey($grid['path']));
        }

        if (!empty($this->solutions_desired) && count($this->solutions) >= $this->solutions_desired) {
            $this->done = true;
            $this->log($this->displaySolutions(), true);
            exit;
        }
    }

    protected function solutionExists($grid)
    {
        if (empty($grid)) {
            return false;
        }
        $key = $this->collapse($grid);
        if (in_array($key, array_keys($this->solutions))) {
            return true;
        }
    }

    protected function collapse($grid)
    {
        $str = '';
        foreach ($grid['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlank($i, $j)) {
                    $str .= '.';
                } else {
                    $str .= current($cell['choices']);
                }
            }
        }

        return $str;
    }

    protected function display($grid, $standard_output = false)
    {
        $this->log($this->displayOptions($grid), $standard_output && $this->debug);
    }

    protected function displayGuessSequence($sgx)
    {
        $str = '';
        $path = $sgx['path'];
        if (empty($path)) {
            return "none\n";
        }
        foreach ($path as $guess) {
            $parts = explode('_', $guess);
            $str .= "guess value " . $parts[2] . " at cell {$parts[0]}, {$parts[1]}" . "\n";
        }
        return $str;
    }

    protected function displayOptions($sgx, $padding = 10)
    {
        $str = "\n";
        if ($this->debug) {
            $path = $this->getPathKey($sgx['path']);
            $str .= "grid at $path:\n";
            $str .= "difficulty: " . $sgx['difficulty'] . "\n";
        }

        foreach ($sgx['cells'] as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($this->isBlank($i, $j)) {
                    $c = '.';
                } else {
                    $c = implode('', $cell['choices']);
                }
                $str .= str_pad($c, $padding, ' ');
            }
            $str .= "\n";
        }

        return $str;
    }

    protected function timeCheck()
    {
        $time = microtime(true);
        $elapsed_time = $time - $this->start_time;
        $this->log('time ' . $elapsed_time, $this->debug);
        if ($elapsed_time >= $this->time_limit) {
            $this->log('time limit');
            return false;
        }

        if (!$this->debug && $time - $this->last_output_time > 1) {
            print ".";
            $this->last_output_time = $time;
        }

        return true;
    }

    protected function getCurrentPathKey()
    {
        if (empty($this->current_path)) {
            return 'initial';
        }

        $simplified_path = [];
        foreach ($this->current_path as $node) {
            $simplified_path[] = $node['i'] . '_' . $node['j'] . '_' . $node['choice'];
        }

        return $this->getPathKey($simplified_path);
    }

    protected function getPathKey($path)
    {
        sort($path);
        return !empty($path) ? implode('__', $path) : 'initial';
    }

    public function getResult()
    {
        return $this->solutions;
    }
}