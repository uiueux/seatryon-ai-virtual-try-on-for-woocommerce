param(
	[Parameter(Position = 0)]
	[string] $Version = '1.1.2',

	[switch] $FolderOnly
)

$ErrorActionPreference = 'Stop'

if ($Version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$') {
	throw "Invalid version: $Version"
}

$Root = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$Dist = Join-Path $Root 'dist'
$Stage = Join-Path $Dist 'seatryon-ai-virtual-try-on-for-woocommerce'
$Zip = Join-Path $Dist "seatryon-ai-virtual-try-on-for-woocommerce-$Version.zip"
$Checksum = "$Zip.sha256"

$ExcludedDirectories = @(
	'.git', '.github', '.idea', '.vscode', '.wp-env',
	'bin', 'coverage', 'dist', 'fixtures', 'node_modules',
	'scripts', 'stubs', 'tests', 'vendor', 'sea-tryon-doc'
)
$ExcludedPrefixes = @('assets/src/', 'build/')
$ExcludedFiles = @(
	'.distignore', '.gitattributes', '.gitignore', '.wp-env.json', 'CHANGELOG.md', 'HANDOFF.md', 'README.md', 'design-qa.md',
	'.phpcs-cache', '.phpstan.cache', '.phpunit.cache', '.phpunit.result.cache',
	'.DS_Store', 'Thumbs.db',
	'auth.json', 'composer.json', 'composer.lock', 'package.json',
	'package-lock.json', 'phpcs.xml', 'phpcs.xml.dist', 'phpstan.neon',
	'phpstan.neon.dist', 'phpunit.xml', 'phpunit.xml.dist', 'webpack.config.js'
)

function Test-ExcludedPath {
	param([string] $RelativePath)

	$normalized = $RelativePath.Replace('\', '/')
	foreach ($prefix in $ExcludedPrefixes) {
		if ($normalized.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
			return $true
		}
	}

	$parts = $normalized -split '/'
	if ($parts | Where-Object { $ExcludedDirectories -contains $_ }) {
		return $true
	}

	$name = [System.IO.Path]::GetFileName($RelativePath)
	if ($ExcludedFiles -contains $name) {
		return $true
	}

	return $name -match '(^\.env($|\.)|^secrets\.|\.(key|pem|log|map|zip)$)'
}

if (Test-Path -LiteralPath $Stage) {
	Remove-Item -LiteralPath $Stage -Recurse -Force
}
if (-not $FolderOnly) {
	foreach ($file in @($Zip, $Checksum)) {
		if (Test-Path -LiteralPath $file) {
			Remove-Item -LiteralPath $file -Force
		}
	}
}
New-Item -ItemType Directory -Path $Stage -Force | Out-Null

$sourceFiles = Get-ChildItem -LiteralPath $Root -File -Recurse | Where-Object {
	$relative = $_.FullName.Substring($Root.Length + 1)
	-not (Test-ExcludedPath $relative)
}

foreach ($source in $sourceFiles) {
	$relative = $source.FullName.Substring($Root.Length + 1)
	$target = Join-Path $Stage $relative
	$targetDirectory = Split-Path -Parent $target
	if (-not (Test-Path -LiteralPath $targetDirectory)) {
		New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
	}
	Copy-Item -LiteralPath $source.FullName -Destination $target
}

$RequiredFiles = @(
	'sea-tryon.php',
	'readme.txt',
	'assets/build/frontend.js',
	'assets/build/frontend.css',
	'assets/build/frontend-rtl.css',
	'assets/build/frontend.asset.php',
	'assets/build/admin.js',
	'assets/build/admin.css',
	'assets/build/admin-rtl.css',
	'assets/build/admin.asset.php',
	'assets/build/virtual-try-on-editor.js',
	'assets/build/virtual-try-on-editor.asset.php'
)
foreach ($required in $RequiredFiles) {
	if (-not (Test-Path -LiteralPath (Join-Path $Stage $required) -PathType Leaf)) {
		throw "Distribution audit failed: required runtime file is missing: $required"
	}
}

$forbidden = Get-ChildItem -LiteralPath $Stage -File -Recurse | Where-Object {
	$relative = $_.FullName.Substring($Stage.Length + 1)
	Test-ExcludedPath $relative
} | Select-Object -First 1
if ($forbidden) {
	throw "Distribution audit failed: forbidden path found: $($forbidden.FullName)"
}

$credentialPattern = 'sk-[A-Za-z0-9_-]{20,}|(^|\r?\n)(OPENAI_API_KEY|SEAAI_API_KEY)=[^\s]+|BEGIN ([A-Z ]+ )?PRIVATE KEY'
foreach ($file in Get-ChildItem -LiteralPath $Stage -File -Recurse) {
	if ($file.Length -gt 5MB) {
		continue
	}
	$content = [System.IO.File]::ReadAllText($file.FullName)
	if ($content -match $credentialPattern) {
		throw "Distribution audit failed: likely credential material found in $($file.FullName)"
	}
}

Write-Output "Distribution audit passed: $Stage"
if ($FolderOnly) {
	Write-Output "Created release folder: $Stage"
	exit 0
}

Add-Type -AssemblyName System.IO.Compression
$stream = [System.IO.File]::Open($Zip, [System.IO.FileMode]::CreateNew)
try {
	$archive = [System.IO.Compression.ZipArchive]::new(
		$stream,
		[System.IO.Compression.ZipArchiveMode]::Create,
		$false
	)
	try {
		$fixedTime = [DateTimeOffset]::new(2026, 8, 9, 0, 0, 0, [TimeSpan]::Zero)
		foreach ($file in Get-ChildItem -LiteralPath $Stage -File -Recurse | Sort-Object FullName) {
			$relative = $file.FullName.Substring($Stage.Length + 1).Replace('\', '/')
			$entry = $archive.CreateEntry("seatryon-ai-virtual-try-on-for-woocommerce/$relative", [System.IO.Compression.CompressionLevel]::Optimal)
			$entry.LastWriteTime = $fixedTime
			$input = [System.IO.File]::OpenRead($file.FullName)
			$output = $entry.Open()
			try {
				$input.CopyTo($output)
			} finally {
				$output.Dispose()
				$input.Dispose()
			}
		}
	} finally {
		$archive.Dispose()
	}
} finally {
	$stream.Dispose()
}

$hash = (Get-FileHash -LiteralPath $Zip -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath $Checksum -Value "$hash  $([System.IO.Path]::GetFileName($Zip))" -Encoding ascii -NoNewline

Write-Output "Created release package: $Zip"
Write-Output "SHA256: $hash"
