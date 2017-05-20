<?php

namespace AppBundle\Process;

class BuildTables extends BaseProcess
{
    protected 
        $max_size,
        $number_set = [1,2,3,4,5,6,7,8,9],
        $target,
        $size,
        $known = [],
        $temp = [];

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        $this->target = !empty($this->parameters['target']) ? (int)$this->parameters['target'] : 0;
        $this->size = !empty($this->parameters['size']) ? (int)$this->parameters['size'] : 1;
        if (!empty($this->parameters['number_set'])) {
            if (is_array($this->parameters['number_set'])) {
                $this->number_set = $this->parameters['number_set'];
            } else {
                $this->number_set = array_map('intval', explode(',', $this->parameters['number_set']));
            }
        }
        $this->max_size = count($this->number_set);
        $this->tables = [];
    }

    protected function execute()
    {
        $this->validate();
        $this->findChoices($this->target, $this->size, $this->number_set, []);
    }

    public function findValues($target, $size, $used = [])
    {
        $stored = $this->fetchTarget($target, $size, $used);
        if (!empty($stored)) {
            return $stored;
        }
        $this->temp = [];
        $unused = array_values(array_diff($this->number_set, $used));
        $this->findChoices($target, $size, $unused, []);
        $solutions = $this->flatten($this->temp);
        if (empty($solutions)) {
            return [];
        }
        $this->store($target, $size, $used, $solutions);
        return $solutions;
    }

    protected function fetchTarget($target, $size, $used)
    {
        $key = $this->getStorageKey($used);
        return !empty($this->known[$target][$size][$key]) ? $this->known[$target][$size][$key] : [];
    }

    protected function store($target, $size, $used, $solutions)
    {
        $key = $this->getStorageKey($used);
        $this->known[$target][$size][$key] = $solutions;
    
        return true;
    }

    public function findChoices($target, $size, $unused = [], $solutions = [])
    {
        $found = false;
        $max = !empty($solutions) ? max($solutions) : 0;
        if ($size === 1 && in_array($target, $unused)) {
            $solutions[] = $target;
            $this->temp[] = $solutions;
            $found = true;
        }

        foreach ($unused as $elt) {
            $reduced_set = [];
            foreach ($unused as $remaining_choice) {
                if ($remaining_choice > max($max, $elt)) {
                    $reduced_set[] = $remaining_choice;
                }
            }
            $augmented_solutions = $solutions;
            $augmented_solutions[] = $elt;
            $this->findChoices($target - $elt, $size - 1, $reduced_set, $augmented_solutions);
        }

        return $found;
    }

    protected function getStorageKey($used)
    {
        if (empty($used)) {
            return 0;
        }

        sort($used);
        return implode('_', $used);
    }

    protected function flatten($a, $solution = [])
    {
        if (is_array($a)) {
            foreach ($a as $b) {
                $solution = $this->flatten($b, $solution); 
            }
        } else {
            if (!in_array($a, $solution)) {
                $solution[] = $a;
            }
        }

        return $solution;
    }

    protected function validate()
    {
        if ($this->size > $this->max_size) {
            $msg = 'size too big: ';
            $msg .= 'size ' . $this->size . ' > ' . $this->max_size;
            $msg .= ' (' . json_encode($this->parameters) . ')';
            throw new \Exception($msg);
        }
    }

    public function getResult()
    {
        return $this->temp;
    }
}