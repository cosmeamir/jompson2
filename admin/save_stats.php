<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$data = load_data();
$data['stats']['services'] = max(0, (int) ($_POST['services'] ?? 0));
$data['stats']['clients'] = max(0, (int) ($_POST['clients'] ?? 0));
$data['stats']['experience'] = max(0, (int) ($_POST['experience'] ?? 0));

save_data($data);
$_SESSION['admin_success'] = 'Indicadores actualizados com sucesso.';
header('Location: dashboard.php');
exit;
