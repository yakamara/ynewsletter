<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/console ynewsletter:send.
 *
 * Versendet alle Newsletter, deren Versandtermin erreicht ist. Für einen System-Cron
 * (z. B. jede Minute); überlappende Läufe blockieren sich über die Versandsperre.
 */
class rex_ynewsletter_command_send extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Sends all newsletters whose scheduled date has been reached')
            ->addOption('package-size', null, InputOption::VALUE_REQUIRED, 'E-mails per package (0 = all at once)', '100')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Seconds to wait between packages', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $packageSize = max(0, (int) $input->getOption('package-size'));
        $delay = max(0, (int) $input->getOption('delay'));

        foreach (rex_ynewsletter::sendDue($packageSize, $delay) as $message) {
            $io->text($message);
        }

        return self::SUCCESS;
    }
}
