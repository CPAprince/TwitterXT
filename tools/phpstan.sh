#!/usr/bin/env bash
set -euo pipefail

docker compose up -d php

docker compose exec php sh -lc '
set -euo pipefail

pkgs="phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine"
missing=""

for pkg in $pkgs; do
    if ! composer show "$pkg" >/dev/null 2>&1; then
        missing="$missing $pkg"
    fi
done

if [ -n "$missing" ]; then
    echo "Installing missing PHPStan packages:$missing"
    composer require --dev $missing
fi

CONTAINER_XML="var/cache/dev/App_KernelDevDebugContainer.xml"

if [ ! -f "$CONTAINER_XML" ]; then
    echo "Warming up dev cache..."
    if ! APP_ENV=dev APP_DEBUG=1 php bin/console cache:warmup --env=dev --no-interaction; then
        echo "Cache warmup failed, will try debug:container."
    fi
fi

if [ ! -f "$CONTAINER_XML" ]; then
    echo "Generating container XML via debug:container..."
    mkdir -p "$(dirname "$CONTAINER_XML")"
    if ! APP_ENV=dev APP_DEBUG=1 php bin/console debug:container \
        --env=dev \
        --format=xml \
        --no-interaction > "$CONTAINER_XML"
    then
        echo "Failed to generate container XML via debug:container."
        echo "Try: php bin/console debug:container --env=dev"
        exit 1
    fi
fi

if [ ! -f "$CONTAINER_XML" ]; then
    echo "Container XML is still missing at: $CONTAINER_XML"
    echo "Check dev env: php bin/console list --env=dev"
    exit 1
fi

echo "Running PHPStan via composer phpstan..."
composer phpstan
'
