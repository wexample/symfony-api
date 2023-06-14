#!/usr/bin/env bash

_wexLog "Running custom app scripts"
# List and sort the tags
ALL_TAGS=$(git tag -l | sort -V)

# Get the latest tag
LATEST_TAG=$(echo "$ALL_TAGS" | tail -n 1)

CURRENT_VERSION=v$(grep -oP '"version": "\K[^"]+' composer.json)

rm -rf .env.skip

# Check if CURRENT_VERSION is the latest
if [ "$CURRENT_VERSION" != "$LATEST_TAG" ]; then
    _wexLog "The current version ($CURRENT_VERSION) is not the latest tag.. stopping pipeline"
    echo "SKIP_BUILD=true" >> .env.skip
else
    _wexLog "The CURRENT_VERSION ($CURRENT_VERSION) is the latest tag.. continue"
fi