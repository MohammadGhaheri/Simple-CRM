[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$ApprovedCommit)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$git = (Get-Command git.exe -ErrorAction SilentlyContinue).Source
if (-not $git) { $git = 'C:\Program Files\Microsoft Visual Studio\2022\Enterprise\Common7\IDE\CommonExtensions\Microsoft\TeamFoundation\Team Explorer\Git\cmd\git.exe' }
if (-not (Test-Path $git)) { throw 'Git executable missing.' }
if (@(& $git -C $root status --porcelain).Count -ne 0) { throw 'Refusing package from dirty worktree.' }
$branch = (& $git -C $root branch --show-current).Trim()
if ($branch -ne 'mammut-connect') { throw 'Only approved product branch can be packaged.' }
$head = (& $git -C $root rev-parse HEAD).Trim()
if ($ApprovedCommit -ne $head) { throw 'Supplied reviewed commit must equal local product HEAD.' }
$outRoot = 'D:\CRM-Local\artifacts'
if (-not (Test-Path $outRoot)) { New-Item -ItemType Directory -Path $outRoot -Force | Out-Null }
$archive = Join-Path $outRoot ('crm-' + $head.Substring(0,12) + '.zip')
$manifest = Join-Path $outRoot ('crm-' + $head.Substring(0,12) + '.manifest.json')
if ((Test-Path $archive) -or (Test-Path $manifest)) { throw 'Artifact exists: never overwrite an earlier release.' }
& $git -C $root archive --format=zip ('--output=' + $archive) HEAD crm
if ($LASTEXITCODE -ne 0) { throw 'Archive creation failed.' }
$report = [ordered]@{
  commit = $head
  source_branch = $branch
  created_utc = [DateTime]::UtcNow.ToString('o')
  artifact_file = [IO.Path]::GetFileName($archive)
  sha256 = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
  status = 'PACKAGE_ONLY_NOT_DEPLOYED'
  production_migrations_executed = $false
  production_file_uploads_executed = $false
}
$report | ConvertTo-Json | Set-Content -LiteralPath $manifest -Encoding UTF8
Write-Output ($report | ConvertTo-Json -Compress)
