<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Controller;

use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaNieuwFormType;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaAfmeldenFormType;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LooptoelichtingFormType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use DateTime;
use Exception;

class LoperlijstController extends Controller
{
    /**
     * Lijst met aanmeldingen
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $minAantalLopers = $this->container->getParameter('loopschema_minimum_aantal_lopers');

        $em = $this->get('doctrine')->getManager();
        
        $openList = array();
        foreach ($em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActive($user) as $key => $loopschema) {
            // Aanpassing m.b.t. looprondes. In de nieuwe situatie zijn er 
            // mogelijk meerdere looprondes per dag. We onderscheiden de
            // rondes m.b.v. de "datum" (datum en tijd). Een loopronde is
            // "gevuld" wanneer er een minimaal aantal lopers is per ronde.
            $datum = $loopschema->getDatum();
            $useDatetime = true;
            $openList[$key]['eigen_schema'] = $loopschema;
            $openList[$key]['toelichtingen'] = $em->getRepository('ZabutoBuurtpreventieBundle:Looptoelichting')->findForDate($datum, $useDatetime);
            $openList[$key]['schemas_anderen'] = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForDate($datum, $user, $useDatetime);
            $openList[$key]['gevuld'] = (count($openList[$key]['schemas_anderen']) >= ($minAantalLopers - 1)) ? true : false;
        }

        return $this->render('ZabutoBuurtpreventieBundle:Loperlijst:list.html.twig', array('loper' => $user, 'openList' => $openList));
    }

    /**
     * Datum annuleren
     *
     * @param integer $id
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function cancelDateAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            throw new Exception('ID kan niet worden opgevraagd');
        }

        $form = $this->createForm(new LoopschemaAfmeldenFormType(), $loopschema);
        $form->setData($loopschema);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $loopschema->setActueel(false);
                $em->persist($loopschema);
                $em->flush();

                $this->get('session')->getFlashBag()->clear();
                $this->get('session')->getFlashBag()->add('buurtpreventie-loper', 'Uw afmelding voor ' . $loopschema->getDatum()->format('d-m-Y') . ' is verwerkt.');

                return $this->redirect($this->generateUrl('buurtpreventie_loper_lijst'));
            }
        }

        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loperlijst:cancel.html.twig', $data);
    }

    /**
     * Toelichting schrijven
     *
     * @param integer $id
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function toelichtingDateAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            throw new Exception('ID kan niet worden opgevraagd');
        }

        $toelichting = new Looptoelichting();
        $loopschema->addToelichting($toelichting);

        $form = $this->createForm(new LooptoelichtingFormType(), $toelichting);
        $form->setData($loopschema);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $em->persist($loopschema);
                $em->flush();

                $this->get('session')->getFlashBag()->clear();
                $this->get('session')->getFlashBag()->add('buurtpreventie-loper', 'Uw toelichting bij ' . $loopschema->getDatum()->format('d-m-Y') . ' is verwerkt.');

                return $this->redirect($this->generateUrl('buurtpreventie_loper_lijst'));
            }
        }

        $toelichtingen = $em->getRepository('ZabutoBuurtpreventieBundle:Looptoelichting')->findForDate($loopschema->getDatum());

        $data = array(
            'toelichtingen' => $toelichtingen,
            'entity' => $loopschema,
            'form' => $form->createView(),
            'action' => $this->generateUrl($this->getRequest()->get('_route'), array('id' => $loopschema->getId(), 'date' => $loopschema->getDatum()->format('Y-m-d'))),
            'redir' => $this->generateUrl('buurtpreventie_loper_lijst'),
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loperlijst:toelichting.html.twig', $data);
    }
}
