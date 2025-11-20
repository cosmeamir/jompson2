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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_asset('adminV2/assets/css/auth.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon"><i class="bi bi-shield-lock"></i></span>
            <div>
                <h1>JOMPSON ADMIN</h1>
                <p>Acesso reservado à equipa autorizada.</p>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <form method="post" autocomplete="off" novalidate>
            <div class="mb-3 text-start">
                <label for="username" class="form-label">Utilizador</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-4 text-start">
                <label for="password" class="form-label">Palavra-passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar no painel</button>
        </form>
    </div>
    <p class="auth-footer">Precisa de apoio? Contacte <a href="mailto:suporte@codigocosme.com">suporte@codigocosme.com</a></p>
</div>
</body>
</html>
