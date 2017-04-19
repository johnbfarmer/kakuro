<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
            $arr = [];
            foreach ($cell_contents as $cell) {
                if (strpos($cell, '\\') !== false) {
                    $c = explode('\\', $cell);
                    $c[0] = $c[0] ? (int)$c[0] : 0;
                    $c[1] = $c[1] ? (int)$c[1] : 0;
                } else {
                    $c = null;
                }
                $grid['cells'][] = $c;
            }

            // $grid['cells'][] = $arr;
        }

        return new JsonResponse($grid);
    }
}
