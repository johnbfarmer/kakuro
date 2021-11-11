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
     * @Route("/grid/design/{id}", name="design_grid", defaults={"id" = 0})
     */
    public function designGridAction($id)
    {
        return $this->render('default/grid.html.twig', ['gridId' => $id]);
    }

    /**
     * @Route("/grid/design-by-sum/{id}", name="design_grid_by_sum", defaults={"id" = 0})
     */
    public function designGridBySumAction($id)
    {
        return $this->render('default/grid.html.twig', ['gridId' => $id]);
    }

    /**
     * @Route("/grid/{id}", name="grid", defaults={"id" = 0})
     */
    public function gridAction($id)
    {
        return $this->render('default/grid.html.twig', ['gridId' => $id]);
    }
}
