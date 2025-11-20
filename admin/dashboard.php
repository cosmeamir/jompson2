<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

$data = load_data();
$stats = array_merge([
    'services' => 0,
    'clients' => 0,
    'experience' => 0,
], $data['stats']);
$blogs = $data['blogs'];
$courses = $data['courses'];
$courseRegistrations = $data['course_registrations'];
$courseCategories = $data['course_categories'] ?? [];
$courseSubcategories = $data['course_subcategories'] ?? [];
$emailConfig = defined('EMAIL_CONFIG') && is_array(EMAIL_CONFIG) ? EMAIL_CONFIG : [];
$smtpConfig = $emailConfig['smtp'] ?? [];
$imapConfig = $emailConfig['imap'] ?? [];

usort($courseCategories, static function (array $a, array $b): int {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$categoryMap = [];
foreach ($courseCategories as $category) {
    if (!isset($category['id'])) {
        continue;
    }
    $categoryMap[$category['id']] = $category['name'] ?? '';
}

usort($courseSubcategories, static function (array $a, array $b) use ($categoryMap): int {
    $categoryA = $categoryMap[$a['category_id'] ?? ''] ?? '';
    $categoryB = $categoryMap[$b['category_id'] ?? ''] ?? '';
    $categoryComparison = strcasecmp($categoryA, $categoryB);
    if ($categoryComparison !== 0) {
        return $categoryComparison;
    }

    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$subcategoryMap = [];
$subcategoriesByCategory = [];
foreach ($courseSubcategories as $subcategory) {
    if (!isset($subcategory['id'])) {
        continue;
    }
    $subcategoryMap[$subcategory['id']] = $subcategory;
    $categoryId = $subcategory['category_id'] ?? '';
    if (!isset($subcategoriesByCategory[$categoryId])) {
        $subcategoriesByCategory[$categoryId] = [];
    }
    $subcategoriesByCategory[$categoryId][] = $subcategory;
}

foreach ($courses as &$course) {
    $categoryId = $course['category_id'] ?? '';
    if ($categoryId !== '' && isset($categoryMap[$categoryId])) {
        $course['category'] = $categoryMap[$categoryId];
    }

    $subcategoryId = $course['subcategory_id'] ?? '';
    if ($subcategoryId !== '' && isset($subcategoryMap[$subcategoryId])) {
        $course['subcategory'] = $subcategoryMap[$subcategoryId]['name'] ?? '';
    }
}
unset($course);

$categoryCourseCounts = [];
$subcategoryCourseCounts = [];
foreach ($courses as $courseEntry) {
    $categoryId = $courseEntry['category_id'] ?? '';
    if ($categoryId !== '') {
        if (!isset($categoryCourseCounts[$categoryId])) {
            $categoryCourseCounts[$categoryId] = 0;
        }
        $categoryCourseCounts[$categoryId]++;
    }

    $subcategoryId = $courseEntry['subcategory_id'] ?? '';
    if ($subcategoryId !== '') {
        if (!isset($subcategoryCourseCounts[$subcategoryId])) {
            $subcategoryCourseCounts[$subcategoryId] = 0;
        }
        $subcategoryCourseCounts[$subcategoryId]++;
    }
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

usort($courseRegistrations, static function (array $a, array $b): int {
    return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? '');
});

$successMessage = $_SESSION['admin_success'] ?? null;
$errorMessage = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

if (!function_exists('admin_asset')) {
    function admin_asset(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1 || strpos($path, '//') === 0 || strpos($path, 'data:') === 0) {
            return $path;
        }

        return '../' . ltrim($path, '/');
    }
}

$totalCategories = count($courseCategories);
$totalSubcategories = count($courseSubcategories);
$totalCourses = count($courses);
$totalBlogs = count($blogs);
$totalRegistrations = count($courseRegistrations);

$dashboardJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$dashboardPayload = [
    'categories' => array_values(array_map(static function (array $category): array {
        return [
            'id' => $category['id'] ?? '',
            'name' => $category['name'] ?? '',
        ];
    }, $courseCategories)),
    'subcategories' => array_values(array_map(static function (array $subcategory): array {
        return [
            'id' => $subcategory['id'] ?? '',
            'category_id' => $subcategory['category_id'] ?? '',
            'name' => $subcategory['name'] ?? '',
        ];
    }, $courseSubcategories)),
    'courses' => array_values(array_map(static function (array $course): array {
        return [
            'id' => $course['id'] ?? '',
            'category_id' => $course['category_id'] ?? '',
            'subcategory_id' => $course['subcategory_id'] ?? '',
            'title' => $course['title'] ?? '',
            'headline' => $course['headline'] ?? '',
            'price' => $course['price'] ?? '',
            'cover_image' => $course['cover_image'] ?? '',
            'installments' => $course['installments'] ?? 1,
            'overview' => $course['overview'] ?? '',
            'general_objectives' => $course['general_objectives'] ?? '',
            'specific_objectives' => $course['specific_objectives'] ?? '',
            'contents' => $course['contents'] ?? '',
            'details' => $course['details'] ?? '',
            'pdf_url' => $course['pdf_url'] ?? '',
        ];
    }, $courses)),
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel administrativo | Jompson</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="<?php echo htmlspecialchars(admin_asset('admin/assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(admin_asset('admin/assets/css/admin.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body class="sb-nav-fixed">
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="#overview">Jompson Admin</a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" aria-label="Alternar menu">
        <i class="fas fa-bars"></i>
    </button>
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user fa-fw"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="../index.html" target="_blank" rel="noopener">Ver site</a></li>
                <li><hr class="dropdown-divider" /></li>
                <li><a class="dropdown-item" href="logout.php">Terminar sessão</a></li>
            </ul>
        </li>
    </ul>
</nav>
<div id="layoutSidenav">
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">Painel</div>
                    <a class="nav-link" href="#overview" data-section-target="overview">
                        <div class="sb-nav-link-icon"><i class="fas fa-gauge"></i></div>
                        Visão geral
                    </a>
                    <a class="nav-link" href="#indicadores" data-section-target="indicadores">
                        <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
                        Indicadores
                    </a>
                    <div class="sb-sidenav-menu-heading">Catálogo</div>
                    <a class="nav-link" href="#categorias" data-section-target="categorias">
                        <div class="sb-nav-link-icon"><i class="fas fa-layer-group"></i></div>
                        Categorias
                    </a>
                    <a class="nav-link" href="#subcategorias" data-section-target="subcategorias">
                        <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                        Subcategorias
                    </a>
                    <a class="nav-link" href="#courses" data-section-target="courses">
                        <div class="sb-nav-link-icon"><i class="fas fa-graduation-cap"></i></div>
                        Cursos
                    </a>
                    <a class="nav-link" href="#inscricoes" data-section-target="inscricoes">
                        <div class="sb-nav-link-icon"><i class="fas fa-clipboard-list"></i></div>
                        Pré-inscrições
                    </a>
                    <div class="sb-sidenav-menu-heading">Conteúdo</div>
                    <a class="nav-link" href="#blogs" data-section-target="blogs">
                        <div class="sb-nav-link-icon"><i class="fas fa-newspaper"></i></div>
                        Blog
                    </a>
                    <a class="nav-link" href="#comunicacoes" data-section-target="comunicacoes">
                        <div class="sb-nav-link-icon"><i class="fas fa-envelope"></i></div>
                        Comunicações
                    </a>
                </div>
            </div>
            <div class="sb-sidenav-footer">
                <div class="small">Sessão activa</div>
                Administrador
            </div>
        </nav>
    </div>
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mt-4 mb-3">
                    <div>
                        <h1 class="mb-0">Painel administrativo</h1>
                        <p class="page-lead mb-0">Administra o catálogo de cursos, o blog e as pré-inscrições a partir deste ecrã.</p>
                    </div>
                    <a class="btn btn-outline-primary mt-3 mt-md-0" href="../index.html" target="_blank" rel="noopener">
                        <i class="fa-solid fa-globe me-2"></i>Ver site público
                    </a>
                </div>

                <?php if ($successMessage || $errorMessage): ?>
                    <div class="app-alerts mb-3">
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($errorMessage): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <section id="overview" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Visão geral</h2>
                        <p>Resumo das métricas e conteúdos activos no site.</p>
                    </div>
                    <div class="metric-grid">
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-briefcase"></i></span>
                            <strong><?php echo number_format((int) $stats['services'], 0, ',', '.'); ?></strong>
                            <span>Serviços concluídos</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-emoji-smile"></i></span>
                            <strong><?php echo number_format((int) $stats['clients'], 0, ',', '.'); ?></strong>
                            <span>Clientes satisfeitos</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-award"></i></span>
                            <strong><?php echo number_format((int) $stats['experience'], 0, ',', '.'); ?>+</strong>
                            <span>Anos de experiência</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-collection"></i></span>
                            <strong><?php echo number_format($totalCategories, 0, ',', '.'); ?></strong>
                            <span>Categorias activas</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-grid"></i></span>
                            <strong><?php echo number_format($totalSubcategories, 0, ',', '.'); ?></strong>
                            <span>Subcategorias disponíveis</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-mortarboard"></i></span>
                            <strong><?php echo number_format($totalCourses, 0, ',', '.'); ?></strong>
                            <span>Cursos publicados</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-person-lines-fill"></i></span>
                            <strong><?php echo number_format($totalRegistrations, 0, ',', '.'); ?></strong>
                            <span>Pré-inscrições recebidas</span>
                        </div>
                        <div class="metric-card">
                            <span class="metric-icon"><i class="bi bi-journal-text"></i></span>
                            <strong><?php echo number_format($totalBlogs, 0, ',', '.'); ?></strong>
                            <span>Artigos no blog</span>
                        </div>
                    </div>
                </section>

                <section id="indicadores" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Indicadores principais</h2>
                        <p>Actualize os números exibidos na página inicial.</p>
                    </div>
                    <div class="module-stack">
                        <section class="module-card">
                            <header>
                                <h3>Indicadores da página inicial</h3>
                                <span>Defina os dados que representam a empresa.</span>
                            </header>
                            <form method="post" action="save_stats.php" class="row g-3">
                                <div class="col-md-4">
                                    <label for="services" class="form-label">Serviços concluídos</label>
                                    <input type="number" min="0" class="form-control" id="services" name="services" value="<?php echo (int) $stats['services']; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="clients" class="form-label">Clientes satisfeitos</label>
                                    <input type="number" min="0" class="form-control" id="clients" name="clients" value="<?php echo (int) $stats['clients']; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="experience" class="form-label">Anos de experiência</label>
                                    <input type="number" min="0" class="form-control" id="experience" name="experience" value="<?php echo (int) $stats['experience']; ?>" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Guardar indicadores</button>
                                </div>
                            </form>
                        </section>
                    </div>
                </section>

                <section id="categorias" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Categorias</h2>
                        <p>Organize o portefólio de cursos por áreas estratégicas.</p>
                    </div>
                    <div class="module-stack">
                        <section class="module-card">
                            <header>
                                <h3>Nova categoria</h3>
                                <span>Cada categoria pode agrupar várias subcategorias e cursos.</span>
                            </header>
                            <form method="post" action="save_course_category.php" class="row g-3">
                                <input type="hidden" name="mode" value="create">
                                <div class="col-md-8 col-lg-6">
                                    <label for="new-course-category" class="form-label">Nome da categoria</label>
                                    <input type="text" class="form-control" id="new-course-category" name="name" placeholder="Formação Executiva" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Adicionar categoria</button>
                                </div>
                            </form>
                        </section>

                        <section class="module-card">
                            <header>
                                <h3>Lista de categorias</h3>
                                <span>Eliminações apenas disponíveis quando não existirem dependências.</span>
                            </header>
                            <?php if (empty($courseCategories)): ?>
                                <div class="course-taxonomy-empty">Ainda não existem categorias registadas.</div>
                            <?php else: ?>
                                <ul class="course-taxonomy-list">
                                    <?php foreach ($courseCategories as $category): ?>
                                        <?php
                                            $categoryId = $category['id'] ?? '';
                                            $categoryName = $category['name'] ?? '—';
                                            $subCount = isset($subcategoriesByCategory[$categoryId]) ? count($subcategoriesByCategory[$categoryId]) : 0;
                                            $courseCount = $categoryCourseCounts[$categoryId] ?? 0;
                                            $canDeleteCategory = $categoryId !== '' && $subCount === 0 && $courseCount === 0;
                                        ?>
                                        <li>
                                            <div class="course-taxonomy-meta">
                                                <span class="course-badge"><i class="bi bi-layers"></i><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="course-taxonomy-count"><?php echo htmlspecialchars($subCount . ' subcat. • ' . $courseCount . ' cursos', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                            <div class="course-taxonomy-actions">
                                                <form method="post" action="delete_course_category.php" onsubmit="return <?php echo $canDeleteCategory ? 'confirm(\'Eliminar esta categoria?\')' : 'false'; ?>;">
                                                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" <?php echo $canDeleteCategory ? '' : 'disabled'; ?>>Eliminar</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="text-muted small mt-3">Remove as subcategorias e cursos associados antes de eliminar uma categoria.</p>
                            <?php endif; ?>
                        </section>
                    </div>
                </section>

                <section id="subcategorias" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Subcategorias</h2>
                        <p>Crie níveis adicionais para organizar os cursos dentro de cada categoria.</p>
                    </div>
                    <div class="module-stack">
                        <section class="module-card">
                            <header>
                                <h3>Nova subcategoria</h3>
                                <span>Seleccione a categoria de destino e descreva a subcategoria.</span>
                            </header>
                            <?php if (empty($courseCategories)): ?>
                                <div class="course-taxonomy-empty">Crie uma categoria antes de adicionar subcategorias.</div>
                            <?php else: ?>
                                <form method="post" action="save_course_subcategory.php" class="row g-3">
                                    <input type="hidden" name="mode" value="create">
                                    <div class="col-md-6">
                                        <label for="new-subcategory-category" class="form-label">Categoria</label>
                                        <select class="form-select" id="new-subcategory-category" name="category_id" required>
                                            <option value="" selected disabled>Selecciona uma categoria</option>
                                            <?php foreach ($courseCategories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new-course-subcategory" class="form-label">Nome da subcategoria</label>
                                        <input type="text" class="form-control" id="new-course-subcategory" name="name" placeholder="Finanças &amp; Contabilidade" required>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Adicionar subcategoria</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </section>

                        <section class="module-card">
                            <header>
                                <h3>Lista de subcategorias</h3>
                                <span>Organize os cursos dentro dos agrupamentos definidos.</span>
                            </header>
                            <?php if (empty($courseSubcategories)): ?>
                                <div class="course-taxonomy-empty">Ainda não existem subcategorias registadas.</div>
                            <?php else: ?>
                                <ul class="course-taxonomy-list">
                                    <?php foreach ($courseSubcategories as $subcategory): ?>
                                        <?php
                                            $subcategoryId = $subcategory['id'] ?? '';
                                            $subcategoryName = $subcategory['name'] ?? '—';
                                            $parentCategoryId = $subcategory['category_id'] ?? '';
                                            $parentCategoryName = $categoryMap[$parentCategoryId] ?? '—';
                                            $subcategoryCourseCount = $subcategoryCourseCounts[$subcategoryId] ?? 0;
                                            $canDeleteSubcategory = $subcategoryId !== '' && $subcategoryCourseCount === 0;
                                        ?>
                                        <li>
                                            <div class="course-taxonomy-meta">
                                                <span class="course-badge"><i class="bi bi-folder"></i><?php echo htmlspecialchars($parentCategoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="course-badge"><i class="bi bi-tag"></i><?php echo htmlspecialchars($subcategoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="course-taxonomy-count"><?php echo htmlspecialchars($subcategoryCourseCount . ' cursos', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                            <div class="course-taxonomy-actions">
                                                <form method="post" action="delete_course_subcategory.php" onsubmit="return <?php echo $canDeleteSubcategory ? 'confirm(\'Eliminar esta subcategoria?\')' : 'false'; ?>;">
                                                    <input type="hidden" name="subcategory_id" value="<?php echo htmlspecialchars($subcategoryId, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" <?php echo $canDeleteSubcategory ? '' : 'disabled'; ?>>Eliminar</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="text-muted small mt-3">Remova os cursos associados antes de eliminar uma subcategoria.</p>
                            <?php endif; ?>
                        </section>
                    </div>
                </section>

                <section id="courses" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Cursos</h2>
                        <p>Adicione ou actualize os programas disponibilizados na página “Cursos”.</p>
                    </div>
                    <div class="course-admin-grid">
                        <section class="module-card">
                            <header>
                                <h3 id="course-form-title">Adicionar curso</h3>
                                <span id="course-form-helper">Preenche os campos para adicionar um novo curso ao catálogo.</span>
                            </header>
                            <form id="course-form" method="post" action="save_course.php" enctype="multipart/form-data">
                                <input type="hidden" name="mode" id="course-mode" value="create">
                                <input type="hidden" name="course_id" id="course-id" value="">
                                <input type="hidden" name="current_cover_image" id="course-current-cover" value="">
                                <div class="mb-3">
                                    <label for="course-category" class="form-label">Categoria</label>
                                    <select class="form-select" id="course-category" name="category_id" required>
                                        <option value="">Selecciona uma categoria</option>
                                        <?php foreach ($courseCategories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="course-subcategory" class="form-label">Subcategoria</label>
                                    <select class="form-select" id="course-subcategory" name="subcategory_id" required>
                                        <option value="">Selecciona uma subcategoria</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="course-title" class="form-label">Título do curso</label>
                                    <input type="text" class="form-control" id="course-title" name="title" placeholder="Elaboração e Técnicas de Negociação de Contratos" required>
                                </div>
                                <div class="mb-3">
                                    <label for="course-headline" class="form-label">Chamada curta</label>
                                    <input type="text" class="form-control" id="course-headline" name="headline" placeholder="Domine a narrativa do programa em poucas palavras">
                                </div>
                                <div class="mb-3">
                                    <label for="course-cover-image" class="form-label">Imagem de capa</label>
                                    <input type="url" class="form-control" id="course-cover-image" name="cover_image" placeholder="https://...">
                                    <input type="file" class="form-control mt-2" id="course-cover-upload" name="cover_image_file" accept="image/png,image/jpeg,image/webp">
                                    <div class="form-text">Carrega uma imagem (JPG, PNG ou WEBP até 2MB) ou indica um endereço público.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="course-price" class="form-label">Preço do curso</label>
                                    <input type="text" class="form-control" id="course-price" name="price" placeholder="Ex.: 150.000 AOA" required>
                                </div>
                                <div class="mb-3">
                                    <label for="course-installments" class="form-label">Pagamentos em prestações</label>
                                    <select class="form-select" id="course-installments" name="installments">
                                        <option value="1">1 prestação</option>
                                        <option value="2">2 prestações</option>
                                        <option value="3">3 prestações</option>
                                        <option value="4">4 prestações</option>
                                    </select>
                                    <div class="form-text">Define o número máximo de prestações disponíveis para este curso.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="course-overview" class="form-label">Descrição geral</label>
                                    <textarea class="form-control" id="course-overview" name="overview" rows="3" placeholder="Apresenta o propósito e o diferencial do programa."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="course-general-objectives" class="form-label">Objectivos gerais</label>
                                    <textarea class="form-control" id="course-general-objectives" name="general_objectives" rows="3" placeholder="Lista cada objectivo numa linha."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="course-specific-objectives" class="form-label">Objectivos específicos</label>
                                    <textarea class="form-control" id="course-specific-objectives" name="specific_objectives" rows="3" placeholder="Lista cada objectivo numa linha."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="course-contents" class="form-label">Conteúdos e módulos</label>
                                    <textarea class="form-control" id="course-contents" name="contents" rows="4" placeholder="Detalha os módulos ou tópicos do programa, um por linha."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="course-details" class="form-label">Informações rápidas</label>
                                    <textarea class="form-control" id="course-details" name="details" rows="3" placeholder="Inclui carga horária, modalidade, certificação, investimento, etc."></textarea>
                                    <div class="form-text">Cada linha é apresentada como um destaque ao lado da descrição pública.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="course-pdf" class="form-label">Ficha ou brochura (URL)</label>
                                    <input type="url" class="form-control" id="course-pdf" name="pdf_url" placeholder="https://...">
                                </div>
                                <div class="course-form-actions">
                                    <button type="submit" class="btn btn-success" id="course-submit">Guardar curso</button>
                                    <button type="button" class="btn btn-outline-primary" id="course-reset">Novo curso</button>
                                </div>
                            </form>
                        </section>
                        <section class="module-card">
                            <header>
                                <h3>Cursos publicados</h3>
                                <span>Lista ordenada por categoria e subcategoria.</span>
                            </header>
                            <?php if (empty($courses)): ?>
                                <div class="course-empty">Ainda não existem cursos registados. Adiciona o primeiro curso para activar a página pública.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="course-list">
                                        <thead>
                                            <tr>
                                                <th>Categoria</th>
                                                <th>Subcategoria</th>
                                                <th>Curso</th>
                                                <th>Preço</th>
                                                <th>Actualização</th>
                                                <th class="text-end">Acções</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courses as $course): ?>
                                                <?php
                                                    $updatedAtRaw = $course['updated_at'] ?? '';
                                                    $updatedDisplay = '—';
                                                    if ($updatedAtRaw !== '') {
                                                        $timestamp = strtotime($updatedAtRaw);
                                                        $updatedDisplay = $timestamp ? date('d/m/Y H:i', $timestamp) : $updatedAtRaw;
                                                    }
                                                    $courseJson = htmlspecialchars(json_encode([
                                                        'id' => $course['id'] ?? '',
                                                        'category_id' => $course['category_id'] ?? '',
                                                        'subcategory_id' => $course['subcategory_id'] ?? '',
                                                        'title' => $course['title'] ?? '',
                                                        'headline' => $course['headline'] ?? '',
                                                        'price' => $course['price'] ?? '',
                                                        'cover_image' => $course['cover_image'] ?? '',
                                                        'installments' => $course['installments'] ?? 1,
                                                        'overview' => $course['overview'] ?? '',
                                                        'general_objectives' => $course['general_objectives'] ?? '',
                                                        'specific_objectives' => $course['specific_objectives'] ?? '',
                                                        'contents' => $course['contents'] ?? '',
                                                        'details' => $course['details'] ?? '',
                                                        'pdf_url' => $course['pdf_url'] ?? '',
                                                    ], $dashboardJsonFlags), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($course['category'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($course['subcategory'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($course['title'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php if (!empty($course['headline'])): ?>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($course['headline'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($course['price'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($updatedDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <div class="course-actions">
                                                            <button type="button" class="btn btn-outline-primary btn-sm" data-course="<?php echo $courseJson; ?>"><i class="bi bi-pencil"></i> Editar</button>
                                                            <form method="post" action="delete_course.php" onsubmit="return confirm('Eliminar este curso?');">
                                                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Eliminar</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </section>

                <section id="inscricoes" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Pré-inscrições</h2>
                        <p>Acompanhe as submissões recebidas pelo formulário público.</p>
                    </div>
                    <section class="module-card">
                        <header>
                            <h3>Registos recentes</h3>
                            <span>Os comprovativos ficam disponíveis para download directo.</span>
                        </header>
                        <?php if (empty($courseRegistrations)): ?>
                            <div class="course-empty">Ainda não recebemos nenhuma pré-inscrição.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="registration-table">
                                    <thead>
                                        <tr>
                                            <th>Participante</th>
                                            <th>Dados adicionais</th>
                                            <th>Curso seleccionado</th>
                                            <th>Mensagem</th>
                                            <th>Data</th>
                                            <th class="text-end">Acções</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courseRegistrations as $registration): ?>
                                            <?php
                                                $submittedAtRaw = $registration['submitted_at'] ?? '';
                                                $submittedDisplay = '—';
                                                if ($submittedAtRaw !== '') {
                                                    $timestamp = strtotime($submittedAtRaw);
                                                    $submittedDisplay = $timestamp ? date('d/m/Y H:i', $timestamp) : $submittedAtRaw;
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($registration['nome'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <span class="registration-meta"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($registration['email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="registration-meta"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($registration['telefone'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($registration['empresa'])): ?>
                                                        <div><?php echo htmlspecialchars($registration['empresa'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($registration['pais'])): ?>
                                                        <span class="registration-meta">País: <?php echo htmlspecialchars($registration['pais'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <span class="registration-meta">BI/NIF: <?php echo htmlspecialchars($registration['documento'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if (!empty($registration['profissao'])): ?>
                                                        <span class="registration-meta">Profissão: <?php echo htmlspecialchars($registration['profissao'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong><?php echo htmlspecialchars($registration['curso'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                    <?php if (!empty($registration['course_price'])): ?>
                                                        <span class="registration-meta">Preço: <?php echo htmlspecialchars($registration['course_price'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($registration['course_id'])): ?>
                                                        <span class="registration-meta">ID: <?php echo htmlspecialchars($registration['course_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($registration['plano_pagamento'])): ?>
                                                        <span class="registration-meta">Plano de pagamento: <?php echo htmlspecialchars($registration['plano_pagamento'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                    <span class="registration-meta">Forma de pagamento: <?php echo htmlspecialchars($registration['forma_pagamento'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if (!empty($registration['comprovativo'])): ?>
                                                        <div class="mt-2">
                                                            <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(admin_asset($registration['comprovativo']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ver comprovativo</a>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="min-width: 220px;">
                                                    <?php echo nl2br(htmlspecialchars($registration['mensagem'] ?? '—', ENT_QUOTES, 'UTF-8')); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($submittedDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end">
                                                    <form method="post" action="delete_registration.php" onsubmit="return confirm('Eliminar esta inscrição?');">
                                                        <input type="hidden" name="registration_id" value="<?php echo htmlspecialchars($registration['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </section>

                <section id="blogs" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Blog</h2>
                        <p>Publica artigos que reforçam a autoridade da marca.</p>
                    </div>
                    <div class="module-stack">
                        <section class="module-card">
                            <header>
                                <h3>Novo artigo</h3>
                                <span>Cria conteúdos com imagem de destaque e resumo.</span>
                            </header>
                            <form method="post" action="save_blog.php" enctype="multipart/form-data" class="row g-3">
                                <input type="hidden" name="mode" value="create">
                                <div class="col-lg-6">
                                    <label class="form-label">Título</label>
                                    <input type="text" class="form-control" name="title" required>
                                </div>
                                <div class="col-lg-6">
                                    <label class="form-label">Imagem de capa</label>
                                    <input type="url" class="form-control" name="image_url" placeholder="https://...">
                                    <input type="file" class="form-control mt-2" name="image_file" accept="image/png,image/jpeg,image/webp">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Resumo</label>
                                    <textarea class="form-control" name="excerpt" rows="3" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Conteúdo</label>
                                    <textarea class="form-control" name="content" rows="6" required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">Publicar artigo</button>
                                </div>
                            </form>
                        </section>

                        <section class="module-card">
                            <header>
                                <h3>Artigos publicados</h3>
                                <span>Editar ou remover conteúdos existentes.</span>
                            </header>
                            <?php if (empty($blogs)): ?>
                                <div class="course-taxonomy-empty">Ainda não existem artigos publicados.</div>
                            <?php else: ?>
                                <div class="blog-grid">
                                    <?php foreach ($blogs as $blog): ?>
                                        <article class="blog-card">
                                            <?php if (!empty($blog['image'])): ?>
                                                <img src="<?php echo htmlspecialchars(admin_asset($blog['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Imagem de destaque">
                                            <?php endif; ?>
                                            <div class="blog-body">
                                                <div>
                                                    <h4 class="h5 mb-1"><?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($blog['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php if (!empty($blog['updated_at'])): ?>
                                                        <div class="blog-meta"><i class="bi bi-clock"></i> Atualizado em <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($blog['updated_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="blog-actions">
                                                    <form method="post" action="delete_blog.php" onsubmit="return confirm('Eliminar este artigo?');">
                                                        <input type="hidden" name="blog_id" value="<?php echo htmlspecialchars($blog['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                    </form>
                                                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#edit-blog-<?php echo htmlspecialchars($blog['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-controls="edit-blog-<?php echo htmlspecialchars($blog['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Editar</button>
                                                </div>
                                                <div class="collapse" id="edit-blog-<?php echo htmlspecialchars($blog['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <div class="card card-body mt-3">
                                                        <form method="post" action="save_blog.php" enctype="multipart/form-data" class="row g-3">
                                                            <input type="hidden" name="mode" value="update">
                                                            <input type="hidden" name="blog_id" value="<?php echo htmlspecialchars($blog['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <div class="col-lg-6">
                                                                <label class="form-label">Título</label>
                                                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                                            </div>
                                                            <div class="col-lg-6">
                                                                <label class="form-label">Imagem actual</label>
                                                                <?php if (!empty($blog['image'])): ?>
                                                                    <div class="small text-muted mb-1">Ficheiro: <?php echo htmlspecialchars($blog['image'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <?php endif; ?>
                                                                <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($blog['image'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="url" class="form-control" name="image_url" placeholder="https://..." value="<?php echo htmlspecialchars($blog['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Substituir imagem</label>
                                                                <input type="file" class="form-control" name="image_file" accept="image/png,image/jpeg,image/webp">
                                                                <div class="form-text">Se carregares um novo ficheiro, substitui a imagem actual.</div>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Resumo</label>
                                                                <textarea class="form-control" name="excerpt" rows="3" required><?php echo htmlspecialchars($blog['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Conteúdo</label>
                                                                <textarea class="form-control" name="content" rows="6" required><?php echo htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                            </div>
                                                            <div class="col-12">
                                                                <button type="submit" class="btn btn-primary">Guardar alterações</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </section>

                <section id="comunicacoes" class="admin-section" data-section>
                    <div class="section-header">
                        <h2>Comunicações</h2>
                        <p>Dados de autenticação da caixa info@jompson.com.</p>
                    </div>
                    <section class="module-card">
                        <header>
                            <h3>Configurações de email</h3>
                            <span>Utiliza estas informações nos clientes de correio.</span>
                        </header>
                        <div class="config-grid">
                            <div class="config-group">
                                <h3>Servidor SMTP (saída)</h3>
                                <dl class="config-list">
                                    <div class="config-item"><dt>Protocolo</dt><dd>SMTP</dd></div>
                                    <div class="config-item"><dt>Host</dt><dd><?php echo htmlspecialchars($smtpConfig['host'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Porta</dt><dd><?php echo htmlspecialchars((string) ($smtpConfig['port'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Encriptação</dt><dd><?php echo htmlspecialchars(strtoupper($smtpConfig['encryption'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Utilizador</dt><dd><?php echo htmlspecialchars($smtpConfig['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Palavra-passe</dt><dd><?php echo htmlspecialchars($smtpConfig['password'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                </dl>
                            </div>
                            <div class="config-group">
                                <h3>Servidor IMAP (entrada)</h3>
                                <dl class="config-list">
                                    <div class="config-item"><dt>Protocolo</dt><dd>IMAP</dd></div>
                                    <div class="config-item"><dt>Host</dt><dd><?php echo htmlspecialchars($imapConfig['host'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Porta</dt><dd><?php echo htmlspecialchars((string) ($imapConfig['port'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Encriptação</dt><dd><?php echo htmlspecialchars(strtoupper($imapConfig['encryption'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Utilizador</dt><dd><?php echo htmlspecialchars($imapConfig['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Palavra-passe</dt><dd><?php echo htmlspecialchars($imapConfig['password'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                </dl>
                            </div>
                            <div class="config-group">
                                <h3>Endereços utilizados</h3>
                                <dl class="config-list">
                                    <div class="config-item"><dt>Remetente</dt><dd><?php echo htmlspecialchars(($emailConfig['from_name'] ?? 'JOMPSON Cursos') . ' <' . ($emailConfig['from_address'] ?? 'info@jompson.com') . '>', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Resposta para</dt><dd><?php echo htmlspecialchars($emailConfig['reply_to_fallback'] ?? 'info@jompson.com', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                    <div class="config-item"><dt>Destino</dt><dd><?php echo htmlspecialchars($emailConfig['to_address'] ?? 'geral@jompson.com', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                </dl>
                            </div>
                        </div>
                    </section>
                </section>
            </div>
        </main>
        <footer class="footer mt-auto">
            <div>&copy; <?php echo date('Y'); ?> Jompson. Todos os direitos reservados.</div>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.dashboardData = <?php echo json_encode($dashboardPayload, $dashboardJsonFlags); ?>;
</script>
<script src="<?php echo htmlspecialchars(admin_asset('admin/assets/js/scripts.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(admin_asset('admin/assets/js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
