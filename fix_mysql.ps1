$ErrorActionPreference = 'Stop'
$dataDir = "c:\xampp\mysql\data"
$backupDir = "c:\xampp\mysql\backup"
$corruptDir = "c:\xampp\mysql\data_corrupt_" + (Get-Date -Format "yyyyMMdd_HHmmss")

Write-Host "Renaming corrupted data directory to $corruptDir..."
Rename-Item -Path $dataDir -NewName $corruptDir

Write-Host "Restoring fresh data directory from backup..."
Copy-Item -Path $backupDir -Destination $dataDir -Recurse

Write-Host "Copying user databases back..."
$excludeDirs = @("mysql", "performance_schema", "phpmyadmin", "test")
$dirs = Get-ChildItem -Path $corruptDir -Directory
foreach ($d in $dirs) {
    if ($excludeDirs -notcontains $d.Name) {
        Write-Host "Restoring database: $($d.Name)"
        Copy-Item -Path $d.FullName -Destination "$dataDir\$($d.Name)" -Recurse
    }
}

Write-Host "Restoring ibdata1 tablespace..."
Copy-Item -Path "$corruptDir\ibdata1" -Destination "$dataDir\ibdata1" -Force

Write-Host "Repair complete! You can now start MySQL in XAMPP GUI."
