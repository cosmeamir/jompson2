<?php
require_once __DIR__ . '/admin/config.php';

header('Content-Type: application/json; charset=UTF-8');

const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método inválido.'
    ], JSON_FLAGS);
    exit;
}

function field(string $key): string
{
    return trim($_POST[$key] ?? '');
}

function random_hex(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }

    try {
        return bin2hex(random_bytes($bytes));
    } catch (Throwable $exception) {
        $fallback = '';
        for ($i = 0; $i < $bytes; $i++) {
            try {
                $fallback .= chr(random_int(0, 255));
            } catch (Throwable $innerException) {
                $fallback .= chr(mt_rand(0, 255));
            }
        }

        return bin2hex($fallback);
    }
}

function get_email_config(): array
{
    return defined('EMAIL_CONFIG') && is_array(EMAIL_CONFIG) ? EMAIL_CONFIG : [];
}

function normalise_crlf(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return str_replace("\n", "\r\n", $text);
}

function smtp_read_response($stream): array
{
    $response = '';
    while (($line = fgets($stream, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === '-') {
            continue;
        }
        break;
    }

    $code = (int) substr($response, 0, 3);

    return [$code, $response];
}

function smtp_expect($stream, array $expectedCodes): bool
{
    [$code] = smtp_read_response($stream);
    return in_array($code, $expectedCodes, true);
}

function smtp_command($stream, string $command, array $expectedCodes): bool
{
    if (@fwrite($stream, $command . "\r\n") === false) {
        return false;
    }

    return smtp_expect($stream, $expectedCodes);
}

function smtp_send_message(array $smtpConfig, string $fromAddress, string $toAddress, string $message): bool
{
    $host = trim($smtpConfig['host'] ?? '');
    $port = (int) ($smtpConfig['port'] ?? 0);
    $encryption = strtolower(trim($smtpConfig['encryption'] ?? ''));
    $username = (string) ($smtpConfig['username'] ?? '');
    $password = (string) ($smtpConfig['password'] ?? '');
    $timeout = (int) ($smtpConfig['timeout'] ?? 30);

    if ($host === '' || $port <= 0) {
        return false;
    }

    $remote = $host . ':' . $port;
    $transport = $remote;
    $contextOptions = [];

    if ($encryption === 'ssl') {
        $transport = 'ssl://' . $remote;
        $contextOptions['ssl'] = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ];
    }

    $context = empty($contextOptions) ? null : stream_context_create($contextOptions);
    $stream = @stream_socket_client($transport, $errno, $errstr, $timeout ?: 30, STREAM_CLIENT_CONNECT, $context);

    if (!is_resource($stream)) {
        return false;
    }

    stream_set_timeout($stream, $timeout ?: 30);

    if (!smtp_expect($stream, [220])) {
        fclose($stream);
        return false;
    }

    $ehloDomain = 'jompson.com';
    if (!smtp_command($stream, 'EHLO ' . $ehloDomain, [250])) {
        fclose($stream);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_command($stream, 'STARTTLS', [220])) {
            fclose($stream);
            return false;
        }

        if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($stream);
            return false;
        }

        if (!smtp_command($stream, 'EHLO ' . $ehloDomain, [250])) {
            fclose($stream);
            return false;
        }
    }

    if ($username !== '' && $password !== '') {
        if (!smtp_command($stream, 'AUTH LOGIN', [334])) {
            fclose($stream);
            return false;
        }

        if (!smtp_command($stream, base64_encode($username), [334])) {
            fclose($stream);
            return false;
        }

        if (!smtp_command($stream, base64_encode($password), [235])) {
            fclose($stream);
            return false;
        }
    }

    if (!smtp_command($stream, 'MAIL FROM:<' . $fromAddress . '>', [250])) {
        fclose($stream);
        return false;
    }

    if (!smtp_command($stream, 'RCPT TO:<' . $toAddress . '>', [250, 251])) {
        fclose($stream);
        return false;
    }

    if (!smtp_command($stream, 'DATA', [354])) {
        fclose($stream);
        return false;
    }

    $message = normalise_crlf($message);
    $message = preg_replace('/(^|\r\n)\./', '$1..', $message);
    if (substr($message, -2) !== "\r\n") {
        $message .= "\r\n";
    }

    if (@fwrite($stream, $message . ".\r\n") === false) {
        fclose($stream);
        return false;
    }

    if (!smtp_expect($stream, [250])) {
        fclose($stream);
        return false;
    }

    smtp_command($stream, 'QUIT', [221]);
    fclose($stream);

    return true;
}

