<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;
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
        // $cells = $request->request->get('cells');
        $cells = json_decode($request->request->get('cells'), true);
        $advanced_reduction = $request->request->has('advanced') && $request->request->get('advanced');
        $dir = realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR;
        $filename = $request->request->get('file');
        $file = $dir.$filename;
        $parameters = ['file' => $file, 'cells' => $cells, 'simple_reduction' => !$advanced_reduction, 'reduce_only' => true];
        $x = SolveGrid::autoExecute($parameters, null);
        $grid = $x->getApiResponse();
        return new JsonResponse($grid);
    }
}
