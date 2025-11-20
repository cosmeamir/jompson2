<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

function redirect_with_message(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header('Location: dashboard.php#courses');
    exit;
}

$mode = $_POST['mode'] ?? 'create';
$courseId = trim($_POST['course_id'] ?? '');
$categoryId = trim($_POST['category_id'] ?? '');
$subcategoryId = trim($_POST['subcategory_id'] ?? '');
$title = trim($_POST['title'] ?? '');
$headline = trim($_POST['headline'] ?? '');
$overview = trim($_POST['overview'] ?? '');
$generalObjectives = trim($_POST['general_objectives'] ?? '');
$specificObjectives = trim($_POST['specific_objectives'] ?? '');
$contents = trim($_POST['contents'] ?? '');
$details = trim($_POST['details'] ?? '');
$pdfUrl = trim($_POST['pdf_url'] ?? '');
$price = trim($_POST['price'] ?? '');

if ($categoryId === '' || $subcategoryId === '' || $title === '' || $price === '') {
    redirect_with_message('admin_error', 'Selecciona a categoria, subcategoria e indica o título e preço do curso.');
}

if ($mode === 'update' && $courseId === '') {
    redirect_with_message('admin_error', 'Curso inválido seleccionado para edição.');
}

$data = load_data();
$courses = $data['courses'] ?? [];
$categories = $data['course_categories'] ?? [];
$subcategories = $data['course_subcategories'] ?? [];

$categoryName = '';
foreach ($categories as $category) {
    if (($category['id'] ?? '') === $categoryId) {
        $categoryName = $category['name'] ?? '';
        break;
    }
}

if ($categoryName === '') {
    redirect_with_message('admin_error', 'A categoria seleccionada já não existe. Actualiza a página e tenta novamente.');
}

$subcategoryName = '';
$subcategoryCategoryId = '';
foreach ($subcategories as $subcategory) {
    if (($subcategory['id'] ?? '') === $subcategoryId) {
        $subcategoryName = $subcategory['name'] ?? '';
        $subcategoryCategoryId = $subcategory['category_id'] ?? '';
        break;
    }
}

if ($subcategoryName === '') {
    redirect_with_message('admin_error', 'A subcategoria seleccionada já não existe. Actualiza a página e tenta novamente.');
}

if ($subcategoryCategoryId !== $categoryId) {
    redirect_with_message('admin_error', 'A subcategoria escolhida não pertence à categoria seleccionada.');
}

$now = date('c');

if ($mode === 'update') {
    $updated = false;
    foreach ($courses as &$course) {
        if (($course['id'] ?? '') === $courseId) {
            $course['category_id'] = $categoryId;
            $course['subcategory_id'] = $subcategoryId;
            $course['category'] = $categoryName;
            $course['subcategory'] = $subcategoryName;
            $course['title'] = $title;
            $course['headline'] = $headline;
            $course['overview'] = $overview;
            $course['general_objectives'] = $generalObjectives;
            $course['specific_objectives'] = $specificObjectives;
            $course['contents'] = $contents;
            $course['details'] = $details;
            $course['pdf_url'] = $pdfUrl;
            $course['price'] = $price;
            $course['updated_at'] = $now;
            if (empty($course['created_at'])) {
                $course['created_at'] = $now;
            }
            $updated = true;
            break;
        }
    }
    unset($course);

    if (!$updated) {
        redirect_with_message('admin_error', 'Não foi possível encontrar o curso seleccionado.');
    }

    $message = 'Curso actualizado com sucesso.';
} else {
    $id = 'course-' . bin2hex(random_bytes(6));
    $courses[] = [
        'id' => $id,
        'category_id' => $categoryId,
        'subcategory_id' => $subcategoryId,
        'category' => $categoryName,
        'subcategory' => $subcategoryName,
        'title' => $title,
        'headline' => $headline,
        'overview' => $overview,
        'general_objectives' => $generalObjectives,
        'specific_objectives' => $specificObjectives,
        'contents' => $contents,
        'details' => $details,
        'pdf_url' => $pdfUrl,
        'price' => $price,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $message = 'Curso criado com sucesso.';
}

usort($courses, static function (array $a, array $b): int {
    $categoryComparison = strcasecmp($a['category'] ?? '', $b['category'] ?? '');
    if ($categoryComparison !== 0) {
        return $categoryComparison;
    }

    $subcategoryComparison = strcasecmp($a['subcategory'] ?? '', $b['subcategory'] ?? '');
    if ($subcategoryComparison !== 0) {
        return $subcategoryComparison;
    }

    return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
});

$data['courses'] = array_values($courses);
save_data($data);

redirect_with_message('admin_success', $message);
