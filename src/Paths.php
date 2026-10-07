<?php

namespace Yiendos\MySitesIde\Servers\Apache;

use RuntimeException;

/**
 * Where this plugin's files live. The vhost stub ships inside the package,
 * while the vhosts generated from it belong to each site, under the IDE's
 * Repos/<site>/_build/config/ - where conf/httpd.conf includes them from.
 *
 * The IDE root comes from IDE_ROOT, which the my-sites-ide bootstrap sets
 * before any plugin command runs (and which docker-compose.yml interpolates).
 */
final class Paths
{
    /**
     * The my-sites-ide project root
     *
     * @return string
     */
    public static function root(): string
    {
        $root = getenv('IDE_ROOT');

        if ($root === false || $root === '') {
            throw new RuntimeException('IDE_ROOT is not set - run this command through the my-sites-ide CLI.');
        }

        return rtrim($root, '/');
    }

    /**
     * A file within a site's _build/config directory
     *
     * @param string $site
     * @param string $file
     * @return string
     */
    public static function siteConfig(string $site, string $file = ''): string
    {
        $path = self::root() . "/Repos/{$site}/_build/config";

        return $file === '' ? $path : "{$path}/{$file}";
    }

    /**
     * The site's application code relative to Repos/ (and /opt/repos in the
     * containers) - <site>/<IDE_APP_DIR>, which the IDE sets (deploy by
     * default, `.` for an app at the repository root, giving just <site>).
     * Falls back to deploy on an IDE that predates it.
     *
     * @param string $site
     * @return string
     */
    public static function siteApp(string $site): string
    {
        $app = trim((string) (getenv('IDE_APP_DIR') ?: 'deploy'), '/');

        return $app === '.' || $app === '' ? $site : "{$site}/{$app}";
    }

    /**
     * A file shipped with this package, e.g. stubs/sample.vhost
     *
     * @param string $file
     * @return string
     */
    public static function package(string $file = ''): string
    {
        return dirname(__DIR__) . ($file === '' ? '' : "/{$file}");
    }

    /**
     * An absolute path shown relative to the IDE root, for user-facing messages
     *
     * @param string $path
     * @return string
     */
    public static function relative(string $path): string
    {
        $root = self::root() . '/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
