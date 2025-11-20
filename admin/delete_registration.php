<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#inscricoes');
    exit;
}

$registrationId = trim($_POST['registration_id'] ?? '');

if ($registrationId === '') {
    $_SESSION['admin_error'] = 'Registo inválido seleccionado para eliminar.';
    header('Location: dashboard.php#inscricoes');
    exit;
}

$data = load_data();
$registrations = $data['course_registrations'] ?? [];

$removedProof = null;
$initialCount = count($registrations);
$registrations = array_values(array_filter($registrations, function (array $registration) use ($registrationId, &$removedProof) {
    if (($registration['id'] ?? '') === $registrationId) {
        $removedProof = $registration['comprovativo'] ?? null;
        return false;
    }

    return true;
}));

if ($initialCount === count($registrations)) {
    $_SESSION['admin_error'] = 'Não foi possível encontrar a inscrição seleccionada.';
} else {
    $data['course_registrations'] = $registrations;
    save_data($data);
    if ($removedProof) {
        $filePath = dirname(__DIR__) . '/' . ltrim($removedProof, '/');
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
    $_SESSION['admin_success'] = 'Inscrição removida com sucesso.';
}

header('Location: dashboard.php#inscricoes');
exit;
