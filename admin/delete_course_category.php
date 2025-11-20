<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#courses');
    exit;
}

$categoryId = trim($_POST['category_id'] ?? '');

if ($categoryId === '') {
    $_SESSION['admin_error'] = 'Categoria inválida seleccionada para eliminar.';
    header('Location: dashboard.php#courses');
    exit;
}

$data = load_data();
$categories = $data['course_categories'] ?? [];
$subcategories = $data['course_subcategories'] ?? [];
$courses = $data['courses'] ?? [];

$hasSubcategories = false;
foreach ($subcategories as $subcategory) {
    if (($subcategory['category_id'] ?? '') === $categoryId) {
        $hasSubcategories = true;
        break;
    }
}

if ($hasSubcategories) {
    $_SESSION['admin_error'] = 'Remove as subcategorias desta categoria antes de a eliminar.';
    header('Location: dashboard.php#courses');
    exit;
}

$hasCourses = false;
foreach ($courses as $course) {
    if (($course['category_id'] ?? '') === $categoryId) {
        $hasCourses = true;
        break;
    }
}

if ($hasCourses) {
    $_SESSION['admin_error'] = 'Remove ou reatribui os cursos antes de eliminar esta categoria.';
    header('Location: dashboard.php#courses');
    exit;
}

$initialCount = count($categories);
$categories = array_values(array_filter($categories, static function (array $category) use ($categoryId) {
    return ($category['id'] ?? '') !== $categoryId;
}));

if ($initialCount === count($categories)) {
    $_SESSION['admin_error'] = 'Não foi possível encontrar a categoria seleccionada.';
} else {
    $data['course_categories'] = $categories;
    save_data($data);
    $_SESSION['admin_success'] = 'Categoria eliminada com sucesso.';
}

header('Location: dashboard.php#courses');
exit;
