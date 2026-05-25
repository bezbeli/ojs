#!/usr/bin/env bash
#
# Laravel Forge deployment script for ojs.zemaljskimuzej.ba
# Paste the body below into Forge ? Site ? Deployment Script.
#
set -euo pipefail

cd /home/forge/ojs.zemaljskimuzej.ba/ojs

git pull origin "$FORGE_SITE_BRANCH"
git submodule sync --recursive
git submodule update --init --recursive

$FORGE_COMPOSER --working-dir=lib/pkp install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_COMPOSER --working-dir=plugins/generic/citationStyleLanguage install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_COMPOSER --working-dir=plugins/paymethod/paypal install --no-dev --no-interaction --prefer-dist --optimize-autoloader

HUSKY=0 npm ci
npm run build

php tools/upgrade.php upgrade

if [[ -d cache/t_compile ]]; then
  find cache/t_compile -mindepth 1 -delete
fi

if [[ -d cache/opcache ]]; then
  find cache/opcache -mindepth 1 -delete
fi

(
  flock -w 10 9 || exit 1
  echo 'Restarting FPM...'
  sudo -S service "$FORGE_PHP_FPM" reload
) 9>/tmp/fpmlock
