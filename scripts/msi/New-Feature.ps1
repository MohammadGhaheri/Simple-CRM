[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$Name, [switch]$SkipFetch)
# Git emits normal progress to stderr in Windows PowerShell 5.1; inspect LASTEXITCODE explicitly.
$ErrorActionPreference = 'Continue'
if ($Name -cnotmatch '^[a-z0-9][a-z0-9-]{1,48}$') { throw 'Use lowercase slug with 2-49 letters, digits or hyphens.' }
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$git = (Get-Command git.exe -ErrorAction SilentlyContinue).Source
if (-not $git) { $git = 'C:\Program Files\Microsoft Visual Studio\2022\Enterprise\Common7\IDE\CommonExtensions\Microsoft\TeamFoundation\Team Explorer\Git\cmd\git.exe' }
if (-not (Test-Path $git)) { throw 'Git executable is missing.' }
if (@(& $git -C $root status --porcelain).Count -ne 0) { throw 'Commit/stash your changes before starting a branch.' }
$branch = 'feature/' + $Name
& $git -C $root show-ref --verify --quiet ('refs/heads/' + $branch)
if ($LASTEXITCODE -eq 0) { throw 'Feature branch already exists.' }
if (-not $SkipFetch) {
    & $git -C $root fetch origin mammut-connect
    if ($LASTEXITCODE -ne 0) { throw 'Fetch failed; no branch created.' }
}
& $git -C $root switch mammut-connect
if ($LASTEXITCODE -ne 0) { throw 'Could not switch to product base branch.' }
if (-not $SkipFetch) {
    & $git -C $root merge --ff-only origin/mammut-connect
    if ($LASTEXITCODE -ne 0) { throw 'Cannot fast-forward base; manual review required.' }
}
& $git -C $root switch -c $branch
if ($LASTEXITCODE -ne 0) { throw 'Could not create feature branch.' }
Write-Host ('Feature branch created on MSI: ' + $branch)
