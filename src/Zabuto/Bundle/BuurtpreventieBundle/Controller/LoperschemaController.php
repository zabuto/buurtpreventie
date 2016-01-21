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
     * Datum tonen
     *
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function dateShowAction($date)
    {
        $date = new DateTime($date);

        $isAdmin = false;
        $securityContext = $this->container->get('security.context');
        if ($securityContext->isGranted('ROLE_ADMIN')) {
            $isAdmin = true;
        }

        $allowResults = $this->container->getParameter('loopschema_resultaat_voor_lopers');
        if (false === $allowResults) {
            $allowResults = $isAdmin;
        }

        $allowAfmelding = $isAdmin;

        $em = $this->get('doctrine')->getManager();

        $activeList = $this->_loopschemaToList($em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForDate($date));
        $inactiveList = $this->_loopschemaToList($em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllInactiveForDate($date));

        $active = (array_key_exists($date->format('Y-m-d'), $activeList)) ? $activeList[$date->format('Y-m-d')] : array();
        $inactive = (array_key_exists($date->format('Y-m-d'), $inactiveList)) ? $inactiveList[$date->format('Y-m-d')] : array();
        $toelichtingen = $em->getRepository('ZabutoBuurtpreventieBundle:Looptoelichting')->findForDate($date);

        return $this->render('ZabutoBuurtpreventieBundle:Loperschema:date.html.twig', array('date' => $date, 'active' => $active, 'inactive' => $inactive, 'toelichtingen' => $toelichtingen, 'toon_resultaat' => $allowResults, 'toon_afmelding' => $allowAfmelding));
    }

    /**
     * Aanmelding voor nieuwe datum
     *
     * @param string $date
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addDateFormAction($date)
    {
        $date = new DateTime($date);
        $user = $this->container->get('security.context')->getToken()->getUser();

        $em = $this->get('doctrine')->getManager();
        $afgemeld = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllInactiveForDate($date, $user);

        $loopschema = new Loopschema();
        $loopschema->setDatum($date);

        $toelichting = new Looptoelichting();
        $loopschema->addToelichting($toelichting);

        $form = $this->createForm(new LoopschemaNieuwFormType(), $loopschema);
        $form->setData($loopschema);

        $isAdmin = false;
        $securityContext = $this->container->get('security.context');
        if ($securityContext->isGranted('ROLE_ADMIN')) {
            $isAdmin = true;
        }
        
        // Aanpassing m.b.t. tijden voor loopschemas
        // Men kan zelf een tijd aangeven voor een loopschema
        // of een bestaande tijd selecteren.
        $schemas = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForDate($date);
        $tijden = array();
        foreach ($schemas as $schema) {
            $tijd = $schema->getDatum()->format('H:i');
            $actueel = $schema->getActueel();
            if ($actueel == 1 && $tijd != '00:00') {
                $tijden[] = $tijd;
            }
        }
        
        $tijden = array_unique($tijden);
        $lopers = [];
        
        // Aanpassing voor de beheerder:
        // De beheerder kan voortaan zelf lopers inplannen voor
        // een bepaalde datum.
        if ($isAdmin) {
            $conn = $this->get('database_connection');
            $sql = 'SELECT id FROM zabuto_usergroup WHERE name = "Loper"';
            $lopers = [];
            $group_id = $conn->fetchColumn($sql);
            
            // Genereer een lijst met lopers
            if ($group_id !== false) {
            
                $sql = 'SELECT g.user_id AS id, u.real_name AS naam
                        FROM zabuto_user_usergroup g, zabuto_user u 
                        WHERE g.group_id = :group_id AND u.id = g.user_id';
                
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('group_id', $group_id);
                $stmt->execute();
                
                $lopers = $stmt->fetchAll();
            }
        }
        
        $data = array(
            'entity' => $loopschema,
            'form' => $form->createView(),
            'action' => $this->generateUrl('buurtpreventie_loper_nieuwe_datum_schema_post', array('date' => $loopschema->getDatum()->format('Y-m-d'))),
            'afgemeld' => $afgemeld,
            'tijden' => $tijden,
            'lopers' => $lopers,
            'is_admin' => $isAdmin
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
        $isAdmin = false;
        $securityContext = $this->container->get('security.context');
        if ($securityContext->isGranted('ROLE_ADMIN')) {
            $isAdmin = true;
        }
        
        $errors = array();
        
        // Aanpassing i.v.m. meerdere looprondes per dag.
        // Een loopronde kan worden gekoppeld aan een tijd.
        // De standaard tijd is 00:00:00 (oude situatie).
        $time = $this->get('request')->request->get('time') . ':00';
        $date = "$date $time";
        
        // Aanpassing voor de Admin rol. De geselecteerde
        // lopers worden aan een loopronde toegevoegd.
        if ($isAdmin) {
            $lopers = $this->get('request')->request->get('lopers');
            $hasLopers = (is_array($lopers) && count($lopers) > 0);
            if ($hasLopers) {
                $repo = $this->get('doctrine')->getManager()->getRepository('ZabutoUserBundle:User');
                foreach ($lopers as $user_id) {
                    $user = $repo->find($user_id);
                    $errors = array_merge($errors, $this->_addLoopschema($date, $user));
                }
            } else {
                $errors[] = 'Selecteer &eacute;&eacute;n of meerdere lopers';
            }
        // Standaard functionaliteit: de ingelogde user wordt
        // aan een loopronde toegevoegd.
        } else {
            $user = $securityContext->getToken()->getUser();
            $errors = $this->_addLoopschema($date, $user);
        }
        
        if (count($errors) == 0) {
            return new JsonResponse(array('success' => true));
        }
        
        return new JsonResponse(array('success' => false, 'errors' => $errors));
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
     * Afmelding voor datum verwerken
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

        $afgemeld = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllInactiveForDate(new DateTime($date), $user);

        $form = $this->createForm(new LoopschemaAfmeldenFormType(), $loopschema);

        $form->submit($this->getRequest());
        if ($form->isValid()) {
            foreach ($afgemeld as $afmelding) {
                $em->remove($afmelding);
            }

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
     * Toevoegen van een loopschema
     *
     * @param string $date
     * @param User $user
     * @return array
     */
    private function _addLoopschema($date, $user)
    {    
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
            return array();
        }
        return $this->_getFormErrors($form);
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
        $isAdmin = false;
        $securityContext = $this->container->get('security.context');
        if ($securityContext->isGranted('ROLE_ADMIN')) {
            $isAdmin = true;
        }
        
        $user = $securityContext->getToken()->getUser();
        $userId = $user->getId();
        
        $em = $this->get('doctrine')->getManager();

        $minAantalLopers = $this->container->getParameter('loopschema_minimum_aantal_lopers');
        $today = date("Y-m-d");

        foreach ($list as $date => $info) {
            $info['editable'] = false;
            $info['toon_toelichting'] = false;
            $info['toon_resultaat'] = false;
            $info['toelichtingen'] = array();
            
            // Aanpassing i.v.m. meerdere looprondes / tijden
            // per dag. Eenzelfde loper kan zich aanmelden voor
            // meerdere rondes maar is voor het systeem 1 loper.
            $info['lopers'] = array_unique($info['lopers']);

            if ($date < $today) {
                if (count($info['lopers']) < $minAantalLopers) {
                    unset($list[$date]);
                } else {
                    // Aanpassing m.b.t. het invullen van een resultaat.
                    // Voorheen kon men enkel op de dag van de loopronde
                    // een resultaat toevoegen. Men kan nu ook achteraf
                    // een resultaat toevoegen.
                    // Oude situatie:
                    //   if $info['eigen_datum'] > 0 && false === $info['eigen_resultaat']
                    //   then ...
                    if (false === $info['eigen_resultaat']) {
                        $info['editable'] = 'resultaat';
                    }
                    
                    // Aanpassing voor de Admin rol. Een admin kan looprondes
                    // toevoegen voor anderen. De lopers kunnen vervolgens een 
                    // resultaat toevoegen. Die functie kunnen we in zo'n geval
                    // uitschakelen voor de admin.
                    if ($isAdmin) {
                        $k = 0;
                        $n = count($info['lopers']);
                        $found = false;
                        while (!$found && $k < $n) {
                            $found = ($userId == $info['lopers'][$k]->getId());
                            $k++;
                        }
                        if (!$found) $info['editable'] = false;
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
