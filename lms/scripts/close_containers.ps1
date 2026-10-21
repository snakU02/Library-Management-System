# close_containers.ps1 — Ensure all pages properly close their container-fluid divs
# Each page opens <div class="container-fluid"> and must close it before include footer

$lms = "c:\Users\Administrator\1CODES\Library Management System\lms"

$adminPages = @('dashboard','books','catalogue','borrow','return','penalties','reports','reservations','search','students','settings')
$studentPages = @('dashboard','catalogue','my_borrows','my_penalties','my_returns','reserve','search')

function Fix-ContainerClose($filePath) {
    $content = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)
    
    # Check if container-fluid is opened
    if ($content -notmatch '<div class="container-fluid">') {
        Write-Host "NO container-fluid found in: $filePath"
        return
    }
    
    # Check if it's already properly closed with comment
    if ($content -match '<!-- /\.container-fluid -->') {
        Write-Host "Already fixed: $filePath"
        return
    }
    
    # Find the last include footer or page_scripts before footer and insert closing div before it
    # Strategy: insert </div><!-- /.container-fluid --> before the final include footer
    
    # Pattern 1: $page_scripts = '...'; include '../includes/footer.php';
    # Pattern 2: include '../includes/footer.php';
    
    $pattern1 = [regex]::Escape('<?php') + "\r?\n" + [regex]::Escape('$page_scripts')
    $pattern2 = [regex]::Escape("include '../includes/footer.php';")
    
    if ($content -match '(?s)\$page_scripts\s*=') {
        # Has page_scripts before footer
        # Find last occurrence of $page_scripts assignment
        $idx = $content.LastIndexOf('$page_scripts')
        if ($idx -gt 0) {
            # Go back to find the <?php before it
            $phpIdx = $content.LastIndexOf('<?php', $idx)
            if ($phpIdx -gt 0) {
                $content = $content.Substring(0, $phpIdx) + "`r`n</div><!-- /.container-fluid -->`r`n" + $content.Substring($phpIdx)
                [System.IO.File]::WriteAllText($filePath, $content, [System.Text.Encoding]::UTF8)
                Write-Host "Fixed (page_scripts): $filePath"
                return
            }
        }
    }
    
    # Pattern 2: simple include footer at end
    $idx = $content.LastIndexOf("include '../includes/footer.php';")
    if ($idx -gt 0) {
        # Find the <?php before this include
        $phpIdx = $content.LastIndexOf('<?php', $idx)
        if ($phpIdx -gt 0) {
            $content = $content.Substring(0, $phpIdx) + "`r`n</div><!-- /.container-fluid -->`r`n" + $content.Substring($phpIdx)
            [System.IO.File]::WriteAllText($filePath, $content, [System.Text.Encoding]::UTF8)
            Write-Host "Fixed (direct footer): $filePath"
            return
        }
    }
    
    Write-Host "COULD NOT FIX: $filePath"
}

foreach ($pg in $adminPages) {
    $f = "$lms\admin\$pg.php"
    if (Test-Path $f) { Fix-ContainerClose $f }
    else { Write-Host "Not found: $f" }
}

foreach ($pg in $studentPages) {
    $f = "$lms\student\$pg.php"
    if (Test-Path $f) { Fix-ContainerClose $f }
    else { Write-Host "Not found: $f" }
}

Write-Host "Complete."
