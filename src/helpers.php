<?php

declare(strict_types=1);

/**
 * HTMLに出す値は必ずこれを通す（XSS対策）
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSONを返して終わる
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * 別の画面へ移す
 */
function redirect(string $path, int $status = 303): void
{
    header('Location: ' . $path, true, $status);
}

/**
 * フォームに入れるCSRFトークン。POSTするフォームには必ず入れる。
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . App\Csrf::FIELD . '" value="' . e(App\Csrf::token()) . '">';
}

/**
 * ログイン後に戻る先。管理画面の中のパスだけ許す（外のサイトへ飛ばされないように）。
 */
function safe_admin_path(mixed $next): string
{
    if (is_string($next) && preg_match('#^/admin(?:/[A-Za-z0-9/_\-]*)?(?:\?[A-Za-z0-9=&_%\-]*)?$#', $next)) {
        return $next;
    }
    return '/admin';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
