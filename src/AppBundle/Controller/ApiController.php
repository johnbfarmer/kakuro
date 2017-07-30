<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;
use AppBundle\Process\SaveGrid;
use AppBundle\Process\LoadSavedGrid;
use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;

class ApiController extends Controller
{
    /**
     * @Route("api/grid/{name}", name="get_grid")
     */
    public function gridAction(Request $request, $name)
    {
        $grid = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->findOneBy(['name' => $name]);
        GridHelper::log($grid->getName());
        return new JsonResponse($grid->getForApi());
        // return new JsonResponse(GridHelper::getGrid($name));
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
     * @Route("api/check", name="grid_check")
     */
    public function checkSolutionAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $name = $request->request->get('grid_name');
        $parameters = ['grid_name' => $name, 'cells' => $cells];
        // $x = SolveGrid::autoExecute($parameters, null);
        // $grid = $x->getApiResponse();
        return new JsonResponse(['isSolution' => true]);
    }

    /**
     * @Route("api/save-choices", name="grid_save_choices")
     */
    public function saveChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $grid_name = $request->request->get('grid_name');
        $saved_grid_name = $request->request->get('saved_grid_name');
        $parameters = ['grid_name' => $grid_name, 'saved_grid_name' => $saved_grid_name, 'cells' => $cells];
        $x = SaveGrid::autoExecute($parameters, null);
        $grid = [];
        return new JsonResponse($grid);
    }

    /**
     * @Route("api/load-choices", name="grid_load_choices")
     */
    public function loadSavedChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $grid_name = $request->request->get('grid_name');
        $saved_grid_name = $request->request->get('saved_grid_name');
        $parameters = ['grid_name' => $grid_name, 'saved_grid_name' => $saved_grid_name];
        $grid = LoadSavedGrid::autoExecute($parameters, null);
        return new JsonResponse($grid->getGrid());
    }
}
