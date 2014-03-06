<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class IndexController extends Controller
{
    /**
     * Startpagina buurtpreventie
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->render('ZabutoBuurtpreventieBundle:Index:index.html.twig');
    }
}
