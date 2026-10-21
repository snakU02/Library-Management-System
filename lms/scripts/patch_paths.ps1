# patch_paths.ps1 — Add paths.php include to all admin and student PHP pages
$lmsRoot = "c:\Users\Administrator\1CODES\Library Management System\lms"

$adminPages = @('books','catalogue','borrow','return','penalties','reports','reservations','search','students','settings')
$studentPages = @('catalogue','dashboard','my_borrows','my_penalties','my_returns','reserve','search')

function Patch-Page($filePath, $dbInclude) {
    if (Test-Path $filePath) {
        $content = [System.IO.File]::ReadAllText($filePath)
        if ($content -notlike "*config/paths.php*") {
            $pathsLine = "require_once '$dbInclude/../config/paths.php';"
            $content = $content -replace "require_once '$dbInclude/config/db\.php';", "require_once '$dbInclude/config/db.php';`r`nrequire_once '$dbInclude/config/paths.php';"
            [System.IO.File]::WriteAllText($filePath, $content, [System.Text.Encoding]::UTF8)
            Write-Host "Patched: $filePath"
        } else {
            Write-Host "Already patched: $filePath"
        }
    } else {
        Write-Host "Not found: $filePath"
    }
}

foreach ($page in $adminPages) {
    $f = "$lmsRoot\admin\$page.php"
    Patch-Page $f ".."
}

foreach ($page in $studentPages) {
    $f = "$lmsRoot\student\$page.php"
    Patch-Page $f ".."
}

Write-Host "Done."
