<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Controller;

use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaResultaatFormType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Exception;

class LoopresultaatController extends Controller
{
    /**
     * Lijst gelopen ronden
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $minAantalLopers = $this->container->getParameter('loopschema_minimum_aantal_lopers');

        $em = $this->get('doctrine')->getManager();

        $resultList = array();
        foreach ($em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllHistory($user) as $key => $loopschema) {
            $others = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForDate($loopschema->getDatum(), $user);
            if (count($others) >= ($minAantalLopers - 1)) {
                $resultList[$key]['eigen_schema'] = $loopschema;
                $resultList[$key]['schemas_anderen'] = $others;

                $resultList[$key]['eigen_resultaat'] = false;
                $resultList[$key]['resultaten'] = array();
                $resultList[$key]['bijzonderheden'] = 0;
                $resultList[$key]['incidenten'] = 0;

                if (null !== $loopschema->getResultaat()) {
                    $resultList[$key]['eigen_resultaat'] = true;
                    $resultList[$key]['resultaten'][] = $loopschema;
                    if (true === $loopschema->getResultaat()->getBijzonderheid()) {
                        $resultList[$key]['bijzonderheden'] = $resultList[$key]['bijzonderheden'] + 1;
                    }
                    if (true === $loopschema->getResultaat()->getIncident()) {
                        $resultList[$key]['incidenten'] = $resultList[$key]['incidenten'] + 1;
                    }
                }

                foreach ($others as $otherSchema) {
                    if (null !== $otherSchema->getResultaat()) {
                        $resultList[$key]['resultaten'][] = $otherSchema;
                        if (true === $otherSchema->getResultaat()->getBijzonderheid()) {
                            $resultList[$key]['bijzonderheden'] = $resultList[$key]['bijzonderheden'] + 1;
                        }
                        if (true === $otherSchema->getResultaat()->getIncident()) {
                            $resultList[$key]['incidenten'] = $resultList[$key]['incidenten'] + 1;
                        }
                    }
                }
            }
        }

        return $this->render('ZabutoBuurtpreventieBundle:Loopresultaat:list.html.twig', array('loper' => $user, 'resultList' => $resultList));
    }

    /**
     * Resultaat voor datum verwerken
     *
     * @param integer $id
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function dateAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            throw new Exception('ID kan niet worden opgevraagd');
        }

        $form = $this->createForm(new LoopschemaResultaatFormType(), $loopschema);
        $form->setData($loopschema);

        if ($this->getRequest()->isMethod('POST')) {
            $form->submit($this->getRequest());
            if ($form->isValid()) {
                $em->persist($loopschema);
                $em->flush();

                $mailManager = $this->get('zabuto_buurtpreventie.mailmanager');
                $mailManager->mailLoopschemaResultaat($loopschema);

                $this->get('session')->getFlashBag()->clear();
                $this->get('session')->getFlashBag()->add('buurtpreventie-loper', 'Uw resultaat voor ' . $loopschema->getDatum()->format('d-m-Y') . ' is verwerkt.');

                return $this->redirect($this->generateUrl('buurtpreventie_loper_resultaat_lijst'));
            }
        }

        $resultaten = $em->getRepository('ZabutoBuurtpreventieBundle:Loopresultaat')->findAll();

        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
            'resultaten' => $resultaten,
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loopresultaat:result.html.twig', $data);
    }
}
