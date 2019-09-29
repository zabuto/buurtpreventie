<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\RoundWalker;
use App\Exception\MailException;
use App\Model\WalkerDayModel;
use App\Service\CalendarService;
use App\Service\MailService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ReminderCommand
 */
class ReminderCommand extends Command
{
    /**
     * @var CalendarService
     */
    private $calendarService;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * Constructor
     *
     * @param  CalendarService        $calendarService
     * @param  MailService            $mailService
     * @param  EntityManagerInterface $em
     * @param  string|null            $name
     */
    public function __construct(CalendarService $calendarService, MailService $mailService, EntityManagerInterface $em, string $name = null)
    {
        $this->calendarService = $calendarService;
        $this->mailService = $mailService;
        $this->em = $em;

        parent::__construct($name);
    }

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('app:reminder')
            ->setDescription('Send e-mail reminder to walkers.')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, '', 1);
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Send e-mail reminder to walkers</info>');

        $days = $input->getOption('days');
        $date = new DateTime();
        $date->add(new DateInterval(sprintf('P%sD', $days)));

        $walksForDate = $this->calendarService->getWalksForDate($date);

        $output->writeln(sprintf('%s reminders for %s', count($walksForDate), $date->format('Y-m-d')));

        $count = 0;
        /** @var WalkerDayModel $walk */
        foreach ($walksForDate as $walkForDate) {
            try {
                $this->mailService->sendReminder($walkForDate);
                $count++;

                foreach ($walkForDate->getWalks() as $walk) {
                    $entity = $this->em->getRepository(RoundWalker::class)->find($walk->getId());
                    $entity->setReminded(new DateTime());
                }

                $this->em->flush();
            } catch (MailException $e) {
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            } catch (Exception $e) {
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            }
        }

        $output->writeln(sprintf('%s e-mails sent', $count));
    }
}
