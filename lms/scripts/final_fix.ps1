# final_fix.ps1 — Fix include paths and HTML structure in all LMS pages
# Run from: PowerShell as administrator
# Command: powershell -File "c:\Users\Administrator\1CODES\Library Management System\lms\scripts\final_fix.ps1"

$lmsRoot = "c:\Users\Administrator\1CODES\Library Management System\lms"

$allPages = @(
    "$lmsRoot\admin\dashboard.php",
    "$lmsRoot\admin\books.php",
    "$lmsRoot\admin\catalogue.php",
    "$lmsRoot\admin\borrow.php",
    "$lmsRoot\admin\return.php",
    "$lmsRoot\admin\penalties.php",
    "$lmsRoot\admin\reports.php",
    "$lmsRoot\admin\reservations.php",
    "$lmsRoot\admin\search.php",
    "$lmsRoot\admin\students.php",
    "$lmsRoot\admin\settings.php",
    "$lmsRoot\student\dashboard.php",
    "$lmsRoot\student\catalogue.php",
    "$lmsRoot\student\my_borrows.php",
    "$lmsRoot\student\my_penalties.php",
    "$lmsRoot\student\my_returns.php",
    "$lmsRoot\student\reserve.php",
    "$lmsRoot\student\search.php"
)

foreach ($f in $allPages) {
    if (-not (Test-Path $f)) {
        Write-Host "SKIP (not found): $f"
        continue
    }

    $c = [System.IO.File]::ReadAllText($f, [System.Text.Encoding]::UTF8)
    $changed = $false

    # Fix 1: Remove old "<!-- Begin Page Content -->" comment after sidebar include (sidebar now just outputs nav)
    if ($c -match "<!-- Begin Page Content -->") {
        $c = $c -replace "\s*<!--\s*Begin Page Content\s*-->\s*", "`r`n"
        $changed = $true
    }

    # Fix 2: Remove "<!-- End Page Content -->" with its extra closing </div>
    # This pattern: </div>\n<!-- End Page Content -->
    if ($c -match "<!-- End Page Content -->") {
        $c = $c -replace "\s*</div>\s*\r?\n\s*<!--\s*End Page Content\s*-->\s*", "`r`n"
        $changed = $true
    }

    if ($changed) {
        [System.IO.File]::WriteAllText($f, $c, [System.Text.Encoding]::UTF8)
        Write-Host "Fixed: $f"
    } else {
        Write-Host "OK: $f"
    }
}
Write-Host "All done."
