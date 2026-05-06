param(
    [string]$BaseUrl = $env:TM_BASE_URL,
    [string]$Company = $env:TM_COMPANY,
    [string]$UserEmail = $env:TM_USER_EMAIL,
    [string]$UserPass = $env:TM_USER_PASS,
    [string]$Range = "7d"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Fail([string]$Message, [int]$Code = 1) {
    Write-Host "[FAIL] $Message" -ForegroundColor Red
    exit $Code
}

function Pass([string]$Message) {
    Write-Host "[PASS] $Message" -ForegroundColor Green
}

function Normalize-BaseUrl([string]$Url) {
    $raw = ""
    if ($null -ne $Url) {
        $raw = [string]$Url
    }
    $trimmed = $raw.Trim()
    if ([string]::IsNullOrWhiteSpace($trimmed)) {
        return ""
    }

    if ($trimmed.EndsWith("/")) {
        return $trimmed.TrimEnd('/')
    }

    return $trimmed
}

function Assert-Envelope($Payload, [string]$Context) {
    if ($null -eq $Payload) {
        Fail "$Context returned null payload"
    }

    $requiredKeys = @("ok", "status", "text", "message", "data", "meta")
    foreach ($key in $requiredKeys) {
        if (-not ($Payload.PSObject.Properties.Name -contains $key)) {
            Fail "$Context payload missing key '$key'"
        }
    }

    $status = ""
    if ($null -ne $Payload.status) {
        $status = [string]$Payload.status
    }
    if ($status -notin @("success", "error")) {
        Fail "$Context payload has invalid status '$status'"
    }
}

function Parse-JsonResponse([string]$Raw, [string]$Context) {
    try {
        return ($Raw | ConvertFrom-Json)
    }
    catch {
        Fail "$Context did not return valid JSON. Raw: $Raw"
    }
}

$BaseUrl = Normalize-BaseUrl $BaseUrl
if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    Fail "Missing TM_BASE_URL (example: https://trademeter-app.herokuapp.com)" 2
}
if ([string]::IsNullOrWhiteSpace($Company)) {
    Fail "Missing TM_COMPANY (company email or company name)" 2
}
if ([string]::IsNullOrWhiteSpace($UserEmail)) {
    Fail "Missing TM_USER_EMAIL" 2
}
if ([string]::IsNullOrWhiteSpace($UserPass)) {
    Fail "Missing TM_USER_PASS" 2
}

$authUrl = "$BaseUrl/apiAuthentications.php"
$dashboardUrl = "$BaseUrl/apiRequest.php"

Pass "Starting release smoke test against $BaseUrl"

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$loginForm = @{
    action = "login"
    companyEmail = $Company
    email = $UserEmail
    pass = $UserPass
    remember = "0"
}

$loginResp = Invoke-WebRequest -Uri $authUrl -Method Post -Body $loginForm -WebSession $session -ContentType "application/x-www-form-urlencoded"
$loginJson = Parse-JsonResponse $loginResp.Content "login"
Assert-Envelope $loginJson "login"

if (-not [bool]$loginJson.ok) {
    $msg = "Login failed"
    if (-not [string]::IsNullOrWhiteSpace([string]$loginJson.message)) {
        $msg = [string]$loginJson.message
    } elseif (-not [string]::IsNullOrWhiteSpace([string]$loginJson.text)) {
        $msg = [string]$loginJson.text
    }
    Fail "Login failed: $msg"
}

$csrf = ""
if ($null -ne $loginJson.csrf_token) {
    $csrf = [string]$loginJson.csrf_token
}
if ([string]::IsNullOrWhiteSpace($csrf)) {
    Fail "Login success response missing csrf_token"
}

foreach ($key in @("user_id", "company", "permissions")) {
    if (-not ($loginJson.PSObject.Properties.Name -contains $key)) {
        Fail "Login success response missing '$key'"
    }
}

Pass "Login envelope + fields validated"

$dashboardForm = @{
    action = "loadDashboard"
    range = $Range
    csrf_token = $csrf
}

$dashboardResp = Invoke-WebRequest -Uri $dashboardUrl -Method Post -Body $dashboardForm -WebSession $session -ContentType "application/x-www-form-urlencoded"
$dashboardJson = Parse-JsonResponse $dashboardResp.Content "loadDashboard"
Assert-Envelope $dashboardJson "loadDashboard"

if (-not [bool]$dashboardJson.ok) {
    $msg = "Dashboard load failed"
    if (-not [string]::IsNullOrWhiteSpace([string]$dashboardJson.message)) {
        $msg = [string]$dashboardJson.message
    } elseif (-not [string]::IsNullOrWhiteSpace([string]$dashboardJson.text)) {
        $msg = [string]$dashboardJson.text
    }
    Fail "Dashboard failed: $msg"
}

$requiredDashboardKeys = @(
    "outstanding",
    "advancePayment",
    "activeDebtors",
    "activeCreditors",
    "totalSales",
    "totalPurchases",
    "rangeTransactions",
    "inventoryValue",
    "profit"
)

foreach ($key in $requiredDashboardKeys) {
    if (-not ($dashboardJson.PSObject.Properties.Name -contains $key)) {
        Fail "Dashboard success response missing '$key'"
    }
}

Pass "Dashboard envelope + KPI fields validated"

$logoutForm = @{
    action = "logout"
    csrf_token = $csrf
}

$logoutResp = Invoke-WebRequest -Uri $authUrl -Method Post -Body $logoutForm -WebSession $session -ContentType "application/x-www-form-urlencoded"
$logoutJson = Parse-JsonResponse $logoutResp.Content "logout"
Assert-Envelope $logoutJson "logout"

if (-not [bool]$logoutJson.ok) {
    $msg = "Logout failed"
    if (-not [string]::IsNullOrWhiteSpace([string]$logoutJson.message)) {
        $msg = [string]$logoutJson.message
    } elseif (-not [string]::IsNullOrWhiteSpace([string]$logoutJson.text)) {
        $msg = [string]$logoutJson.text
    }
    Fail "Logout failed: $msg"
}

Pass "Logout envelope validated"
Pass "Release smoke test completed successfully"
exit 0
