# verify_includes.ps1
$lms = "c:\Users\Administrator\1CODES\Library Management System\lms"
$pages = @(
    "admin\books.php","admin\catalogue.php","admin\borrow.php","admin\return.php",
    "admin\penalties.php","admin\reports.php","admin\reservations.php",
    "admin\search.php","admin\students.php","admin\settings.php",
    "student\dashboard.php","student\catalogue.php","student\my_borrows.php",
    "student\my_penalties.php","student\my_returns.php","student\reserve.php","student\search.php"
)
foreach ($p in $pages) {
    $f = "$lms\$p"
    $c = Get-Content $f -Raw -ErrorAction SilentlyContinue
    $hasPaths  = $c -match "require_once.*paths\.php"
    $hasDb     = $c -match "require_once.*db\.php"
    $hasHeader = $c -match "include.*header\.php"
    $pLabel    = if ($hasPaths)  { "paths-OK " } else { "MISSING-paths" }
    $dLabel    = if ($hasDb)     { "db-OK    " } else { "MISSING-db   " }
    $hLabel    = if ($hasHeader) { "header-OK" } else { "MISSING-hdr  " }
    Write-Host "[$pLabel][$dLabel][$hLabel] $p"
}
