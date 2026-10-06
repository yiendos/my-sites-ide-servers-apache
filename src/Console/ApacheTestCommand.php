<?php

namespace Yiendos\MySitesIde\Servers\Apache\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Apache\Traits\InteractsWithApache;

class ApacheTestCommand extends Command
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
            ->setName('servers:apache-test')
            ->setDescription("Test Apache's configuration, including every site's *-apache.conf")
            ->addOption('vhosts', null, InputOption::VALUE_NONE, 'Also list the parsed vhosts (httpd -S) - handy for spotting a ServerName two sites share')
        ;
    }

    /**
     * Works whether or not apache is running, so a vhost can be checked
     * before the server ever loads it
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if ($this->apacheConfigTest($output, $input->getOption('vhosts') ? '-S' : '-t') !== 0) {
            $io->error('Apache configuration has errors - see above.');
            return Command::FAILURE;
        }

        $io->success('Apache configuration is valid.');

        return Command::SUCCESS;
    }
}