function send_registration_notification(string $subject, string $body, string $replyTo): array
{
    $config = get_email_config();
    $fromAddress = filter_var($config['from_address'] ?? '', FILTER_VALIDATE_EMAIL) ?: 'no-reply@jompson.com';
    $fromName = $config['from_name'] ?? 'JOMPSON Cursos';
    $fallbackReply = filter_var($config['reply_to_fallback'] ?? '', FILTER_VALIDATE_EMAIL) ?: $fromAddress;
    $toAddress = filter_var($config['to_address'] ?? '', FILTER_VALIDATE_EMAIL) ?: 'geral@jompson.com';
    $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ?: $fallbackReply;

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $messageId = sprintf('<%s@jompson.com>', random_hex(16));
    $originIp = $_SERVER['REMOTE_ADDR'] ?? '';

    $baseHeaders = [
        'MIME-Version: 1.0',
        'Date: ' . date(DATE_RFC2822),
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $replyTo,
        'Message-ID: ' . $messageId,
        'X-Mailer: JOMPSON Dashboard',
    ];

    if ($originIp !== '') {
        $baseHeaders[] = 'X-Originating-IP: ' . $originIp;
    }

    $smtpHeaders = array_merge([
        'To: ' . $toAddress,
        'Subject: ' . $encodedSubject,
    ], $baseHeaders);

    $message = implode("\r\n", $smtpHeaders) . "\r\n\r\n" . $body;

    $smtpConfig = $config['smtp'] ?? [];
    $sentVia = 'smtp';
    $sent = false;

    if (!empty($smtpConfig)) {
        $sent = smtp_send_message($smtpConfig, $fromAddress, $toAddress, $message);
    }

    if (!$sent) {
        $sentVia = 'mail';
        $mailHeaders = implode("\r\n", array_merge($baseHeaders, ['To: ' . $toAddress]));
        $sent = @mail($toAddress, $encodedSubject, $body, $mailHeaders, '-f' . $fromAddress);
    }

    return ['sent' => (bool) $sent, 'transport' => $sentVia];
}

$empresa = field('empresa');
$nome = field('nome');
$pais = field('pais');
$email = field('email');
$telefone = field('telefone');
$documento = field('documento');
$profissao = field('profissao');
$curso = field('curso');
$courseId = field('course_id');
$coursePrice = field('course_price');
$formaPagamento = field('forma_pagamento');
$mensagem = field('mensagem');
$comprovativo = $_FILES['comprovativo'] ?? null;
$comprovativoMeta = null;

$data = load_data();
$coursesData = $data['courses'] ?? [];

$errors = [];

if ($nome === '') {
    $errors[] = 'Indica o teu nome.';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Fornece um email válido.';
}

if ($telefone === '') {
    $errors[] = 'Indica um número de telefone.';
}

if ($documento === '') {
    $errors[] = 'Indica o nº de BI ou NIF.';
}

if ($curso === '') {
    $errors[] = 'Selecciona um curso antes de enviar a pré-inscrição.';
}

if ($courseId === '') {
    $errors[] = 'Selecciona um curso válido antes de enviar a pré-inscrição.';
}

$allowedPayments = ['Transferência Bancária'];
if ($formaPagamento === '') {
    $errors[] = 'Selecciona a forma de pagamento preferencial.';
} elseif (!in_array($formaPagamento, $allowedPayments, true)) {
    $errors[] = 'A forma de pagamento seleccionada é inválida.';
} else {
    $formaPagamento = $allowedPayments[0];
}

$matchedCourse = null;
if ($courseId !== '') {
    foreach ($coursesData as $courseEntry) {
        if (($courseEntry['id'] ?? '') === $courseId) {
            $matchedCourse = $courseEntry;
            break;
        }
    }
}

if ($matchedCourse === null) {
    $errors[] = 'O curso seleccionado já não está disponível. Actualiza a página e tenta novamente.';
} else {
    $curso = $matchedCourse['title'] ?? $curso;
    $coursePrice = $matchedCourse['price'] ?? $coursePrice;
}

$allowedProofMimes = [
    'application/pdf' => 'pdf',
    'application/x-pdf' => 'pdf',
    'application/acrobat' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
];

