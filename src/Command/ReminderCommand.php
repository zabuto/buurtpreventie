<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\RoundWalker;
use App\Service\CalendarService;
use App\Service\MailService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reminder', description: 'Send e-mail reminder to walkers.')]
class ReminderCommand extends Command
{
    public function __construct(
        private readonly CalendarService        $calendarService,
        private readonly MailService            $mailService,
        private readonly EntityManagerInterface $em,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, '', 1);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Send e-mail reminder to walkers</info>');

        $days = $input->getOption('days');
        $date = new DateTime();
        $date->add(new DateInterval(sprintf('P%sD', $days)));

        $walksForDate = $this->calendarService->getWalksForDateReminders($date);
        $output->writeln(sprintf('%s reminders for %s', count($walksForDate), $date->format('Y-m-d')));

        $count = 0;
        foreach ($walksForDate as $walkerSingleDay) {
            try {
                $this->mailService->sendReminder($walkerSingleDay);
                $count++;

                foreach ($walkerSingleDay->walking_ids as $roundWalkerId) {
                    $entity = $this->em->getRepository(RoundWalker::class)->find($roundWalkerId);
                    $entity?->setReminded(new DateTime());
                }

                $this->em->flush();
            } catch (Exception $e) {
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            }
        }

        $output->writeln(sprintf('%s e-mails sent', $count));

        return Command::SUCCESS;
    }
}
