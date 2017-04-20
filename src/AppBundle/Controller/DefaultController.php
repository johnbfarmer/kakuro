<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/grid/{filename}", name="grid")
     */
    public function gridAction(Request $request, $filename)
    {
        return $this->render('default/grid.html.twig', [
            'filename' => $filename,
        ]);
    }

    /**
     * @Route("/grid-twig/{filename}", name="grid_twig")
     */
    public function gridTwigAction(Request $request, $filename)
    {
        $dir = realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR;
        $data = file_get_contents($dir.$filename);
        $rows = explode("\n", $data);
        $grid = [];
        foreach ($rows as $row) {
            $grid[] = explode("\t", $row);
        }
        return $this->render('default/grid_twig.html.twig', [
            'data' => $grid,
        ]);
    }
}