if (!isset($comprovativo) || !is_array($comprovativo) || ($comprovativo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors[] = 'Anexa o comprovativo da transferência.';
} else {
    $proofSize = (int) ($comprovativo['size'] ?? 0);
    if ($proofSize <= 0) {
        $errors[] = 'O comprovativo enviado é inválido.';
    } elseif ($proofSize > 1048576) {
        $errors[] = 'O comprovativo deve ter no máximo 1MB.';
    } else {
        $mimeType = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = (string) $finfo->file($comprovativo['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = (string) mime_content_type($comprovativo['tmp_name']);
        }

        if ($mimeType === '' && isset($comprovativo['name'])) {
            $extension = strtolower((string) pathinfo($comprovativo['name'], PATHINFO_EXTENSION));
            $extensionToMime = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ];
            $mimeType = $extensionToMime[$extension] ?? '';
        }

        if ($mimeType === '' || !isset($allowedProofMimes[$mimeType])) {
            $errors[] = 'O comprovativo deve ser um ficheiro PDF, JPG ou PNG.';
        } else {
            $comprovativoMeta = [
                'tmp_name' => $comprovativo['tmp_name'],
                'extension' => $allowedProofMimes[$mimeType],
                'mime' => $mimeType,
                'original_name' => $comprovativo['name'] ?? '',
            ];
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => implode(' ', $errors)
    ], JSON_FLAGS);
    exit;
}

$uploadRelativePath = '';
if ($comprovativoMeta !== null) {
    $uploadDir = __DIR__ . '/uploads/comprovativos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível preparar o diretório de comprovativos.'
        ], JSON_FLAGS);
        exit;
    }

    $proofFilename = 'comprovativo-' . random_hex(12) . '.' . $comprovativoMeta['extension'];
    $targetPath = $uploadDir . '/' . $proofFilename;

    if (!move_uploaded_file($comprovativoMeta['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível guardar o comprovativo enviado. Tenta novamente.'
        ], JSON_FLAGS);
        exit;
    }

    $uploadRelativePath = 'uploads/comprovativos/' . $proofFilename;
}

$registrations = $data['course_registrations'] ?? [];

$registration = [
    'id' => 'registration-' . random_hex(6),
    'empresa' => $empresa,
    'nome' => $nome,
    'pais' => $pais,
    'email' => $email,
    'telefone' => $telefone,
    'documento' => $documento,
    'profissao' => $profissao,
    'curso' => $curso,
    'course_id' => $courseId,
    'course_price' => $coursePrice,
    'forma_pagamento' => $formaPagamento,
    'mensagem' => $mensagem,
    'comprovativo' => $uploadRelativePath,
    'comprovativo_mime' => $comprovativoMeta['mime'] ?? '',
    'comprovativo_original' => $comprovativoMeta['original_name'] ?? '',
    'submitted_at' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];

$registrations[] = $registration;
$data['course_registrations'] = $registrations;
save_data($data);

$subject = 'Nova pré-inscrição - ' . $curso;

$lines = [
    'Nova pré-inscrição recebida a partir do site da JOMPSON.',
    '',
    'Curso: ' . $curso,
    'Nome: ' . $nome,
    'Empresa: ' . ($empresa !== '' ? $empresa : '—'),
    'País: ' . ($pais !== '' ? $pais : '—'),
    'Email: ' . $email,
    'Telefone: ' . $telefone,
    'Nº de BI/NIF: ' . $documento,
    'Profissão: ' . ($profissao !== '' ? $profissao : '—'),
    'ID do curso: ' . ($courseId !== '' ? $courseId : '—'),
    'Preço indicado: ' . ($coursePrice !== '' ? $coursePrice : '—'),
    'Forma de pagamento: ' . $formaPagamento,
    'Comprovativo: ' . ($uploadRelativePath !== '' ? $uploadRelativePath : '—'),
    '',
    'Mensagem:',
    $mensagem !== '' ? $mensagem : '—',
    '',
    'Registado em: ' . date('d/m/Y H:i'),
    'IP: ' . ($registration['ip'] ?: '—'),
];

$body = implode("\n", $lines);

$emailResult = send_registration_notification($subject, $body, $email);
$emailSent = $emailResult['sent'];
$emailTransport = $emailResult['transport'];

http_response_code(200);

echo json_encode([
    'success' => true,
    'emailSent' => $emailSent,
    'transport' => $emailTransport,
], JSON_FLAGS);
