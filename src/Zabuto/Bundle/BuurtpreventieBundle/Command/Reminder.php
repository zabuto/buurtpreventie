<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;
use Exception;

class Reminder extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('zabutobuurtpreventie:reminder')
            ->setDescription('Reminder voor lopers')
            ->addArgument('days-in-future', InputArgument::REQUIRED, 'Aantal dagen vooruit kijken (0 = vandaag)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $minLopers = $this->getContainer()->getParameter('loopschema_minimum_aantal_lopers');

            $days = $input->getArgument('days-in-future');

            $date = new DateTime('now');
            if (is_numeric($days) && $days > 0) {
                $date->modify('+' . $days . ' day');
            }

            $em = $this->getContainer()->get('doctrine')->getManager();
            $loopschemas = $em->getRepository('ZabutoBuurtpreventieBundle:Loopschema')->findAllActiveForDate($date);
            $toelichtingen = $em->getRepository('ZabutoBuurtpreventieBundle:Looptoelichting')->findForDate($date);

            $mailManager = $this->getApplication()->getKernel()->getContainer()->get('zabuto_buurtpreventie.mailmanager');
            $result = $mailManager->mailLoopschemaReminder($minLopers, $loopschemas, $toelichtingen);

            $text = '[' . (($result) ? 'OK' : 'ERROR') . '] Reminder ' . $date->format('d-m-Y') . ': ' . count($loopschemas) . ' ' . ((count($loopschemas) == 1) ? 'loper' : 'lopers');
            $output->writeln($text);
        } catch (Exception $e) {
            $output->writeln('[ERROR] Reminder ' . $e->getMessage());
        }
    }
}
