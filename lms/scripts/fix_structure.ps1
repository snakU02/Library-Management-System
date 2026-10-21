# fix_structure.ps1 — Remove redundant container-fluid div from pages
# since sidebar_admin/student now opens it and footer closes it
$lmsRoot = "c:\Users\Administrator\1CODES\Library Management System\lms"

$filePaths = @(
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
    "$lmsRoot\student\dashboard.php",
    "$lmsRoot\student\catalogue.php",
    "$lmsRoot\student\my_borrows.php",
    "$lmsRoot\student\my_penalties.php",
    "$lmsRoot\student\my_returns.php",
    "$lmsRoot\student\reserve.php",
    "$lmsRoot\student\search.php"
)

foreach ($f in $filePaths) {
    if (Test-Path $f) {
        $c = [System.IO.File]::ReadAllText($f, [System.Text.Encoding]::UTF8)

        # 1. Remove the old sidebar include + "Begin Page Content" + container-fluid open div
        # Pattern: after include sidebar, remove the stray container-fluid
        $c = $c -replace "(?s)(include\s+'\.\.\/includes\/sidebar_admin\.php';\s*\?>\s*<!--\s*Begin Page Content\s*-->\s*<div class=""container-fluid"">)", "include '../includes/sidebar_admin.php';`r`n?>"
        $c = $c -replace "(?s)(include\s+'\.\.\/includes\/sidebar_student\.php';\s*\?>\s*<!--\s*Begin Page Content\s*-->\s*<div class=""container-fluid"">)", "include '../includes/sidebar_student.php';`r`n?>"

        # 2. Remove the old sidebar include + bare container-fluid (without comment)
        $c = $c -replace "(?s)(include\s+'\.\.\/includes\/sidebar_admin\.php';\s*\?>\s*<div class=""container-fluid"">)", "include '../includes/sidebar_admin.php';`r`n?>"
        $c = $c -replace "(?s)(include\s+'\.\.\/includes\/sidebar_student\.php';\s*\?>\s*<div class=""container-fluid"">)", "include '../includes/sidebar_student.php';`r`n?>"

        # 3. Remove trailing "<!-- End Page Content -->" + closing </div> right before $page_scripts / include footer
        $c = $c -replace "\s*</div>\s*<!--\s*End Page Content\s*-->\s*\r?\n(\s*<\?php\s*\r?\n\s*\`$page_scripts)", "`r`n<?php`r`n`$page_scripts"
        $c = $c -replace "\s*</div>\s*<!--\s*End Page Content\s*-->", ""

        [System.IO.File]::WriteAllText($f, $c, [System.Text.Encoding]::UTF8)
        Write-Host "Processed: $f"
    } else {
        Write-Host "Not found: $f"
    }
}
Write-Host "Done."
