<#
.SYNOPSIS
    Refresh the README's screenshots (docs/screenshots) from a local dev
    server, using headless Microsoft Edge. Not part of the add-on.

.DESCRIPTION
    For Windows with the dev server running in WSL (see DEVELOPMENT.md):

      1. In WSL, from the repo root, seed made-up visitors:
           export VISITORS_DB_PATH=/tmp/visitors-dev.db
           php tests/seed_demo_data.php --force
      2. In WSL, start the dev server:
           cd visitors_addon/files/general/www/public && php -S 127.0.0.1:8421
      3. In PowerShell, from the repo root:
           powershell -ExecutionPolicy Bypass -File .\tests\capture_screenshots.ps1
         or just some of them:
           ... -File .\tests\capture_screenshots.ps1 -Only tablet-sign-in,phone-sign-out

    Each shot sets the theme, loads a page, optionally runs a setup script
    (filling the form, searching for a visitor, logging in), then saves a
    PNG. Tablet shots are a 1180 x 820 screen, phone shots 390 x 844, both
    at 2x. Admin shots are 1280 wide and as tall as the page (up to a limit).

    Admin shots log in with -AdminPassword. With no options.json the dev
    server uses the default password, and the admin page shows its "default
    password" warning. To leave that out, put an options.json with your own
    admin_password in the public folder (git ignores it).

    For a one-off shot while working on the pages, pass -Url (and optionally
    -Width, -Height, -Scale, -Theme, -Name, -Mobile, -Setup).
#>
param(
    [string]$OutDir = (Join-Path $PSScriptRoot '..\docs\screenshots'),
    [string]$BaseUrl = 'http://127.0.0.1:8421',
    [string[]]$Only = @(),
    [string]$AdminPassword = 'cannabis',
    [string]$Browser = '',
    # A one-off shot
    [string]$Url = '',
    [string]$Name = 'adhoc',
    [int]$Width = 1180,
    [int]$Height = 820,
    [double]$Scale = 2,
    [string]$Theme = 'light',
    [switch]$Mobile,
    [string]$Setup = '',
    # With -Url: JavaScript to run after the setup, whose result is printed (for checking a layout)
    [string]$Eval = ''
)
$ErrorActionPreference = 'Stop'
# "-Only a,b" arrives as one string when run with powershell -File
$Only = @($Only | ForEach-Object { $_ -split ',' } | Where-Object { $_ })

# --- Find a browser --------------------------------------------------------------
if (-not $Browser) {
    $Browser = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
    ) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
if (-not $Browser) {
    throw "Microsoft Edge wasn't found. Pass -Browser with the path to msedge.exe or chrome.exe."
}

# --- Check the dev server is up ------------------------------------------------------
try {
    Invoke-WebRequest "$BaseUrl/index.php" -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    throw "No Visitors dev server answering at $BaseUrl. Seed and start one first (see the notes at the top of this script)."
}

# --- Setup scripts, run in the page --------------------------------------------------
# set(selector, value) fills a field and fires 'input' like typing would
$helper = "const set=(s,v)=>{const el=document.querySelector(s);el.value=v;el.dispatchEvent(new Event('input',{bubbles:true}));};const sleep=(ms)=>new Promise(r=>setTimeout(r,ms));"

$fillSignIn = "set('[name=name]','Aroha Walker');set('[name=contact]','021 555 0135');set('[name=company]','Southern HVAC Services');set('[name=visiting]','Mike');document.querySelectorAll('.check-item input').forEach((c,i)=>{if(i<3)c.checked=true;});"
$searchSam = "set('#signOutName','Sam');"
# Wait for the search answer first, or the page is too short to scroll that far
$toSignOut = "await sleep(900);window.scrollTo({top: document.getElementById('sign-out').getBoundingClientRect().top + window.scrollY - 76, behavior: 'instant'});"
$toChecklist = "window.scrollTo({top: document.getElementById('checklist').getBoundingClientRect().top + window.scrollY - 90, behavior: 'instant'});"

