<?php

namespace Yiendos\MySitesIde\Servers\Apache\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the apache compose service from the host. Every call goes through
 * `docker compose` from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithApache
{
    /**
     * Whether the apache container is up
     *
     * @return bool
     */
    protected function apacheRunning(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running apache 2>/dev/null')) !== '';
    }

    /**
     * Checks the config with `httpd -t` - in the running container when
     * there is one, otherwise (or when $fresh) a throwaway one built from
     * the current compose file, so a broken config can be caught before
     * start/reload ever touches the real server
     *
     * @param OutputInterface $output
     * @param string $flag -t (syntax) or -S (syntax plus the parsed vhosts)
     * @param bool $fresh test in a throwaway container even if apache is running
     * @return int the httpd exit code, 0 when the config is good
     */
    protected function apacheConfigTest(OutputInterface $output, string $flag = '-t', bool $fresh = false): int
    {
        $command = !$fresh && $this->apacheRunning()
            ? "exec -T apache httpd {$flag}"
            : "run --rm --no-deps -T apache httpd {$flag}";

        return $this->compose($output, $command);
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
