<?php
$csrfToken = generateCSRFToken();
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$extraHead = $extraHead ?? '';
$extraScripts = $extraScripts ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title><?= $pageTitle ?? 'Admin' ?> – Bansari Homeopathy</title>
    <?= $extraHead ?>
    <script>
        (function(){
            try { var t = localStorage.getItem('admin-theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme:dark)').matches))
                document.documentElement.setAttribute('data-theme','dark');
            } catch(e){}
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script>
        window.ADMIN_API_KEY = '<?= getenv("ADMIN_API_KEY") ?: "your-admin-api-key-here" ?>';
        window.ADMIN_ID = '<?= getAdminId() ?>';
        window.NEXT_API_URL = '<?= getenv("NEXT_PUBLIC_APP_URL") ?: "http://localhost:3000" ?>';
    </script>
    <?= $extraScripts ?>
</head>
<body>
<div class="admin-wrapper">

