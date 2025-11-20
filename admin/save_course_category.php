<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#courses');
    exit;
}

function redirect_with_message(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header('Location: dashboard.php#courses');
    exit;
}

$mode = $_POST['mode'] ?? 'create';
$categoryId = trim($_POST['category_id'] ?? '');
$name = trim($_POST['name'] ?? '');

if ($name === '') {
    redirect_with_message('admin_error', 'Indica o nome da categoria.');
}

$data = load_data();
$categories = $data['course_categories'] ?? [];

$now = date('c');

if ($mode === 'update') {
    if ($categoryId === '') {
        redirect_with_message('admin_error', 'Categoria inválida seleccionada para edição.');
    }

    $updated = false;
    foreach ($categories as &$category) {
        if (($category['id'] ?? '') === $categoryId) {
            foreach ($categories as $existing) {
                if (($existing['id'] ?? '') !== $categoryId && strcasecmp($existing['name'] ?? '', $name) === 0) {
                    redirect_with_message('admin_error', 'Já existe uma categoria com este nome.');
                }
            }

            $category['name'] = $name;
            $category['updated_at'] = $now;
            if (empty($category['created_at'])) {
                $category['created_at'] = $now;
            }
            $updated = true;
            break;
        }
    }
    unset($category);

    if (!$updated) {
        redirect_with_message('admin_error', 'Não foi possível encontrar a categoria seleccionada.');
    }

    $message = 'Categoria actualizada com sucesso.';
} else {
    foreach ($categories as $existing) {
        if (strcasecmp($existing['name'] ?? '', $name) === 0) {
            redirect_with_message('admin_error', 'Já existe uma categoria com este nome.');
        }
    }

    $idBase = 'cat-' . slugify($name);
    if ($idBase === 'cat-') {
        $idBase .= bin2hex(random_bytes(3));
    }

    $id = $idBase;
    $suffix = 1;
    $existingIds = array_map(static function ($category) {
        return $category['id'] ?? '';
    }, $categories);

    while (in_array($id, $existingIds, true)) {
        $id = $idBase . '-' . (++$suffix);
    }

    $categories[] = [
        'id' => $id,
        'name' => $name,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $message = 'Categoria criada com sucesso.';
}

usort($categories, static function (array $a, array $b): int {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$data['course_categories'] = array_values($categories);
save_data($data);

redirect_with_message('admin_success', $message);
