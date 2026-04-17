<?php
if (!function_exists('asset_ver')) {
  function asset_ver(string $relativePath): int {
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $mtime = @filemtime($absolute);
    return $mtime !== false ? $mtime : 1;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>TradeMeter</title>
<meta http-equiv="CONTENT-TYPE" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<meta name="description" content="This page has information about signages and all its materials">
<meta name="keywords" content="HTML, CSS, JAVASCRIPT, PHP, JQUERY, AJAX">
<meta name="author" content="Chivicks Technology">
<meta name="theme-color" content="#1e90ff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TradeMeter">
<link rel="shortcut icon" href="Images/companyDP/icon-192.png" type="image/png">
<link rel="manifest" href="manifest.webmanifest?v=<?= asset_ver('manifest.webmanifest') ?>">
<link rel="apple-touch-icon" href="Images/companyDP/icon-192.png">
<!-- ✅ Bootstrap 4.6 (local vendor) -->
<link rel="stylesheet" href="assets/vendor/css/bootstrap-4.6.2.min.css">

<!-- ✅ Font Awesome 5 (local vendor) -->
<link rel="stylesheet" href="assets/vendor/css/fontawesome-5.15.4-all.min.css">

<!-- ✅ Your custom stylesheet -->
<link rel="stylesheet" href="styles/styles.css" type="text/css"/>
</head>

<body>
<div class="container-fluid">

<?php include __DIR__ . "/modals.php"; ?>

<!-- 🌞🌙 Theme Toggle -->
<button id="toggleTheme" class="theme-toggle" aria-label="Toggle dark mode">
  <span class="icon sun">☀️</span>
  <span class="icon moon">🌙</span>
</button>

<div class="header">
  <div class="h_left"><img id="cLogo" src="Images/companyDP/logo.jpg" class="logo image" alt="Company logo" onerror="this.onerror=null;this.src='Images/companyDP/logo.jpg';"></div>
  <div class="h_right">TradeMeter</div>
</div>

<div class="tab">