# name, path, width, height, scale, phone?, theme, setup, full page?
# 'LOGIN' as the setup logs in to admin.php first, then runs anything after it
$shots = @(
    @('tablet-sign-in',       '/index.php', 1180, 820,  2, $false, 'light', "$fillSignIn $searchSam", $false),
    @('phone-sign-out',       '/index.php', 390,  844,  2, $true,  'light', "$searchSam $toSignOut", $false),
    @('tablet-sign-in-dark',  '/index.php', 1180, 820,  2, $false, 'dark',  "$fillSignIn $searchSam", $false),
    @('phone-sign-in-dark',   '/index.php', 390,  844,  2, $true,  'dark',  $fillSignIn, $false),
    @('desktop-admin',        '/admin.php', 1280, 1250, 1, $false, 'light', 'LOGIN', $true),
    @('phone-admin',          '/admin.php', 390,  844,  2, $true,  'light', 'LOGIN', $false),
    @('desktop-checklist',    '/admin.php', 1280, 820,  1, $false, 'light', "LOGIN $toChecklist", $false)
)
if ($Url) {
    $shots = @(, @($Name, $Url, $Width, $Height, $Scale, [bool]$Mobile, $Theme, $Setup, $false))
}

# --- Headless browser, driven over the DevTools protocol ----------------------------------
$port = 9336
$browserProfile = Join-Path $env:TEMP 'visitors-screenshot-profile'
Remove-Item -Recurse -Force $browserProfile -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force $OutDir | Out-Null

$proc = Start-Process -FilePath $Browser -PassThru -WindowStyle Hidden -ArgumentList @(
    '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
    "--user-data-dir=$browserProfile", "--remote-debugging-port=$port", '--window-size=1280,900', 'about:blank')

