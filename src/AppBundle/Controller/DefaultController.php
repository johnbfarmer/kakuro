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
        // TBI
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/grid", name="grid")
     */
    public function gridAction(Request $request)
    {
        return $this->render('default/grid.html.twig', []);
    }

    /**
     * @Route("/grid/design", name="design_grid")
     */
    public function designGridAction(Request $request)
    {
        return $this->render('default/design_grid.html.twig', []);
    }
}
