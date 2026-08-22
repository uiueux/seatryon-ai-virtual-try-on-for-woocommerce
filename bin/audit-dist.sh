#!/usr/bin/env bash

set -euo pipefail

if [[ $# -ne 1 ]]; then
	printf 'Usage: %s <package-directory>\n' "$0" >&2
	exit 64
fi

package_dir="$(cd "$1" && pwd)"

required_files=(
	'sea-tryon.php'
	'readme.txt'
	'assets/build/frontend.js'
	'assets/build/frontend.css'
	'assets/build/frontend-rtl.css'
	'assets/build/frontend.asset.php'
	'assets/build/admin.js'
	'assets/build/admin.css'
	'assets/build/admin-rtl.css'
	'assets/build/admin.asset.php'
	'assets/build/virtual-try-on-editor.js'
	'assets/build/virtual-try-on-editor.asset.php'
)

for required_file in "${required_files[@]}"; do
	if [[ ! -f "$package_dir/$required_file" ]]; then
		printf 'Distribution audit failed: required runtime file is missing: %s\n' "$required_file" >&2
		exit 1
	fi
done

forbidden_path="$({
	find "$package_dir" -type d \( \
		-name .git -o \
		-name .github -o \
		-name sea-tryon-doc -o \
		-name tests -o \
		-name fixtures -o \
		-name node_modules -o \
		-name vendor \
	\) -print
	find "$package_dir" -type f \( \
		-name '*.map' -o \
		-name '.env' -o \
		-name '.env.*' -o \
		-name 'auth.json' -o \
		-name 'secrets.*' -o \
		-name '*.key' -o \
		-name '*.pem' \
	\) -print
} | head -n 1)"

if [[ -n "$forbidden_path" ]]; then
	printf 'Distribution audit failed: forbidden path found: %s\n' "$forbidden_path" >&2
	exit 1
fi

if grep -RIE --binary-files=without-match \
	-e 'sk-[A-Za-z0-9_-]{20,}' \
	-e '^(OPENAI_API_KEY|SEAAI_API_KEY)=[^[:space:]]+' \
	-e 'BEGIN ([A-Z ]+ )?PRIVATE KEY' \
	"$package_dir"; then
	printf 'Distribution audit failed: likely credential material found.\n' >&2
	exit 1
fi

printf 'Distribution audit passed: %s\n' "$package_dir"
