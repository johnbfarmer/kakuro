<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use AppBundle\Process\SolveGrid;

class ApiController extends Controller
{
    /**
     * @Route("api/grid/{filename}", name="grid_api")
     */
    public function gridAction(Request $request, $filename)
    {
        $dir = realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR;
        $data = file_get_contents($dir.$filename);
        $rows = explode("\n", $data);
        $height = array_shift($rows);
        $width = array_shift($rows);
        $grid = [
            'height' => $height + 1, // reported oddly
            'width' => $width + 1,
            'cells' => [],
        ];
        foreach ($rows as $i => $row) {
            $cell_contents = explode("\t", $row);
            if (count($cell_contents) < 2) {
                continue; // blank last line is ok for example
            }
// $this->get('logger')->error('jbf');
// $this->get('logger')->error($row);

            foreach ($cell_contents as $cell) {
                if (strpos($cell, '\\') !== false) {
                    $c = explode('\\', $cell);
                    $c[0] = $c[0] ? (int)$c[0] : 0;
                    $c[1] = $c[1] ? (int)$c[1] : 0;
                    $choices = [];
                } else {
                    $c = null;
                    $choices = [];
                }
                $grid['cells'][] = [
                    'display' => $c,
                    'is_data' => empty($c),
                    'choices' => $choices,
                ];
            }
        }

        return new JsonResponse($grid);
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
        $grid = ['cells' => $x->getApiResponse()];
        return new JsonResponse($grid);
    }
}
