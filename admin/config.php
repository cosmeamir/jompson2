<?php
session_start();

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'jompson#2025';
const DATA_FILE = __DIR__ . '/../data/site-data.json';
const UPLOAD_DIR = __DIR__ . '/../uploads/blog';
const UPLOAD_URL = 'uploads/blog';
const MAX_UPLOAD_SIZE = 2 * 1024 * 1024; // 2MB
const COURSE_UPLOAD_DIR = __DIR__ . '/../uploads/courses';
const COURSE_UPLOAD_URL = 'uploads/courses';
const COURSE_MAX_UPLOAD_SIZE = 2 * 1024 * 1024; // 2MB

const EMAIL_CONFIG = [
    'from_name' => 'JOMPSON Cursos',
    'from_address' => 'info@jompson.com',
    'reply_to_fallback' => 'info@jompson.com',
    'to_address' => 'geral@jompson.com',
    'smtp' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'info@jompson.com',
        'password' => 'Info#jompson2025',
        'timeout' => 30,
    ],
    'imap' => [
        'host' => 'imap.hostinger.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'info@jompson.com',
        'password' => 'Info#jompson2025',
    ],
];

function load_data(): array
{
    if (!file_exists(DATA_FILE)) {
        return [
            'stats' => [
                'services' => 0,
                'clients' => 0,
                'experience' => 0,
            ],
            'blogs' => [],
            'course_categories' => [],
            'course_subcategories' => [],
            'courses' => [],
            'course_registrations' => [],
        ];
    }

    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [
            'stats' => [
                'services' => 0,
                'clients' => 0,
                'experience' => 0,
            ],
            'blogs' => [],
            'course_categories' => [],
            'course_subcategories' => [],
            'courses' => [],
            'course_registrations' => [],
        ];
    }

    $data['stats'] = $data['stats'] ?? [];
    $data['blogs'] = $data['blogs'] ?? [];
    $data['course_categories'] = $data['course_categories'] ?? [];
    $data['course_subcategories'] = $data['course_subcategories'] ?? [];
    $data['courses'] = $data['courses'] ?? [];
    $data['course_registrations'] = $data['course_registrations'] ?? [];

    return $data;
}

function save_data(array $data): void
{
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(DATA_FILE, $json, LOCK_EX);
}

function ensure_logged_in(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text ?: 'post-' . uniqid();
}
