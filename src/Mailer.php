<?php

declare(strict_types=1);

namespace App;

/**
 * メールを送る。MAIL_DRIVER=mail ならサーバーの mail()、log なら storage/mail/ にファイルとして書く（ローカル用）。
 * 送った結果は mail_log に残す。
 */
final class Mailer
{
    /**
     * @return bool 送れたか
     */
    public static function send(string $to, string $subject, string $body, string $kind, ?int $registrationId = null, ?int $customerId = null): bool
    {
        $error = null;
        try {
            $ok = self::deliver($to, $subject, $body);
            if (!$ok) {
                $error = 'mail() が false を返しました';
            }
        } catch (\Throwable $e) {
            $ok = false;
            $error = mb_substr($e->getMessage(), 0, 255);
            error_log((string) $e);
        }
        Database::pdo()->prepare(
            'INSERT INTO mail_log (kind, registration_id, customer_id, to_email, subject, status, error) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$kind, $registrationId, $customerId, $to, mb_substr($subject, 0, 255), $ok ? 'sent' : 'failed', $error]);
        return $ok;
    }

    /** この申込にこの種類のメールを送ったことがあるか */
    public static function alreadySent(string $kind, int $registrationId): bool
    {
        $stmt = Database::pdo()->prepare("SELECT 1 FROM mail_log WHERE kind = ? AND registration_id = ? AND status = 'sent' LIMIT 1");
        $stmt->execute([$kind, $registrationId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function fromAddress(): string
    {
        $address = trim(Settings::get('mail_from_address'));
        if ($address !== '') {
            return $address;
        }
        $host = (string) parse_url((string) Config::get('APP_URL', ''), PHP_URL_HOST);
        return 'noreply@' . ($host !== '' ? $host : 'localhost');
    }

    private static function deliver(string $to, string $subject, string $body): bool
    {
        $driver = Config::get('MAIL_DRIVER') ?? (Config::get('APP_ENV') === 'production' ? 'mail' : 'log');
        if ($driver === 'log') {
            $dir = APP_ROOT . '/storage/mail';
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                throw new \RuntimeException('storage/mail を作れません');
            }
            $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
            file_put_contents($file, "To: {$to}\nSubject: {$subject}\n\n{$body}\n");
            return true;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromName = mb_encode_mimeheader(Settings::get('mail_from_name'), 'UTF-8', 'B', "\r\n");
        $from = self::fromAddress();
        $headers = [
            "From: {$fromName} <{$from}>",
            "Reply-To: {$from}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
        return mail($to, $encodedSubject, str_replace("\r\n", "\n", $body), implode("\r\n", $headers), '-f' . $from);
    }
}
