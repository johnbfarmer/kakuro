<?php

namespace AppBundle\Entity;

class Strip
{
    private $id;
    private $dir;
    private $total;
    private $len;
    private $startRow;
    private $stopRow;
    private $startCol;
    private $stopCol;
    private $possibleValues = [];

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setDir($dir)
    {
        $this->dir = $dir;
        return $this;
    }

    public function getDir()
    {
        return $this->dir;
    }

    public function setTotal($total)
    {
        $this->total = $total;
        return $this;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setLen($len)
    {
        $this->len = $len;
        return $this;
    }

    public function getLen()
    {
        return $this->len;
    }

    public function setStartRow($startRow)
    {
        $this->startRow = $startRow;
        return $this;
    }

    public function getStartRow()
    {
        return $this->startRow;
    }

    public function setStopRow($stopRow)
    {
        $this->stopRow = $stopRow;
        return $this;
    }

    public function getStopRow()
    {
        return $this->stopRow;
    }

    public function setStartCol($startCol)
    {
        $this->startCol = $startCol;
        return $this;
    }

    public function getStartCol()
    {
        return $this->startCol;
    }

    public function setStopCol($stopCol)
    {
        $this->stopCol = $stopCol;
        return $this;
    }

    public function getStopCol()
    {
        return $this->stopCol;
    }

    public function calculateLen()
    {
        if ($this->dir === 'h') {
            $this->len = 1 + $this->stopCol - $this->startCol;
        } else {
            $this->len = 1 + $this->stopRow - $this->startRow;
        }
    }

    public function setPossibleValues($vals)
    {
        $this->possibleValues = $vals;
        return $this;
    }

    public function getPossibleValues()
    {
        return $this->possibleValues;
    }
}
