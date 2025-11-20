<?php
require_once __DIR__ . '/config.php';

if (!function_exists('admin_asset')) {
    function admin_asset(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1 || strpos($path, '//') === 0 || strpos($path, 'data:') === 0) {
            return $path;
        }

        return '../' . ltrim($path, '/');
    }
}

$error = '';
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Credenciais inválidas. Por favor, tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sessão | Jompson Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="<?php echo htmlspecialchars(admin_asset('admin/assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(admin_asset('admin/assets/css/admin.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
                <h1 class="h4 mb-0">JOMPSON ADMIN</h1>
                <small class="text-muted">Acesso reservado à equipa autorizada.</small>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <form method="post" class="auth-form" autocomplete="off" novalidate>
            <div>
                <label for="username" class="form-label">Utilizador</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
            </div>
            <div>
                <label for="password" class="form-label">Palavra-passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <div class="auth-actions">
                <div class="text-muted small mb-0">Mantém as credenciais seguras.</div>
                <button type="submit" class="btn btn-primary flex-shrink-0">Entrar no painel</button>
            </div>
        </form>
    </div>
    <p class="auth-footer">Precisa de apoio? Contacte <a href="mailto:suporte@codigocosme.com">suporte@codigocosme.com</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
