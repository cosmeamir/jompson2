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

function handle_course_image_upload(array $file, string $title): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível carregar a imagem de capa.');
    }

    if ($file['size'] > COURSE_MAX_UPLOAD_SIZE) {
        throw new RuntimeException('A imagem do curso deve ter no máximo 2MB.');
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de imagem não suportado. Utilize JPG, PNG ou WEBP.');
    }

    if (!is_dir(COURSE_UPLOAD_DIR)) {
        mkdir(COURSE_UPLOAD_DIR, 0775, true);
    }

    $slug = slugify($title ?: 'curso');
    $filename = $slug . '-' . uniqid('', true) . '.' . $allowed[$mime];
    $destination = rtrim(COURSE_UPLOAD_DIR, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Falha ao guardar a imagem do curso no servidor.');
    }

    return rtrim(COURSE_UPLOAD_URL, '/') . '/' . $filename;
}

function delete_course_image(string $relativePath): void
{
    if ($relativePath === '') {
        return;
    }

    $cleanPath = ltrim($relativePath, '/');
    $uploadsBase = trim(COURSE_UPLOAD_URL, '/');

    if (strpos($cleanPath, $uploadsBase) !== 0) {
        return;
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
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
$coverImage = trim($_POST['cover_image'] ?? '');
$currentCoverImage = trim($_POST['current_cover_image'] ?? '');
$installments = (int) ($_POST['installments'] ?? 1);

if ($installments < 1 || $installments > 4) {
    redirect_with_message('admin_error', 'Define o número de prestações entre 1 e 4.');
}

try {
    $uploadedCover = handle_course_image_upload($_FILES['cover_image_file'] ?? [], $title);
} catch (RuntimeException $exception) {
    redirect_with_message('admin_error', $exception->getMessage());
}

if ($uploadedCover !== '') {
    $coverImage = $uploadedCover;
}

if ($categoryId === '' || $subcategoryId === '' || $title === '' || $price === '') {
    redirect_with_message('admin_error', 'Selecciona a categoria, subcategoria e indica o título e preço do curso.');
}

if ($coverImage === '') {
    redirect_with_message('admin_error', 'Adiciona uma imagem de capa para o curso.');
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
            $course['cover_image'] = $coverImage;
            $course['installments'] = $installments;
            $course['updated_at'] = $now;
            if (empty($course['created_at'])) {
                $course['created_at'] = $now;
            }
            $updated = true;
            if ($uploadedCover !== '' && $currentCoverImage !== $uploadedCover && $currentCoverImage !== '') {
                delete_course_image($currentCoverImage);
            }
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
        'cover_image' => $coverImage,
        'installments' => $installments,
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
