#!/usr/bin/env bash
# Stop and remove the dev stack including its database volumes.
ENGINE="${ENGINE:-$(command -v podman || command -v docker)}"
POD=task-table-dev
"$ENGINE" pod rm -f "$POD"
"$ENGINE" volume rm -f "${POD}-wp" "${POD}-db"
