<?php

namespace AppBundle\Process;

use Doctrine\Common\Collections\ArrayCollection;

use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;
use AppBundle\Entity\Cell;

class KakuroUniquenessTester extends BaseKakuro
{
    protected
        $cells,
        $height,
        $width;

    public function __construct($parameters = [], $em = [])
    {
        parent::__construct($parameters, $em);
        if (!empty($this->parameters['uiChoices'])) {
            $this->cells = $this->parameters['uiChoices'];
        }
        $this->height = $this->parameters['height'];
        $this->width = $this->parameters['width'];
    }

    protected function execute()
    {
        // come back to this
        // $grid = $this->getGrid();
        // $parameters = [
        //     'grid' => $grid,
        //     'cells' => $grid->getForProcessing(),
        //     'uiChoices' => $this->cells,
        //     'simpleReduction' => false,
        // ];
        // $reducer = KakuroReducer::autoExecute($parameters, null);
        // GridHelper::log(json_encode($reducer->getApiResponse()));
    }

    protected function getGrid()
    {
        $grid = new Grid();
        $grid->setHeight($this->height);
        $grid->setWidth($this->width);
        foreach($this->cells as $cell) {
            $c = new Cell();
            $c->setGrid($grid);
            $c->setLocation($cell['row'], $cell['col']);
            $c->setDataCell($cell['is_data']);
            $grid->addCell($c);
        }

        return $grid;
    }

    public function getApiResponse()
    {
        return ['success' => true];
    }
}