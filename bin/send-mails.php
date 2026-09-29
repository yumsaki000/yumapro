<?php

declare(strict_types=1);

/*
 * 決まった時に送るメール（前日リマインド・翌日お礼）を送る。cron から毎時流す。
 *
 *   php bin/send-mails.php
 *
 * 何度流しても、同じ人に同じメールは二度送らない（mail_log で確かめる）。cron の設定は docs/deploy-xserver.md。
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\MailJobs;

$reminders = MailJobs::sendReminders();
$thanks = MailJobs::sendThanks();
fwrite(STDOUT, sprintf(
    "[%s] リマインド: %d人に送信（%d人は送れず）／お礼: %d人に送信（%d人は送れず）\n",
    date('Y-m-d H:i'), $reminders['sent'], $reminders['skipped'], $thanks['sent'], $thanks['skipped']
));
