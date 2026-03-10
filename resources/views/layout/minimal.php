<!DOCTYPE html>
<html class="dark" lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Operon Intelligence') ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary":         "#E11D48",
                        "operon-teal":     "#0A1D2A",
                        "operon-energy":   "#18C29C",
                        "background-dark": "#19171A",
                        "brand-surface":   "#232026",
                    },
                    fontFamily: { "display": ["Plus Jakarta Sans", "sans-serif"] },
                },
            },
        };
    </script>
</head>
<body class="bg-background-dark font-display text-slate-100 antialiased">
    <?= $content ?>
</body>
</html>
