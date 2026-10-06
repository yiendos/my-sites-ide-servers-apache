# Apache server

Apache httpd 2.4 for [my-sites-ide](https://github.com/yiendos/my-sites-ide), serving every site in
`Repos/` over HTTPS on port 8443 and handing PHP to the IDE's `fpm` container. It runs alongside
the IDE's own nginx (443), or instead of it - each site picks up whichever servers have a vhost for it.

Written for: developers running sites in my-sites-ide who want them served by Apache, including
ones moving over from the version of Apache that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in Apache](#upgrading-from-the-built-in-apache)
- [Architecture](#architecture)
- [Site vhosts](#site-vhosts)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`:

```json
"yiendos/my-sites-ide-servers-apache": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide ide:build      # builds the ${NAMESPACE}_apache image, then sparks the IDE
```

Composer's `post-autoload-dump` hook registers the `servers:apache-*` commands, the `apache`
compose service, and the plugin's `site-created` hook. The service has `autostart: true`, so
`ide:spark` starts it alongside `APP` - you don't need to list `apache` in `APP`.

Then visit https://default.localhost:8443/.

## Upgrading from the built-in Apache

Apache used to live in the IDE at `_dev/environment/servers/apache/`. The image, config and
container name are unchanged, and existing sites' `*-apache.conf` vhosts keep working as they are.
The parts that moved:

| Before | Now |
|---|---|
| `_dev/environment/servers/apache/` | this package |
| `sample.vhost` | `stubs/sample.vhost` |
| `SERVERS="nginx apache"` decided whether new sites got an Apache vhost | installing the plugin does - `SERVERS` is only for the IDE's own servers now (`nginx`) |
| `apache` listed in `APP` | started by `autostart` |

A container created before the move still mounts `httpd.conf` from the old path, which no longer
exists: it keeps serving from the config already in memory, but every config test or reload fails
with `Could not open configuration file`. Recreate it once from the plugin's compose file:

```
php my-sites-ide servers:apache-start
```

Your existing `.env` can keep `apache` in `APP` and `SERVERS` while the plugin is installed - both
are ignored or de-duplicated. Remove them if you uninstall the plugin, or `ide:spark` will ask
compose for a service that no longer exists.

## Architecture

```
host (my-sites-ide CLI)
  |- ide:create-site / ide:repo-clone --> site-created hook --> servers:apache-vhost <site>
  |                                                             writes Repos/<site>/_build/config/1-<site>-apache.conf
  |- ide:spark / ide:restart          --> docker compose up / restart (apache included)
  |- servers:apache-test              --> httpd -t (or -S) in the running container, or a throwaway one
  |- servers:apache-reload            --> httpd -t, then httpd -k graceful in the running container
  |- servers:apache-start / -stop     --> docker compose up -d / stop apache

browser --https:8443--> apache container
                          |- conf/httpd.conf: IncludeOptional /opt/repos/*/_build/config/*-apache.conf
                          |- static files served straight from /opt/repos/<site>/Sites/public
                          |- *.php ---- ProxyPassMatch fcgi://fpm:9000 ----> IDE fpm container
```

The container runs as `www-data` (uid/gid 10015, set in the `Dockerfile`), listening on 8443
only - there's no plain-HTTP listener.

## Site vhosts

`conf/httpd.conf` loads every site's own vhosts, so a site is served once
`Repos/<site>/_build/config/` holds a `*-apache.conf` and apache has reloaded
(`php my-sites-ide servers:apache-reload`).

`ide:create-site` and `ide:repo-clone` create that vhost for you through this plugin's
`site-created` hook. It copies `stubs/sample.vhost` to `Repos/<site>/_build/config/1-<site>-apache.conf`,
replacing `__PROJECT__` with the site name:

- `ServerName <site>.localhost`, so https://<site>.localhost:8443
- `DocumentRoot /opt/repos/<site>/Sites/public`
- PHP proxied to `${FPM_HOST}`, using the same document root

A site that already has any `*-apache.conf` is left alone. A cloned repository usually brings its
own, and httpd loads every file it finds, so a second vhost for the same `ServerName` would shadow
it. Pass `--force` to write the sample anyway.

For sites that existed before the plugin was installed, run the command yourself:

```
php my-sites-ide servers:apache-vhost <site>
php my-sites-ide servers:apache-reload
```

## Command reference

| Command | What it does |
|---|---|
| `servers:apache-vhost <site>` | Create `Repos/<site>/_build/config/1-<site>-apache.conf` from the sample, unless the site already has an Apache vhost |
| `servers:apache-vhost <site> --force` | Same, writing the sample even when an Apache vhost exists (overwrites `1-<site>-apache.conf`) |
| `servers:apache-test` | Test the config, every site's vhost included (`httpd -t`). Uses the running container, or a throwaway one when apache is stopped |
| `servers:apache-test --vhosts` | Same, also listing the parsed vhosts (`httpd -S`) - which file each `ServerName`/`ServerAlias` comes from |
| `servers:apache-reload` | Test the config, then reload gracefully (`httpd -k graceful`) - requests in flight finish, and only apache is touched. Nothing is reloaded if the test fails |
| `servers:apache-start` | Test the config in a throwaway container, then `docker compose up -d apache`. Also recreates a running container whose compose config has changed |
| `servers:apache-stop` | `docker compose stop apache`, leaving the rest of the IDE running. The next `ide:spark` starts it again |

After editing a vhost, `servers:apache-reload` is quicker than `ide:restart`, which restarts every container.

## Configuration

The plugin has no `.env` options of its own. To change how Apache behaves:

- **One site**: edit its `Repos/<site>/_build/config/*-apache.conf` and run `servers:apache-reload`. This is
  the place for per-site rewrites, extra `ServerAlias`es or a real certificate.
- **All sites**: edit `conf/httpd.conf` in this package (modules, `IncludeOptional`, logging) and
  run `servers:apache-reload`. With a `Packages/` clone, that's your working copy; otherwise copy the change
  upstream, as `composer update` replaces `vendor/`.
- **The image**: edit the `Dockerfile` and rebuild with `docker compose build apache`.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `Repos/` | mounted at `/opt/repos` - the sites and their vhosts |
| `_dev/environment/servers/ssl/selfsigned.*` | the certificate, shared with nginx |
| the `fpm` service (`fpm:9000`) | PHP, through `ProxyPassMatch ... fcgi://${FPM_HOST}` |
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_apache` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | reaching the paths above from `vendor/` |
| the `my-sites-ide` network | reaching `fpm` |

## Troubleshooting

**https://<site>.localhost:8443 shows the default site, or the wrong one.** The site has no Apache
vhost, apache hasn't reloaded since it was added, or another vhost claims the same name first.
`php my-sites-ide servers:apache-test --vhosts` lists which file serves each name; then
`php my-sites-ide servers:apache-reload`.

**`Could not open configuration file /usr/local/apache2/conf/httpd.conf`.** The container was
created from an older compose file whose mount path no longer exists - see
[Upgrading from the built-in Apache](#upgrading-from-the-built-in-apache). `servers:apache-start` recreates it.

**`servers:apache-reload` or `-start` reports a syntax error.** The message names the file and line,
usually a site's `*-apache.conf`. The running server is left as it was until the error is fixed.

**Static files load but PHP pages return 503.** apache can't reach `fpm`. Make sure the `fpm`
service is running (`docker compose ps fpm`), as it's an IDE service and not part of this plugin.

**PHP pages say "File not found".** The fpm container resolves the path from the vhost's
`ProxyPassMatch`, so its document root has to match a path that exists inside fpm's
`/opt/repos`. Compare the `DocumentRoot` and `ProxyPassMatch` lines in the site's vhost.

**`ide:spark` fails with `no such service: apache`.** `APP` in `.env` still lists `apache`, but the
plugin isn't installed. Remove it from `APP`, or install the plugin.

**Port 8443 is already allocated.** Another container or host process has it. `docker ps --filter publish=8443`
shows which one.

**Browser certificate warning.** Expected, as the certificate is the IDE's self-signed one. Accept it
once per hostname.

## Known gaps

- The vhost stub assumes a Laravel-style `Sites/public` document root. Other layouts need their
  vhost edited after it's generated.
- `ide:ssl` (Certbot) prints nginx directives only. To use a Certbot certificate with Apache,
  mount `_dev/environment/certificates/certbot/conf` and point the site vhost's
  `SSLCertificateFile`/`SSLCertificateKeyFile` at it by hand.
- Unlike nginx, apache has no `IDE_SITE_ALIAS` network alias, so other containers (e.g. the
  zaproxy plugin) reach sites through nginx, not apache.
