#!/usr/bin/env bash
# Stop and remove the dev stack including its database volumes. POD selects another stack.
ENGINE="${ENGINE:-podman}"
POD="${POD:-task-table-dev}"
"$ENGINE" pod rm -f "$POD"
"$ENGINE" volume rm -f "${POD}-wp" "${POD}-db"
