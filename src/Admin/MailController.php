<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Events;
use App\Form;
use App\MailJobs;
use App\Session;

/**
 * 回ごとのメールを今すぐ送る（前日リマインド・お礼）。cron が動いていないときや、時間を待たずに送りたいとき用。
 */
final class MailController
{
    public static function send(string $eventId): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $kind = Form::choice($_POST, 'kind', ['reminder', 'thanks'], '');
        if ($kind === '') {
            Session::flash('error', 'メールの種類が正しくありません。');
        } else {
            $result = $kind === 'reminder' ? MailJobs::sendReminders((int) $event['id']) : MailJobs::sendThanks((int) $event['id']);
            $label = $kind === 'reminder' ? 'リマインド' : 'お礼';
            Session::flash('notice', "{$label}メールを {$result['sent']}人に送りました。" . ($result['skipped'] > 0 ? "（{$result['skipped']}人は送れませんでした）" : '') . ' すでに送った人・メールアドレスのない人には送りません。');
        }
        redirect('/admin/events/' . $event['id']);
    }
}