$ws = $null
try {
    $deadline = (Get-Date).AddSeconds(25)
    $targets = $null
    do {
        try { $targets = Invoke-RestMethod "http://127.0.0.1:$port/json" -TimeoutSec 2 } catch { Start-Sleep -Milliseconds 300 }
    } while (-not $targets -and (Get-Date) -lt $deadline)
    if (-not $targets) { throw "The browser's DevTools endpoint never came up." }
    $page = $targets | Where-Object { $_.type -eq 'page' } | Select-Object -First 1

    $ws = [System.Net.WebSockets.ClientWebSocket]::new()
    $ws.ConnectAsync([Uri]$page.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
    $script:msgId = 0
    $recvBuf = [byte[]]::new(4MB)

    function Send-Cdp([string]$method, [hashtable]$params = @{}) {
        $script:msgId++
        $id = $script:msgId
        $json = @{ id = $id; method = $method; params = $params } | ConvertTo-Json -Depth 8 -Compress
        $bytes = [Text.Encoding]::UTF8.GetBytes($json)
        $ws.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
        while ($true) {
            $sb = [Text.StringBuilder]::new()
            do {
                $res = $ws.ReceiveAsync([ArraySegment[byte]]::new($recvBuf), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
                [void]$sb.Append([Text.Encoding]::UTF8.GetString($recvBuf, 0, $res.Count))
            } while (-not $res.EndOfMessage)
            $msg = $sb.ToString() | ConvertFrom-Json
            if ($msg.id -eq $id) {
                if ($msg.error) { throw "DevTools $method failed: $($msg.error.message)" }
                return $msg.result
            }
            # anything else is an event we didn't ask for
        }
    }

    function Invoke-PageJs([string]$js) {
        $r = Send-Cdp 'Runtime.evaluate' @{ expression = $js; returnByValue = $true; awaitPromise = $true }
        return $r.result.value
    }

    function Wait-PageLoad([string]$pathOnly) {
        $t = (Get-Date).AddSeconds(15)
        do {
            Start-Sleep -Milliseconds 200
            $ready = Invoke-PageJs "document.readyState==='complete' && location.pathname.endsWith('$pathOnly') && document.fonts.status==='loaded'"
        } while (-not $ready -and (Get-Date) -lt $t)
        Start-Sleep -Milliseconds 600
    }

    # Land on the site once, so localStorage (the saved theme) is ours to set
    Send-Cdp 'Page.navigate' @{ url = "$BaseUrl/index.php" } | Out-Null
    Wait-PageLoad '/index.php'
    $saved = 0

    foreach ($s in $shots) {
        # (PowerShell names ignore case, so these can't reuse the parameter names: $mobile would be the -Mobile switch)
        $shotName, $shotPath, $shotW, $shotH, $shotScale, $shotMobile, $shotTheme, $shotSetup, $shotFull = $s
        if ($Only.Count -gt 0 -and $Only -notcontains $shotName) { continue }

        Send-Cdp 'Emulation.setDeviceMetricsOverride' @{ width = $shotW; height = $(if ($shotFull) { 900 } else { $shotH }); deviceScaleFactor = $shotScale; mobile = $shotMobile } | Out-Null
        Invoke-PageJs "localStorage.setItem('visitors-theme', '$shotTheme'); true" | Out-Null

        $pathOnly = ($shotPath -split '\?')[0]
        Send-Cdp 'Page.navigate' @{ url = "$BaseUrl$shotPath" } | Out-Null
        Wait-PageLoad $pathOnly

        if ($shotSetup -like 'LOGIN*') {
            # Log in on the page itself and photograph the page the login answers with
            # (anything after LOGIN runs as a setup script once logged in)
            $sent = Invoke-PageJs "(()=>{const f=document.querySelector('input[name=password]');if(!f)return false;window.__beforeLogin=1;f.value=$(ConvertTo-Json $AdminPassword);f.form.submit();return true;})()"
            if ($sent) {
                $t = (Get-Date).AddSeconds(15)
                do {
                    Start-Sleep -Milliseconds 200
                    $moved = Invoke-PageJs "!window.__beforeLogin && document.readyState==='complete'"
                } while (-not $moved -and (Get-Date) -lt $t)
                Wait-PageLoad $pathOnly
            }
            $loggedIn = Invoke-PageJs "!!document.querySelector('.visitor-table, .empty-state')"
            if (-not $loggedIn) { throw "Couldn't log in to admin.php for $shotName. Check -AdminPassword." }
            $afterLogin = $shotSetup.Substring(5)
            if ($afterLogin.Trim() -ne '') {
                Invoke-PageJs "(async()=>{ $helper $afterLogin return true; })()" | Out-Null
                Start-Sleep -Milliseconds 600
            }
        } elseif ($shotSetup -ne '') {
            Invoke-PageJs "(async()=>{ $helper $shotSetup return true; })()" | Out-Null
            Start-Sleep -Milliseconds 900
        }

        if ($shotFull) {
            $m = Send-Cdp 'Page.getLayoutMetrics'
            $contentH = [math]::Ceiling($m.cssContentSize.height)
            $clipH = [math]::Min([math]::Max($contentH, 600), $shotH)
            $shot = Send-Cdp 'Page.captureScreenshot' @{ format = 'png'; captureBeyondViewport = $true; clip = @{ x = 0; y = 0; width = $shotW; height = $clipH; scale = 1 } }
            $size = "${shotW}x$clipH"
        } else {
            # Just the screen as it stands, scrolled position and sticky header included
            $shot = Send-Cdp 'Page.captureScreenshot' @{ format = 'png'; captureBeyondViewport = $false }
            $size = "${shotW}x$shotH"
        }
        if ($Url -and $Eval) {
            "EVAL $(Invoke-PageJs $Eval)"
        }
        $out = Join-Path $OutDir "$shotName.png"
        [IO.File]::WriteAllBytes($out, [Convert]::FromBase64String($shot.data))
        $saved++
        "OK   $shotName  ($([math]::Round((Get-Item $out).Length / 1KB)) KB, $size at ${shotScale}x, $shotTheme)"
    }

    "Saved $saved screenshot(s) to $((Resolve-Path $OutDir).Path)"
} finally {
    if ($ws) { $ws.Dispose() }
    Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
}
