<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'HA Add-on Converter' ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🪄</text></svg>">
    <style>
        body { font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
    </style>
    <link href="<?= $basePath ?? '' ?>/css/vendor/materialdesignicons.min.css" rel="stylesheet">
    <link href="<?= $basePath ?? '' ?>/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="<?= $basePath ?? '' ?>/css/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/css/vendor/easymde.min.css">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/css/vendor/codemirror.min.css">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/css/vendor/monokai.min.css">
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/css/app.css">
    <script src="<?= $basePath ?? '' ?>/js/vendor/bootstrap.bundle.min.js"></script>
    <script src="<?= $basePath ?? '' ?>/js/vendor/htmx.min.js"></script>
    <script src="<?= $basePath ?? '' ?>/js/vendor/easymde.min.js"></script>
    <script src="<?= $basePath ?? '' ?>/js/vendor/codemirror.min.js"></script>
    <script src="<?= $basePath ?? '' ?>/js/vendor/shell.min.js"></script>
</head>
<body>

<div class="header-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <span class="mdi mdi-auto-fix me-2" style="font-size: 24px;"></span>
            <h2 class="mb-0" style="font-weight: 400;">HA Add-on Converter</h2>
        </div>
        <button type="button" id="cancelBtn" class="btn btn-outline-light btn-sm" style="display:none;" onclick="cancelConverter()">Cancel</button>
    </div>
</div>

<div class="container">
    <div class="converter-container">
        <?= $content ?? '' ?>
    </div>
</div>

<?php include __DIR__ . '/fragments/modals.php'; ?>

<script>
    const basePath = '<?= $basePath ?? '' ?>';
</script>
<script src="<?= $basePath ?? '' ?>/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
