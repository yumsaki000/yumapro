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

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * 今のURLのパス部分（/admin/events など）
 */
function current_path(): string
{
    return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
}

/**
 * 金額の表示：3,000円。null は「—」
 */
function yen(int|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return number_format((int) $amount) . '円';
}

/**
 * 日時の表示：2026/10/03（土）19:00
 */
function fmt_dt(?string $datetime, bool $withTime = true): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $week = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)];
    return date('Y/m/d', $ts) . "（{$week}）" . ($withTime ? date('H:i', $ts) : '');
}

/**
 * <input type="datetime-local"> に入れる形（2026-10-03T19:00）
 */
function dt_input(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
}

/**
 * 404 の画面を出して終える。管理画面の中なら管理画面の見た目で出す
 */
function abort_not_found(): never
{
    http_response_code(404);
    $layout = str_starts_with(current_path(), '/admin') ? 'admin/layout' : 'layout';
    echo App\View::render('errors/404', ['title' => 'ページが見つかりません'], $layout);
    exit;
}

/**
 * 403 の画面を出して終える（権限がない操作）
 */
function abort_forbidden(string $message = 'この操作をする権限がありません。'): never
{
    http_response_code(403);
    echo App\View::render('errors/403', ['title' => '権限がありません', 'message' => $message], 'admin/layout');
    exit;
}
