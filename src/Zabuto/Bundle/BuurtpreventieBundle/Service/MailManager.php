<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Service;

use Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class MailManager
{
    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @var EngineInterface
     */
    private $templating;

    /**
     * @var string
     */
    private $address;

    /**
     * Constructor
     *
     * @param Swift_Mailer $mailer
     * @param EngineInterface $templating
     * @param string $address
     */
    public function __construct(\Swift_Mailer $mailer, EngineInterface $templating, $address)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->address = $address;
    }

    /**
     * Mail resultaat met bijzonderheden
     *
     * @param Loopschema $loopschema
     * @return boolean
     */
    public function mailLoopschemaResultaat(Loopschema $loopschema)
    {
        $bijzonderheden = $loopschema->getBijzonderheden();
        if (trim($bijzonderheden) === '') {
            return true;
        } else {
            $body = $this->templating->render('ZabutoBuurtpreventieBundle:Loopresultaat:resultmail.txt.twig', array('loopschema' => $loopschema));

            $message = \Swift_Message::newInstance();
            $message->setSubject('Loopresultaat ' . $loopschema->getDatum()->format('d-m-Y'));
            $message->setFrom($loopschema->getLoper()->getEmail(), $loopschema->getLoper()->getNaam());
            $message->setTo($this->address);
            $message->setBody($body);

            $recipients = $this->mailer->send($message);

            return ($recipients > 0) ? true : false;
        }
    }

    /**
     * Mail herinnering aan lopers
     *
     * @param integer minAantal
     * @param Loopschema[] $loopschemas
     * @param Looptoelichting[] $toelichtingen
     * @return boolean
     */
    public function mailLoopschemaReminder($minAantal, $loopschemas, $toelichtingen)
    {
        if (count($loopschemas) == 0) {
            return true;
        }

        $lopers = array();
        $addresses = array();
        foreach ($loopschemas as $loopschema) {
            $lopers[$loopschema->getLoper()->getNaam()] = $loopschema->getLoper();
            $addresses[$loopschema->getLoper()->getEmail()] = $loopschema->getLoper()->getNaam();
        }
        ksort($lopers);

        $title = (count($lopers) >= $minAantal) ? 'Herinnering deelname loopronde ' : 'Aanmelding loopronde ';
        $body = $this->templating->render('ZabutoBuurtpreventieBundle:Loperschema:remindermail.txt.twig', array('loopschema' => $loopschema, 'lopers' => $lopers, 'minAantal' => $minAantal, 'toelichtingen' => $toelichtingen));

        $message = \Swift_Message::newInstance();
        $message->setSubject($title . $loopschemas[0]->getDatum()->format('d-m-Y'));
        $message->setFrom($this->address);
        $message->setTo($this->address);
        $message->setBcc($addresses);
        $message->setBody($body);

        $recipients = $this->mailer->send($message);

        return ($recipients > 0) ? true : false;
    }
}
