<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$data = load_data();
$blogs = $data['blogs'];

function handle_image_upload(array $file, string $slug): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível carregar a imagem selecionada.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('A imagem deve ter, no máximo, 2MB.');
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

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $filename = $slug . '-' . uniqid('', true) . '.' . $allowed[$mime];
    $destination = rtrim(UPLOAD_DIR, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Falha ao guardar a imagem no servidor.');
    }

    return rtrim(UPLOAD_URL, '/') . '/' . $filename;
}

function delete_uploaded_image(string $relativePath): void
{
    if ($relativePath === '') {
        return;
    }

    $cleanPath = ltrim($relativePath, '/');
    $uploadsBase = trim(UPLOAD_URL, '/');

    if (strpos($cleanPath, $uploadsBase) !== 0) {
        return;
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function redirect_with_message(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header('Location: dashboard.php');
    exit;
}

if ($action === 'create' || $action === 'update') {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $currentImage = trim($_POST['current_image'] ?? '');

    try {
        $uploadedImage = handle_image_upload($_FILES['image_file'] ?? [], slugify($title));
    } catch (RuntimeException $exception) {
        redirect_with_message('admin_error', $exception->getMessage());
    }

    if ($uploadedImage !== '') {
        $image = $uploadedImage;
    }

    if ($title === '' || $date === '' || $author === '' || $excerpt === '' || $content === '') {
        redirect_with_message('admin_error', 'Preenche todos os campos obrigatórios do artigo.');
    }

    if ($image === '') {
        redirect_with_message('admin_error', 'Seleciona uma imagem ou informa um endereço válido.');
    }

    $slug = slugify($title);
    if ($action === 'update') {
        $originalSlug = $_POST['slug'] ?? '';
        $existingSlugs = array_column($blogs, 'slug');
        $existingSlugs = array_values(array_filter($existingSlugs, static function ($value) use ($originalSlug) {
            return $value !== $originalSlug;
        }));

        $baseSlug = $slug ?: $originalSlug;
        if ($baseSlug === '') {
            $baseSlug = 'post-' . uniqid();
        }

        $candidate = $baseSlug;
        $counter = 2;
        while (in_array($candidate, $existingSlugs, true)) {
            $candidate = $baseSlug . '-' . $counter++;
        }

        foreach ($blogs as &$blog) {
            if ($blog['slug'] === $originalSlug) {
                if ($uploadedImage !== '' && $currentImage !== $uploadedImage) {
                    delete_uploaded_image($currentImage);
                }

                $blog = [
                    'slug' => $candidate,
                    'title' => $title,
                    'date' => $date,
                    'author' => $author,
                    'image' => $image,
                    'excerpt' => $excerpt,
                    'content' => $content,
                ];
                break;
            }
        }
        unset($blog);
    } else {
        $existingSlugs = array_column($blogs, 'slug');
        $baseSlug = $slug;
        $counter = 2;
        while (in_array($slug, $existingSlugs, true)) {
            $slug = $baseSlug . '-' . $counter++;
        }
        $blogs[] = [
            'slug' => $slug,
            'title' => $title,
            'date' => $date,
            'author' => $author,
            'image' => $image,
            'excerpt' => $excerpt,
            'content' => $content,
        ];
    }
}

if ($action === 'delete') {
    $slug = $_POST['slug'] ?? '';
    foreach ($blogs as $index => $blog) {
        if ($blog['slug'] === $slug) {
            delete_uploaded_image($blog['image'] ?? '');
            unset($blogs[$index]);
        }
    }
    $blogs = array_values($blogs);
}

usort($blogs, static function ($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$data['blogs'] = $blogs;
save_data($data);
redirect_with_message('admin_success', 'Conteúdo do blog actualizado com sucesso.');
