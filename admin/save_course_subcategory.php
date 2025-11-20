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
$subcategoryId = trim($_POST['subcategory_id'] ?? '');
$categoryId = trim($_POST['category_id'] ?? '');
$name = trim($_POST['name'] ?? '');

if ($categoryId === '') {
    redirect_with_message('admin_error', 'Selecciona a categoria da subcategoria.');
}

if ($name === '') {
    redirect_with_message('admin_error', 'Indica o nome da subcategoria.');
}

$data = load_data();
$categories = $data['course_categories'] ?? [];
$subcategories = $data['course_subcategories'] ?? [];

$categoryExists = false;
foreach ($categories as $category) {
    if (($category['id'] ?? '') === $categoryId) {
        $categoryExists = true;
        break;
    }
}

if (!$categoryExists) {
    redirect_with_message('admin_error', 'A categoria seleccionada já não existe. Actualiza a página e tenta novamente.');
}

$now = date('c');

if ($mode === 'update') {
    if ($subcategoryId === '') {
        redirect_with_message('admin_error', 'Subcategoria inválida seleccionada para edição.');
    }

    $updated = false;
    foreach ($subcategories as &$subcategory) {
        if (($subcategory['id'] ?? '') === $subcategoryId) {
            foreach ($subcategories as $existing) {
                if (($existing['id'] ?? '') !== $subcategoryId
                    && ($existing['category_id'] ?? '') === $categoryId
                    && strcasecmp($existing['name'] ?? '', $name) === 0) {
                    redirect_with_message('admin_error', 'Já existe uma subcategoria com este nome nessa categoria.');
                }
            }

            $subcategory['category_id'] = $categoryId;
            $subcategory['name'] = $name;
            $subcategory['updated_at'] = $now;
            if (empty($subcategory['created_at'])) {
                $subcategory['created_at'] = $now;
            }
            $updated = true;
            break;
        }
    }
    unset($subcategory);

    if (!$updated) {
        redirect_with_message('admin_error', 'Não foi possível encontrar a subcategoria seleccionada.');
    }

    $message = 'Subcategoria actualizada com sucesso.';
} else {
    foreach ($subcategories as $existing) {
        if (($existing['category_id'] ?? '') === $categoryId && strcasecmp($existing['name'] ?? '', $name) === 0) {
            redirect_with_message('admin_error', 'Já existe uma subcategoria com este nome nessa categoria.');
        }
    }

    $idBase = 'sub-' . slugify($name);
    if ($idBase === 'sub-') {
        $idBase .= bin2hex(random_bytes(3));
    }

    $id = $idBase;
    $suffix = 1;
    $existingIds = array_map(static function ($subcategory) {
        return $subcategory['id'] ?? '';
    }, $subcategories);

    while (in_array($id, $existingIds, true)) {
        $id = $idBase . '-' . (++$suffix);
    }

    $subcategories[] = [
        'id' => $id,
        'category_id' => $categoryId,
        'name' => $name,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $message = 'Subcategoria criada com sucesso.';
}

usort($subcategories, static function (array $a, array $b) use ($categories): int {
    $categoryA = $a['category_id'] ?? '';
    $categoryB = $b['category_id'] ?? '';

    if ($categoryA !== $categoryB) {
        $nameA = '';
        $nameB = '';
        foreach ($categories as $category) {
            if (($category['id'] ?? '') === $categoryA) {
                $nameA = $category['name'] ?? '';
            }
            if (($category['id'] ?? '') === $categoryB) {
                $nameB = $category['name'] ?? '';
            }
        }

        $comparison = strcasecmp($nameA, $nameB);
        if ($comparison !== 0) {
            return $comparison;
        }
    }

    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$data['course_subcategories'] = array_values($subcategories);
save_data($data);

redirect_with_message('admin_success', $message);
