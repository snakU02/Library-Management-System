# verify_structure.ps1 — Check all pages have proper container-fluid open and close
$lms = "c:\Users\Administrator\1CODES\Library Management System\lms"
$pages = @(
    "admin\dashboard.php","admin\books.php","admin\catalogue.php","admin\borrow.php","admin\return.php",
    "admin\penalties.php","admin\reports.php","admin\reservations.php",
    "admin\search.php","admin\students.php","admin\settings.php",
    "student\dashboard.php","student\catalogue.php","student\my_borrows.php",
    "student\my_penalties.php","student\my_returns.php","student\reserve.php","student\search.php"
)
foreach ($p in $pages) {
    $f = "$lms\$p"
    $c = Get-Content $f -Raw -ErrorAction SilentlyContinue
    $opensContainer  = ($c -split 'container-fluid">' | Measure-Object).Count - 1
    $closesContainer = ($c -split '</div><!-- /\.container-fluid -->' | Measure-Object).Count - 1
    $hasFooter = $c -match "include.*footer\.php"
    $hasSidebar = ($c -match "sidebar_admin") -or ($c -match "sidebar_student")
    $cLabel = if ($opensContainer -eq 1 -and $closesContainer -eq 1) { "container-OK" } else { "container-BAD(open=$opensContainer,close=$closesContainer)" }
    $fLabel = if ($hasFooter) { "footer-OK" } else { "MISSING-FOOTER" }
    $sLabel = if ($hasSidebar) { "sidebar-OK" } else { "MISSING-SIDEBAR" }
    Write-Host "[$cLabel][$fLabel][$sLabel] $p"
}
