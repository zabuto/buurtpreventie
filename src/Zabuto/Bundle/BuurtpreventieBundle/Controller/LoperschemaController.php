<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Controller;

use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Zabuto\Bundle\BuurtpreventieBundle\Entity\Looptoelichting;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaAfmeldenFormType;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaNieuwFormType;
use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LoopschemaResultaatFormType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Form;
use DateTime;
use Exception;

class LoperschemaController extends Controller
{
    /**
     * Kalender tonen
     *
     * @param integer $jaar
     * @param integer $maand
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function calendarAction($jaar = null, $maand = null)
    {
        $allowResults = $this->container->getParameter('loopschema_resultaat_voor_lopers');
        if (false === $allowResults) {
            $securityContext = $this->container->get('security.context');
            if ($securityContext->isGranted('ROLE_ADMIN')) {
                $allowResults = true;
            }
        }

        $jaar = !is_null($jaar) ? (int)$jaar : date('Y');
        $maand = !is_null($maand) ? (int)$maand : date('n');

        $prev = ($allowResults) ? 'true' : 'false';
        $next = $this->container->getParameter('loopschema_maanden_vooruit');

        $currDate = new DateTime('now');
        $calDate = DateTime::createFromFormat('Y-n', $jaar . '-' . $maand);

        if ($calDate > $currDate) {
            $interval = $calDate->diff($currDate);
            $months = (int)$interval->format('%m');
            $prev = $months;
            $next = $next - $months;
            $next = ($next < 1) ? 'false' : $next;
        }

        return $this->render('ZabutoBuurtpreventieBundle:Loperschema:calendar.html.twig', array('jaar' => $jaar, 'maand' => $maand, 'prev' => $prev, 'next' => $next, 'allowResults' => $allowResults));
    }

    /**
     * Kalender data ophalen
     *
     * @return JsonResponse
     */
    public function calendarDataAction()
    {
        $allowResults = $this->container->getParameter('loopschema_resultaat_voor_lopers');
        if (false === $allowResults) {
            $securityContext = $this->container->get('security.context');
            if ($securityContext->isGranted('ROLE_ADMIN')) {
                $allowResults = true;
            }
        }

        $user = $this->container->get('security.context')->getToken()->getUser();

        $year = $this->getRequest()->get('year', date('Y'));
        $month = $this->getRequest()->get('month', date('n'));

        $em = $this->get('doctrine')->getManager();
        $data = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForMonth($year, $month, !$allowResults);

        $list = $this->_loopschemaToList($data, $user);
        $list = $this->_formatList($list);

        return new JsonResponse($list);
    }

    /**
     * Aanmelding voor nieuwe datum
     *
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addDateFormAction($date)
    {
        $loopschema = new Loopschema();
        $loopschema->setDatum(new DateTime($date));

        $toelichting = new Looptoelichting();
        $loopschema->addToelichting($toelichting);

        $form = $this->createForm(new LoopschemaNieuwFormType(), $loopschema);
        $form->setData($loopschema);

        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
            'action' => $this->generateUrl('buurtpreventie_loper_nieuwe_datum_schema_post', array('date' => $loopschema->getDatum()->format('Y-m-d'))),
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loperschema:add-form.html.twig', $data);
    }

    /**
     * Aanmelding voor nieuwe datum verwerken
     *
     * @param $date
     * @return JsonResponse
     */
    public function addDatePostAction($date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $loopschema = new Loopschema();
        $loopschema->setLoper($user);
        $loopschema->setDatum(new DateTime($date));

        $toelichting = new Looptoelichting();
        $loopschema->addToelichting($toelichting);

        $form = $this->createForm(new LoopschemaNieuwFormType(), $loopschema);

        $form->submit($this->getRequest());
        if ($form->isValid()) {
            $em = $this->get('doctrine')->getManager();
            $em->persist($loopschema);
            $em->flush();
            return new JsonResponse(array('success' => true));
        }

        return new JsonResponse(array('success' => false, 'errors' => $this->_getFormErrors($form)));
    }

