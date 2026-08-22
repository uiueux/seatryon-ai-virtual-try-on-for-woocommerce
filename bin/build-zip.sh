#!/usr/bin/env bash

set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="${1:-1.1.0}"

if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	printf 'Invalid version: %s\n' "$version" >&2
	exit 64
fi

for command_name in rsync zip; do
	if ! command -v "$command_name" >/dev/null 2>&1; then
		printf 'Required command is unavailable: %s\n' "$command_name" >&2
		exit 69
	fi
done

dist_dir="$root_dir/dist"
stage_dir="$dist_dir/sea-tryon"
zip_file="$dist_dir/sea-tryon-$version.zip"

mkdir -p "$dist_dir"
rm -rf "$stage_dir"
rm -f "$zip_file"
mkdir -p "$stage_dir"

rsync \
	--archive \
	--delete \
	--exclude-from="$root_dir/.distignore" \
	"$root_dir/" \
	"$stage_dir/"

bash "$root_dir/bin/audit-dist.sh" "$stage_dir"

(
	cd "$dist_dir"
	zip -qr "$(basename "$zip_file")" sea-tryon
)

printf 'Created release package: %s\n' "$zip_file"
