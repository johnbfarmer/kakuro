<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;
use AppBundle\Process\SaveGrid;
use AppBundle\Helper\GridHelper;

class ApiController extends Controller
{
    /**
     * @Route("api/grid/{name}", name="grid_api")
     */
    public function gridAction(Request $request, $name)
    {
        return new JsonResponse(GridHelper::getGrid($name));
    }

    /**
     * @Route("api/get-choices", name="grid_get_choices")
     */
    public function getChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $advanced_reduction = $request->request->has('advanced') && $request->request->get('advanced');
        $name = $request->request->get('grid_name');
        $parameters = ['grid_name' => $name, 'cells' => $cells, 'simple_reduction' => !$advanced_reduction, 'reduce_only' => true];
        $x = SolveGrid::autoExecute($parameters, null);
        $grid = $x->getApiResponse();
        return new JsonResponse($grid);
    }

    /**
     * @Route("api/save-choices", name="grid_save_choices")
     */
    public function saveChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $name = $request->request->get('grid_name');
        $parameters = ['grid_name' => $name, 'cells' => $cells];
        $x = SaveGrid::autoExecute($parameters, null);
        $grid = [];
        return new JsonResponse($grid);
    }
}
