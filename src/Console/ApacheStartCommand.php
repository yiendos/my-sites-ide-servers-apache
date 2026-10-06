<?php

namespace Yiendos\MySitesIde\Servers\Apache\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Apache\Traits\InteractsWithApache;

class ApacheStartCommand extends Command
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
            ->setName('servers:apache-start')
            ->setDescription('Start the Apache container - or recreate it if its compose config changed - testing the config first')
        ;
    }

    /**
     * Tests the config before starting - a broken one would otherwise leave
     * the container exiting straight after `up` with nothing on screen.
     *
     * Always runs `up -d`, even when apache is already running: it's a no-op
     * for an up-to-date container, and recreates one whose compose config
     * has changed - e.g. a container created before apache became a plugin,
     * whose httpd.conf mount points at a path that no longer exists. The
     * test runs in a throwaway container for the same reason, as the
     * running one may be the stale one.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->apacheConfigTest($output, fresh: true) !== 0) {
            $io->error('Apache configuration has errors - not starting.');
            return Command::FAILURE;
        }

        if ($this->compose($output, 'up -d apache') !== 0) {
            $io->error('Apache did not start - see above.');
            return Command::FAILURE;
        }

        $io->success('Apache started - https://default.localhost:8443');

        return Command::SUCCESS;
    }
}
