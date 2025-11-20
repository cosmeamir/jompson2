<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#courses');
    exit;
}

$subcategoryId = trim($_POST['subcategory_id'] ?? '');

if ($subcategoryId === '') {
    $_SESSION['admin_error'] = 'Subcategoria inválida seleccionada para eliminar.';
    header('Location: dashboard.php#courses');
    exit;
}

$data = load_data();
$subcategories = $data['course_subcategories'] ?? [];
$courses = $data['courses'] ?? [];

$hasCourses = false;
foreach ($courses as $course) {
    if (($course['subcategory_id'] ?? '') === $subcategoryId) {
        $hasCourses = true;
        break;
    }
}

if ($hasCourses) {
    $_SESSION['admin_error'] = 'Remove ou reatribui os cursos antes de eliminar esta subcategoria.';
    header('Location: dashboard.php#courses');
    exit;
}

$initialCount = count($subcategories);
$subcategories = array_values(array_filter($subcategories, static function (array $subcategory) use ($subcategoryId) {
    return ($subcategory['id'] ?? '') !== $subcategoryId;
}));

if ($initialCount === count($subcategories)) {
    $_SESSION['admin_error'] = 'Não foi possível encontrar a subcategoria seleccionada.';
} else {
    $data['course_subcategories'] = $subcategories;
    save_data($data);
    $_SESSION['admin_success'] = 'Subcategoria eliminada com sucesso.';
}

header('Location: dashboard.php#courses');
exit;
