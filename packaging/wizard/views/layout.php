<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtoM Heratio - Configuration Wizard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <h1>AtoM Heratio Configuration Wizard</h1>
    </header>

    <nav class="steps">
        <?php
        $stepNames = [
            1 => 'System Status',
            2 => 'Plugins',
            3 => 'GLAM Sector',
            4 => 'AI & Automation',
            5 => 'Compliance',
            6 => 'Preservation',
            7 => 'Review & Apply',
        ];
        foreach ($stepNames as $num => $name): ?>
            <a href="?token=<?= htmlspecialchars($token) ?>&step=<?= $num ?>"
               class="step-link <?= ($num === $step) ? 'active' : '' ?> <?= ($num < $step) ? 'completed' : '' ?>">
                <span class="step-num"><?= $num ?></span>
                <span class="step-name"><?= $name ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <main>
        <?php require $viewFile; ?>
    </main>

    <footer>
        <div class="nav-buttons">
            <?php if ($step > 1): ?>
                <a href="?token=<?= htmlspecialchars($token) ?>&step=<?= $step - 1 ?>" class="btn btn-secondary">Previous</a>
            <?php endif; ?>
            <?php if ($step < 7): ?>
                <a href="?token=<?= htmlspecialchars($token) ?>&step=<?= $step + 1 ?>" class="btn btn-primary">Next</a>
            <?php endif; ?>
        </div>
        <p class="footer-text">AtoM Heratio &copy; The Archive and Heritage Group (Pty) Ltd</p>
    </footer>

    <script>
        const WIZARD_TOKEN = '<?= htmlspecialchars($token) ?>';
    </script>
    <script src="assets/wizard.js"></script>
</body>
</html>
