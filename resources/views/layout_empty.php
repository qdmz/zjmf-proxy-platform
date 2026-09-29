<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? '') ?> - <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php if ($flash = flash('error')): ?><div class="container"><div class="alert alert-error"><?= e($flash) ?></div></div><?php endif; ?>
<?= $content ?? '' ?>
</body>
</html>
