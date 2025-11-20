<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#courses');
    exit;
}

$courseId = trim($_POST['course_id'] ?? '');

if ($courseId === '') {
    $_SESSION['admin_error'] = 'Curso inválido seleccionado para eliminar.';
    header('Location: dashboard.php#courses');
    exit;
}

$data = load_data();
$courses = $data['courses'] ?? [];

$initialCount = count($courses);
$courses = array_values(array_filter($courses, static function (array $course) use ($courseId) {
    return ($course['id'] ?? '') !== $courseId;
}));

if ($initialCount === count($courses)) {
    $_SESSION['admin_error'] = 'Não foi possível encontrar o curso seleccionado.';
} else {
    $data['courses'] = $courses;
    save_data($data);
    $_SESSION['admin_success'] = 'Curso eliminado com sucesso.';
}

header('Location: dashboard.php#courses');
exit;
