<?php

namespace Yiendos\MySitesIde\Servers\Apache\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Apache\Traits\InteractsWithApache;

class ApacheReloadCommand extends Command
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
            ->setName('servers:apache-reload')
            ->setDescription('Gracefully reload Apache to pick up config and vhost changes, testing the config first')
        ;
    }

    /**
     * A graceful restart (httpd -k graceful) re-reads the config without
     * dropping requests in flight, and only touches apache - unlike
     * ide:restart, which restarts every container.
     *
     * The config is tested first. `httpd -k graceful` would refuse a syntax
     * error on its own, but testing separately gives a clear failure, and
     * the hint for a container whose httpd.conf mount has gone stale.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->apacheRunning()) {
            $io->error('Apache is not running - start it with servers:apache-start.');
            return Command::FAILURE;
        }

        if ($this->apacheConfigTest($output) !== 0) {
            $io->error([
                'Apache configuration has errors - not reloading, the running server is untouched.',
                "If httpd.conf itself can't be opened, the container predates a compose change - recreate it with servers:apache-start.",
            ]);
            return Command::FAILURE;
        }

        if ($this->compose($output, 'exec -T apache httpd -k graceful') !== 0) {
            $io->error('Apache did not reload - see above.');
            return Command::FAILURE;
        }

        $io->success('Apache reloaded.');

        return Command::SUCCESS;
    }
}
