<?php

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        header("Location: {$path}");
        exit();
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('get_user_role')) {
    function get_user_role(): string
    {
        return (string)($_SESSION['role'] ?? 'guest');
    }
}

if (!function_exists('dashboard_for_role')) {
    function dashboard_for_role(string $role): string
    {
        if ($role === 'admin') {
            return 'admin_dashboard.php';
        }
        if ($role === 'teacher') {
            return 'teacher_dashboard.php';
        }
        return 'student_dashboard.php';
    }
}

if (!function_exists('random_code')) {
    function random_code(int $length = 8): string
    {
        return strtoupper(substr(bin2hex(random_bytes(8)), 0, $length));
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
}

if (!function_exists('dev_mail_log')) {
    function dev_mail_log(string $subject, string $message): void
    {
        $logFile = __DIR__ . '/../dev_mail.log';
        $entry = sprintf(
            "[%s] %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $subject,
            $message
        );
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

if (!function_exists('create_token_string')) {
    function create_token_string(): string
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('token_hash')) {
    function token_hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
