[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$privateDir = 'D:\CRM-Local\private'
if (-not (Test-Path $privateDir)) { New-Item -ItemType Directory -Path $privateDir -Force | Out-Null }
$configPath = Join-Path $privateDir 'ftps-connection.local.json'
$credentialPath = Join-Path $privateDir 'ftps-currentuser.clixml'
if ((Test-Path $configPath) -or (Test-Path $credentialPath)) { throw 'Local credentials already exist; refusing to overwrite them.' }
$hostValue = (Read-Host 'Enter current authorized CRM FTPS hostname (FQDN)').Trim()
if ($hostValue -notmatch '^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$') { throw 'Invalid full hostname.' }
$portText = (Read-Host 'FTPS port (Enter=21)').Trim()
$port = 21
if ($portText -ne '') {
    if (-not [int]::TryParse($portText, [ref]$port) -or $port -lt 1 -or $port -gt 65535) { throw 'Invalid port.' }
}
$credential = Get-Credential -Message 'Enter current CRM hosting FTPS username/password locally'
if ($null -eq $credential) { throw 'Credential entry cancelled.' }
$credential | Export-Clixml -LiteralPath $credentialPath
[ordered]@{
  host = $hostValue
  port = $port
  mode = 'explicit-ftps'
  read_only_probe = $true
  deployment_enabled = $false
} | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8
Write-Host 'Encrypted local Windows credentials prepared. No remote connection or deployment occurred.'
