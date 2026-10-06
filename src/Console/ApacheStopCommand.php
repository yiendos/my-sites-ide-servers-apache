<?php

namespace Yiendos\MySitesIde\Servers\Apache\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Apache\Traits\InteractsWithApache;

class ApacheStopCommand extends Command
{
    use InteractsWithApache;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:apache-stop')
            ->setDescription('Stop the Apache container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so servers:apache-start brings back the
     * same container. The next ide:spark starts it again (autostart).
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->apacheRunning()) {
            $io->writeln('Apache is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop apache') !== 0) {
            $io->error('Apache did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Apache stopped.');

        return Command::SUCCESS;
    }
}
