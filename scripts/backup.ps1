# Requires: mysqldump in PATH
param(
  [string]$DbHost = $env:DB_HOST, 
  [int]$DbPort = [int]($env:DB_PORT ? $env:DB_PORT : 3307),
  [string]$DbUser = $env:DB_USER,
  [string]$DbPass = $env:DB_PASS,
  [string]$DbName = ($env:DB_NAME ? $env:DB_NAME : 'hiddengems'),
  [string]$UploadsDir = ($env:UPLOADS_DIR ? $env:UPLOADS_DIR : 'public/uploads'),
  [string]$OutDir = ($env:OUT_DIR ? $env:OUT_DIR : 'backups')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $DbHost) { $DbHost = '127.0.0.1' }
if (-not $DbUser) { $DbUser = 'root' }

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
$ts = Get-Date -Format 'yyyyMMdd_HHmmss'
$sqlFile = Join-Path $OutDir "$DbName`_$ts.sql.gz"

Write-Host "Dumping MySQL to $sqlFile"
$passArg = $null; if ($DbPass) { $passArg = "-p$DbPass" }
$dumpCmd = @('mysqldump','-h', $DbHost, '-P', $DbPort, '-u', $DbUser)
if ($passArg) { $dumpCmd += $passArg }
$dumpCmd += '--single-transaction','--quick','--routines','--triggers', $DbName

$p = Start-Process -FilePath $dumpCmd[0] -ArgumentList $dumpCmd[1..($dumpCmd.Length-1)] -NoNewWindow -RedirectStandardOutput 'stdout.sql' -PassThru
$p.WaitForExit()
if ($p.ExitCode -ne 0) { throw "mysqldump failed with $($p.ExitCode)" }

Compress-Archive -Path 'stdout.sql' -DestinationPath $sqlFile -CompressionLevel Optimal
Remove-Item 'stdout.sql'

if (Test-Path $UploadsDir) {
  $zip = Join-Path $OutDir "uploads_$ts.zip"
  Write-Host "Archiving uploads to $zip"
  Compress-Archive -Path $UploadsDir -DestinationPath $zip -CompressionLevel Optimal
}

Write-Host "Backup completed."

