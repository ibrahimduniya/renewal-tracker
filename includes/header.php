<?php $cfg = app_config(); $flash = take_flash(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($cfg['app_name']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=20261027">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">🔔 <?= e($cfg['app_name']) ?></a>
  <?php if (!empty($_SESSION['admin_id'])): ?>
  <nav>
    <a href="index.php?page=dashboard">Dashboard</a>
    <a href="index.php?page=services">All Websites</a>
    <a class="btn-nav" href="index.php?page=service_form">+ Add New</a>
    <a href="index.php?page=settings">Settings</a>
    <a href="index.php?page=logout">Logout</a>
  </nav>
  <?php endif; ?>
</header>
<main class="wrap">
<?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
