[CmdletBinding()]
param([string]$PhpExe = '', [switch]$SkipLint)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if (-not $PhpExe) {
    if ($env:CRM_PHP_EXE -and (Test-Path $env:CRM_PHP_EXE)) { $PhpExe = $env:CRM_PHP_EXE }
    else {
        $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($cmd) { $PhpExe = $cmd.Source }
    }
}
if (-not $PhpExe -or -not (Test-Path $PhpExe)) { throw 'PHP CLI not detected. Install PHP and set CRM_PHP_EXE to the absolute php.exe path.' }
$passed = 0; $failed = @()
Push-Location $root
try {
    if (-not $SkipLint) {
        $phpFiles = @(Get-ChildItem -Path (Join-Path $root 'crm') -Filter '*.php' -File -Recurse)
        foreach ($file in $phpFiles) {
            $null = & $PhpExe -l $file.FullName 2>&1
            if ($LASTEXITCODE -ne 0) { $failed += ('LINT:' + $file.FullName) }
            else { $passed++ }
        }
        Write-Host ('PHP lint: ' + $passed + ' passed / ' + $failed.Count + ' failed')
    }
    $tests = @(
        'contract_items_test.php',
        'deal_stage_history_test.php',
        'role_dashboard_test.php',
        'sales_record_authorization_test.php',
        'sales_record_authorization_behavior_test.php',
        'ticket_attachment_security_test.php',
        'private_ticket_backup_test.php'
    )
    foreach ($test in $tests) {
        $path = Join-Path $root ('crm\tests\' + $test)
        if (-not (Test-Path $path)) { $failed += ('MISSING:' + $test); continue }
        Write-Host ('Running: ' + $test)
        & $PhpExe $path
        if ($LASTEXITCODE -ne 0) { $failed += ('TEST:' + $test) }
        else { $passed++ }
    }
} finally { Pop-Location }
if ($failed.Count) {
    Write-Host ('Failed checks: ' + ($failed -join ', '))
    throw ('CRM checks failed: ' + $failed.Count)
}
Write-Host ('All local non-database checks passed: ' + $passed)
Write-Host 'MariaDB integration, HTTP and Playwright tests remain mandatory in CI.'
