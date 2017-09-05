<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;
use AppBundle\Process\KakuroReducer;
use AppBundle\Process\SaveGrid;
use AppBundle\Process\LoadSavedGrid;
use AppBundle\Helper\GridHelper;
use AppBundle\Entity\Grid;

class ApiController extends Controller
{
    /**
     * @Route("api/grid/{id}", name="get_grid")
     */
    public function gridAction(Request $request, $id)
    {
        $grid = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->find($id);
        return new JsonResponse($grid->getForApi());
    }

    /**
     * @Route("api/games", name="get_games")
     */
    public function gamesAction(Request $request)
    {
        $games = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->findBy([], ['name' => 'asc']);
        $arr = [];
        foreach ($games as $game) {
            $arr[] = ['name' => $game->getId(), 'label' => $game->getName()];
        }
        return new JsonResponse(['games' => $arr]);
    }

    /**
     * @Route("api/get-choices", name="grid_get_choices")
     */
    public function getChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $advanced_reduction = $request->request->has('advanced') && $request->request->get('advanced');
        $grid_id = $request->request->get('grid_id');
        $grid = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->find($grid_id);
        $parameters = [
            'grid' => $grid,
            'cells' => $grid->getForProcessing(),
            'uiChoices' => $cells,
            'simpleReduction' => !$advanced_reduction,
        ];
        $reducer = KakuroReducer::autoExecute($parameters, null);
        return new JsonResponse($reducer->getApiResponse());
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
     * @Route("api/get-hint", name="grid_get_hint")
     */
    public function getHintAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $grid_id = $request->request->get('grid_id');
        $grid = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->find($grid_id);
        $parameters = [
            'grid' => $grid,
            'cells' => $grid->getForProcessing(),
            'uiChoices' => $cells,
            'hint' => true,
        ];
        $reducer = KakuroReducer::autoExecute($parameters, null);
        return new JsonResponse($reducer->getHint());
    }

    /**
     * @Route("api/save-choices", name="grid_save_choices")
     */
    public function saveChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $id = $request->request->get('grid_id');
        $saved_grid_name = $request->request->has('saved_grid_name') && !empty($request->request->get('saved_grid_name')) 
            ? $request->request->get('saved_grid_name') 
            : 'kakuro_' . time();
        $parameters = ['id' => $id, 'saved_grid_name' => $saved_grid_name, 'cells' => $cells];
        $x = SaveGrid::autoExecute($parameters, null);
        $grid = [];
        return new JsonResponse($grid);
    }

    /**
     * @Route("api/load-choices", name="grid_load_choices")
     */
    public function loadSavedChoicesAction(Request $request)
    {
        $saved_grid_id = $request->request->get('saved_grid_id');
        $savedGrid = $this->getDoctrine()->getManager()->getRepository('AppBundle:SavedGrid')->find($saved_grid_id);
        return new JsonResponse($savedGrid->getForApi());
    }
}
