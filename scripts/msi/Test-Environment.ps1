[CmdletBinding()]
param([string]$PhpExe = '', [string]$GitExe = '')
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if (-not $GitExe) {
    $g = Get-Command git.exe -ErrorAction SilentlyContinue
    if ($g) { $GitExe = $g.Source }
    else {
        $GitExe = 'C:\Program Files\Microsoft Visual Studio\2022\Enterprise\Common7\IDE\CommonExtensions\Microsoft\TeamFoundation\Team Explorer\Git\cmd\git.exe'
    }
}
if (-not $PhpExe) {
    if ($env:CRM_PHP_EXE -and (Test-Path $env:CRM_PHP_EXE)) { $PhpExe = $env:CRM_PHP_EXE }
    else {
        $p = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($p) { $PhpExe = $p.Source }
    }
}
$branch = $null; $sha = $null; $dirty = -1
if (Test-Path $GitExe) {
    $branch = (& $GitExe -C $root branch --show-current)
    $sha = (& $GitExe -C $root rev-parse HEAD)
    $dirty = @(& $GitExe -C $root status --porcelain).Count
}
$phpVersion = $null; $phpModules = @()
if ($PhpExe -and (Test-Path $PhpExe)) {
    $phpVersion = ((& $PhpExe -r 'echo PHP_VERSION;') | Select-Object -First 1)
    $phpModules = @(& $PhpExe -m)
}
$node = Get-Command node.exe -ErrorAction SilentlyContinue
$nodeExe = if ($node) { $node.Source } else { 'D:\DevTools\node-v26.13.1-win-x64\node.exe' }
$store = 'D:\CRM-Local'
$summary = [ordered]@{
    device = $env:COMPUTERNAME
    repository = $root
    branch = $branch
    sha = $sha
    dirty_items = $dirty
    git_available = (Test-Path $GitExe)
    php_available = [bool]$phpVersion
    php_version = $phpVersion
    pdo_mysql = [bool]($phpModules -match '^pdo_mysql$')
    zip = [bool]($phpModules -match '^zip$')
    mbstring = [bool]($phpModules -match '^mbstring$')
    node_available = (Test-Path $nodeExe)
    local_data_dir_present = (Test-Path $store)
    production_connection_checked = $false
    production_changes = $false
}
$summary | ConvertTo-Json -Depth 3
if (-not $summary.git_available) { exit 2 }
