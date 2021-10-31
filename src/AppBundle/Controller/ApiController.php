<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;
use AppBundle\Process\KakuroReducer;
use AppBundle\Process\KakuroUniquenessTester;
use AppBundle\Process\SaveGrid;
use AppBundle\Process\SaveDesign;
use AppBundle\Process\LoadSavedGrid;
use AppBundle\Process\KakuroSolution;
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
        $games = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->findBy(['show' => 1], ['name' => 'asc']);
        $arr = [];
        foreach ($games as $game) {
            $arr[] = ['val' => $game->getId(), 'label' => $game->getName()];
        }
        return new JsonResponse(['games' => $arr]);
    }

    /**
     * @Route("api/get-choices", name="grid_get_choices")
     */
    public function getChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        // $advanced_reduction = $request->request->has('advanced') && $request->request->get('advanced');
        $grid_id = $request->request->get('grid_id');
        $level = $request->request->get('level');
        $grid = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->find($grid_id);
        $parameters = [
            'grid' => $grid,
            'cells' => $grid->getForProcessing(),
            'uiChoices' => $cells,
            'level' => $level,
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
            'oneStep' => true,
            // 'hintOnly' => true,
        ];
        $reducer = KakuroReducer::autoExecute($parameters, null);
        return new JsonResponse($reducer->getApiResponse());
        // return new JsonResponse($reducer->getHint());
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

    /**
     * @Route("api/build/get-choices", name="build_choices")
     * @Method({"POST"})
     */
    public function getBuildChoicesAction(Request $request)
    {
        // $cells = $request->request->get('cells');
        $cells = json_decode($request->request->get('cells'), true);
        // $cells[9]['choices'] = [2,3];
        $parameters = [
            'cells' => $cells,
        ];
        // $reducer = KakuroReducer::autoExecute($parameters, null);
        return new JsonResponse($parameters);
    }

    /**
     * @Route("api/save-design", name="grid_save_design")
     */
    public function saveDesignAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $id = $request->request->has('grid_id') ? $request->request->get('grid_id') : null;
        $height = $request->request->get('height');
        $width = $request->request->get('width');
        $asCopy = $request->request->has('asCopy') ? (int)$request->request->get('asCopy') : 0;
        $name = $request->request->has('name') && !empty($request->request->get('name')) 
            ? $request->request->get('name') 
            : 'kakuro_' . time();
        $parameters = ['id' => $id, 'name' => $name, 'cells' => $cells, 'height' => $height, 'width' => $width, 'asCopy' => $asCopy];
        $processor = SaveDesign::autoExecute($parameters, null);
        $response = $processor->getResponse();
        return new JsonResponse($response);
    }

    /**
     * @Route("api/design/choices", name="design_choices")
     * @Method({"POST"})
     */
    public function getDesignChoicesAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $height = $request->request->get('height');
        $width = $request->request->get('width');
        $cells = GridHelper::populateDesignChoices($cells, $height, $width);
        return new JsonResponse($cells);
    }

    /**
     * @Route("api/solution/{id}", name="solution")
     */
    public function gridSolutionAction(Request $request, $id)
    {
        $solution = KakuroSolution::autoexecute(['id' => $id], null);
        return new JsonResponse($solution->getResult());
    }

    /**
     * @Route("api/check-uniqueness", name="check-uniqueness")
     */
    public function checkUniquenessAction(Request $request)
    {
        $cells = json_decode($request->request->get('cells'), true);
        $height = json_decode($request->request->get('height'), true);
        $width = json_decode($request->request->get('width'), true);
        $parameters = [
            'uiChoices' => $cells,
            'height' => $height,
            'width' => $width,
        ];
        $tester = KakuroUniquenessTester::autoExecute($parameters, null); // build grid from cells, call KakuroReducer wirh advanced = true
        return new JsonResponse($tester->getApiResponse()); // and this will need true|false and some info about alternate solutions
    }

    /**
     * @Route("api/delete-game/{id}", name="delete_game")
     */
    public function deleteGameAction(Request $request, $id)
    {
        $bool = $this->getDoctrine()->getManager()->getRepository('AppBundle:Grid')->deleteGrid($id);
        return new JsonResponse(['success' => $bool]);
    }
}
