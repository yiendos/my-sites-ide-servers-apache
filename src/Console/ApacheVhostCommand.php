<?php

namespace Yiendos\MySitesIde\Servers\Apache\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Servers\Apache\Paths;

class ApacheVhostCommand extends Command
{
    /**
     * The placeholder in stubs/sample.vhost replaced with the site name
     */
    private const PLACEHOLDER = '__PROJECT__';

    /**
     * The placeholder replaced with the site's application code, relative to
     * /opt/repos - Paths::siteApp(), e.g. example/deploy
     */
    private const APP_PLACEHOLDER = '__APP_PATH__';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('servers:apache-vhost')
            ->setDescription("Create a site's Apache vhost (Repos/<site>/_build/config/1-<site>-apache.conf) from the sample")
            ->addArgument('site', InputArgument::REQUIRED, 'The site folder under Repos/, e.g. example')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Write the vhost even if the site already has an Apache config')
        ;
    }

    /**
     * Run by ide:create-site and ide:repo-clone through the plugin's
     * site-created hook, or by hand for a site that predates the plugin.
     *
     * A site that already has any *-apache.conf is left alone - a cloned
     * repository usually brings its own, and httpd loads every one it finds,
     * so a second vhost for the same ServerName would shadow it.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!is_dir(Paths::root() . "/Repos/{$site}")) {
            $io->error("Repos/{$site} does not exist - create or clone the site first.");
            return Command::FAILURE;
        }

        $existing = glob(Paths::siteConfig($site, '*-apache.conf')) ?: [];

        if ($existing !== [] && !$input->getOption('force')) {
            $io->warning('Apache vhost already present, leaving it alone (--force to write the sample anyway): '
                . implode(', ', array_map(Paths::relative(...), $existing)));
            return Command::SUCCESS;
        }

        if (!is_dir(Paths::siteConfig($site))) {
            mkdir(Paths::siteConfig($site), 0755, true);
        }

        $vhost = Paths::siteConfig($site, "1-{$site}-apache.conf");
        $sample = (string) file_get_contents(Paths::package('stubs/sample.vhost'));

        file_put_contents($vhost, strtr($sample, [self::APP_PLACEHOLDER => Paths::siteApp($site), self::PLACEHOLDER => $site]));

        $io->writeln("<info>Created " . Paths::relative($vhost) . "</> - https://{$site}.localhost:8443 once apache reloads (servers:apache-reload).");

        return Command::SUCCESS;
    }
}
