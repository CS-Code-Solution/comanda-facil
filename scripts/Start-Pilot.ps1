param([int]$Port = 8000, [int]$ReverbPort = 8080)
$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskProcesses = @()
Push-Location -LiteralPath $taskRoot
try {
    foreach ($taskPort in @($Port, $ReverbPort)) {
        if ($taskPort -lt 1024 -or $taskPort -gt 65535) { throw 'Porta invalida.' }
        $taskListener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, $taskPort)
        try { $taskListener.Start() } finally { $taskListener.Stop() }
    }
    if (-not (Test-Path -LiteralPath 'public/build/manifest.json')) { throw 'Execute npm run build antes do piloto.' }
    & php artisan dev:database
    if ($LASTEXITCODE -ne 0) { throw 'Banco local indisponivel.' }
    & php scripts/pilot-preflight.php
    if ($LASTEXITCODE -ne 0) { throw 'Pre-verificacao recusou a inicializacao. Confira migracoes e build.' }
    $taskPhp = (Get-Command php).Source
    foreach ($taskArguments in @(
        @('-S', "127.0.0.1:$Port", '-t', 'public', 'scripts/pilot-router.php'),
        @('artisan', 'reverb:start', '--host=127.0.0.1', "--port=$ReverbPort"),
        @('artisan', 'schedule:work'),
        @('artisan', 'queue:work', '--sleep=1', '--tries=1')
    )) {
        $taskInfo = New-Object Diagnostics.ProcessStartInfo
        $taskInfo.FileName = $taskPhp
        $taskInfo.Arguments = $taskArguments -join ' '
        $taskInfo.WorkingDirectory = $taskRoot
        $taskInfo.UseShellExecute = $false
        $taskInfo.CreateNoWindow = $true
        $taskInfo.RedirectStandardOutput = $true
        $taskInfo.RedirectStandardError = $true
        $taskProcess = New-Object Diagnostics.Process
        $taskProcess.StartInfo = $taskInfo
        [void]$taskProcess.Start()
        [void]$taskProcess.StandardOutput.BaseStream.CopyToAsync([IO.Stream]::Null)
        [void]$taskProcess.StandardError.BaseStream.CopyToAsync([IO.Stream]::Null)
        $taskProcesses += $taskProcess
    }
    Write-Host "Piloto local: http://127.0.0.1:$Port - mantenha esta janela aberta. Ctrl+C encerra os processos iniciados aqui."
    Write-Host 'Nao execute outro emissor/scheduler. Confira Operacao e piloto no acesso do gestor.'
    while ($true) {
        foreach ($taskProcess in $taskProcesses) {
            if ($taskProcess.HasExited) { throw 'Um servico do piloto parou. Consulte o diagnostico e reinicie apos investigar.' }
        }
        Start-Sleep -Seconds 1
    }
} finally {
    foreach ($taskProcess in $taskProcesses) {
        if (-not $taskProcess.HasExited) {
            try { & taskkill.exe /PID $taskProcess.Id /T /F 2>$null | Out-Null } catch { }
        }
        $taskProcess.Dispose()
    }
    Pop-Location
}
