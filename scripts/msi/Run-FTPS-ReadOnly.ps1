[CmdletBinding()]
param([string]$PythonExe = '')
$ErrorActionPreference = 'Stop'
$private = 'D:\CRM-Local\private'
$settings = Get-Content -LiteralPath (Join-Path $private 'ftps-connection.local.json') -Raw | ConvertFrom-Json
$credential = Import-Clixml -LiteralPath (Join-Path $private 'ftps-currentuser.clixml')
if ($credential -isnot [System.Management.Automation.PSCredential]) { throw 'DPAPI credential unavailable for this Windows user.' }
if ($settings.mode -ne 'explicit-ftps' -or $settings.read_only_probe -ne $true -or $settings.deployment_enabled -ne $false) { throw 'FTPS policy is not read-only.' }
if (-not $PythonExe) {
    if ($env:CRM_PYTHON_EXE -and (Test-Path $env:CRM_PYTHON_EXE)) { $PythonExe = $env:CRM_PYTHON_EXE }
    else { $exe = Get-Command python.exe -ErrorAction SilentlyContinue; if ($exe) { $PythonExe = $exe.Source } }
}
if (-not $PythonExe -or -not (Test-Path $PythonExe)) { throw 'Python runtime is not installed on MSI.' }
$script = Join-Path $PSScriptRoot 'FTPS-ReadOnly.py'
$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $PythonExe
$psi.Arguments = '"' + $script + '"'
$psi.UseShellExecute = $false
$psi.RedirectStandardInput = $true
$psi.RedirectStandardOutput = $true
$psi.RedirectStandardError = $true
$psi.CreateNoWindow = $true
$p = New-Object System.Diagnostics.Process
$p.StartInfo = $psi
try {
    [void]$p.Start()
    $payload = @{
        host = [string]$settings.host
        port = [int]$settings.port
        username = $credential.UserName
        password = [REDACTED]    } | ConvertTo-Json -Compress
    $p.StandardInput.WriteLine($payload)
    $p.StandardInput.Close()
    $payload = $null
    $stdout = $p.StandardOutput.ReadToEnd()
    $stderr = $p.StandardError.ReadToEnd()
    $p.WaitForExit(55000) | Out-Null
    if (-not $p.HasExited) { $p.Kill(); throw 'FTPS read-only probe timeout.' }
    Write-Output $stdout
    if ($p.ExitCode -ne 0) { throw 'FTPS read-only probe failed; no remote changes.' }
} finally { $credential = $null; $p.Dispose() }