    /**
     * Afmelden voor datum
     *
     * @param $id
     * @param $date
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function cancelDateFormAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            throw new Exception('ID kan niet worden opgevraagd');
        }

        $form = $this->createForm(new LoopschemaAfmeldenFormType(), $loopschema);
        $form->setData($loopschema);

        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
            'action' => $this->generateUrl('buurtpreventie_loper_afmelden_datum_schema_post', array('id' => $loopschema->getId(), 'date' => $loopschema->getDatum()->format('Y-m-d'))),
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loperschema:cancel-form.html.twig', $data);
    }

    /**
     * Afmeldeing voor datum verwerken
     *
     * @param $id
     * @param $date
     * @return JsonResponse
     */
    public function cancelDatePostAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            return new JsonResponse(array('success' => false, 'errors' => array('ID kan niet worden opgevraagd')));
        }

        $form = $this->createForm(new LoopschemaAfmeldenFormType(), $loopschema);

        $form->submit($this->getRequest());
        if ($form->isValid()) {
            $loopschema->setActueel(false);
            $em->persist($loopschema);
            $em->flush();
            return new JsonResponse(array('success' => true));
        }

        return new JsonResponse(array('success' => false, 'errors' => $this->_getFormErrors($form)));
    }

    /**
     * Resultaat voor datum
     *
     * @param $id
     * @param $date
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws Exception
     */
    public function resultDateFormAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            throw new Exception('ID kan niet worden opgevraagd');
        }

        $form = $this->createForm(new LoopschemaResultaatFormType(), $loopschema);
        $form->setData($loopschema);

        $resultaten = $em->getRepository('ZabutoBuurtpreventieBundle:Loopresultaat')->findAll();

        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
            'action' => $this->generateUrl('buurtpreventie_loper_resultaat_datum_schema_post', array('id' => $loopschema->getId(), 'date' => $loopschema->getDatum()->format('Y-m-d'))),
            'resultaten' => $resultaten,
        );

        return $this->render('ZabutoBuurtpreventieBundle:Loperschema:result-form.html.twig', $data);
    }

    /**
     * Resultaat voor datum verwerken
     *
     * @param $id
     * @param $date
     * @return JsonResponse
     */
    public function resultDatePostAction($id, $date)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $loopschema = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findOneBy(array('id' => $id));

        if ($loopschema->getLoper()->getId() !== $user->getId()) {
            return new JsonResponse(array('success' => false, 'errors' => array('ID kan niet worden opgevraagd')));
        }

        $form = $this->createForm(new LoopschemaResultaatFormType(), $loopschema);

        $form->submit($this->getRequest());
        if ($form->isValid()) {
            $em->persist($loopschema);
            $em->flush();

            $mailManager = $this->get('zabuto_buurtpreventie.mailmanager');
            $mailManager->mailLoopschemaResultaat($loopschema);

            return new JsonResponse(array('success' => true));
        }

        return new JsonResponse(array('success' => false, 'errors' => $this->_getFormErrors($form)));
    }

    /**
     * Vertalen van loopschema's naar datum lijst
     *
     * @param Loopschema[] $data
     * @param User $user
     * @return array
     */
    private function _loopschemaToList($data, $user = null)
    {
        $list = array();

        foreach ($data as $loopschema) {
            $date = $loopschema->getDatum()->format('Y-m-d');
            $ownDateId = (!is_null($user) && $loopschema->getLoper()->getId() == $user->getId()) ? $loopschema->getId() : 0;
            $resultaat = $loopschema->getResultaat();

            if (array_key_exists($date, $list)) {
                $list[$date]['eigen_datum'] = $list[$date]['eigen_datum'] + $ownDateId;
                $list[$date]['lopers'][] = $loopschema->getLoper();

                if (!empty($resultaat)) {
                    $bijzonderheid = $resultaat->getBijzonderheid();
                    $incident = $resultaat->getIncident();
                    $list[$date]['resultaten'][] = $loopschema;
                    $list[$date]['bijzonderheden'] = $list[$date]['bijzonderheden'] + (int)$bijzonderheid;
                    $list[$date]['incidenten'] = $list[$date]['incidenten'] + (int)$incident;
                    if ($ownDateId > 0) {
                        $list[$date]['eigen_resultaat'] = true;
                    }
                }
            } else {
                $list[$date] = array(
                    'id' => $loopschema->getId(),
                    'date' => $date,
                    'jaar' => $loopschema->getDatum()->format('Y'),
                    'maand' => $loopschema->getDatum()->format('n'),
                    'datum' => $loopschema->getDatum()->format('l j F Y'),
                    'lopers' => array($loopschema->getLoper()),
                    'eigen_datum' => $ownDateId,
                    'eigen_resultaat' => false,
                );

                if (!empty($resultaat)) {
                    $bijzonderheid = $resultaat->getBijzonderheid();
                    $incident = $resultaat->getIncident();
                    $list[$date]['eigen_resultaat'] = ($ownDateId > 0) ? true : false;
                    $list[$date]['resultaten'] = array($loopschema);
                    $list[$date]['bijzonderheden'] = (int)$bijzonderheid;
                    $list[$date]['incidenten'] = (int)$incident;
                } else {
                    $list[$date]['resultaten'] = array();
                    $list[$date]['bijzonderheden'] = 0;
                    $list[$date]['incidenten'] = 0;
                }
            }
        }

        return $list;
    }

    /**
     * Datum lijst formatteren
     *
     * @param array $list
     * @return array
     */
    private function _formatList($list)
    {
        $em = $this->get('doctrine')->getManager();

        $minAantalLopers = $this->container->getParameter('loopschema_minimum_aantal_lopers');
        $today = date("Y-m-d");

        foreach ($list as $date => $info) {
            $info['editable'] = false;
            $info['toon_toelichting'] = false;
            $info['toon_resultaat'] = false;
            $info['toelichtingen'] = array();

            if ($date < $today) {
                if (count($info['lopers']) < $minAantalLopers) {
                    unset($list[$date]);
                } else {
                    if ($info['eigen_datum'] > 0 && false === $info['eigen_resultaat']) {
                        $info['editable'] = 'resultaat';
                    }

                    $info['toon_resultaat'] = true;
                    $list[$date]['badge'] = true;
                    if (count($info['resultaten']) == 0) {
                        $list[$date]['classname'] = 'loopresultaat-na';
                    } else {
                        if ($list[$date]['incidenten'] > 0) {
                            $list[$date]['classname'] = 'loopresultaat-nok';
                        } else {
                            $list[$date]['classname'] = 'loopresultaat-ok';
                        }
                    }
                }
            } elseif ($date >= $today) {
                $info['editable'] = 'aanmelding';
                $info['toon_toelichting'] = true;

                if ($date == $today && count($info['lopers']) >= $minAantalLopers) {
                    $info['toon_resultaat'] = true;
                    $info['editable'] = 'alles';
                }

                $info['toelichtingen'] = $em->getRepository('ZabutoBuurtpreventieBundle:Looptoelichting')->findForDate(new DateTime($date));

                if ($info['eigen_datum'] > 0) {
                    $list[$date]['badge'] = true;
                }
                if (count($info['lopers']) < $minAantalLopers) {
                    $list[$date]['classname'] = 'lopers-nok';
                } elseif (count($info['lopers']) >= $minAantalLopers) {
                    $list[$date]['classname'] = 'lopers-ok';
                }
            }

            $list[$date]['title'] = $info['datum'];
            $list[$date]['body'] = preg_replace("/\s+/", " ", $this->container->get('templating')->render('ZabutoBuurtpreventieBundle:Loperschema:calendardata-body.html.twig', $info));
            $list[$date]['footer'] = preg_replace("/\s+/", " ", $this->container->get('templating')->render('ZabutoBuurtpreventieBundle:Loperschema:calendardata-footer.html.twig', $info));

            unset($list[$date]['jaar']);
            unset($list[$date]['maand']);
            unset($list[$date]['datum']);
            unset($list[$date]['lopers']);
            unset($list[$date]['eigen_datum']);
            unset($list[$date]['eigen_resultaat']);
            unset($list[$date]['resultaten']);
            unset($list[$date]['bijzonderheden']);
            unset($list[$date]['incidenten']);
            unset($list[$date]['editable']);
            unset($list[$date]['toon_toelichting']);
            unset($list[$date]['toon_resultaat']);
            unset($list[$date]['toelichtingen']);
        }

        return $list;
    }

    /**
     * Bepaald formulier errors voor JSON response
     *
     * @param Form $form
     * @return array
     */
    private function _getFormErrors(Form $form)
    {
        $errors = array();
        $string = $form->getErrorsAsString();
        $parts = explode('||', str_replace(array("\r", "\r\n", "\n"), '||', $string));
        foreach ($parts as $key => $val) {
            $vals = explode(':', $val);
            if (count($vals) == 2 && trim(strtolower($vals[0])) == 'error') {
                $errors[] = trim($vals[1]);
            }
        }

        return $errors;
    }
}
