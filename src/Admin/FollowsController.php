<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Events;
use App\Follows;
use App\Session;
use App\Settings;
use App\View;

/**
 * 次回のお知らせ（管理画面）：登録者の一覧と、募集を始めたイベントのお知らせの送信。
 */
final class FollowsController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        echo View::render('admin/follows/index', [
            'title' => '次回のお知らせの登録者',
            'admin' => $admin,
            'enabled' => Settings::get('follow_enabled') === '1',
            'follows' => Follows::all(),
            'types' => Events::types(),
            'counts' => Follows::countsByType(),
        ], 'admin/layout');
    }

    /** 募集中のイベントを、その形式の登録者にメールで知らせる（1つのイベントにつき1回だけ） */
    public static function announce(string $eventId): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $back = '/admin/events/' . (int) $event['id'];
        if ($event['status'] !== 'open') {
            Session::flash('error', '「募集中」のイベントだけお知らせを送れます。');
        } elseif ($event['announced_at'] !== null) {
            Session::flash('error', 'このイベントのお知らせは送信済みです（' . fmt_dt($event['announced_at']) . '）。');
        } else {
            $result = Follows::announce($event);
            Session::flash('notice', $result['failed'] > 0
                ? "お知らせを {$result['sent']}人に送りました。{$result['failed']}人には送れませんでした（メールの設定を確認してください）。"
                : "お知らせを {$result['sent']}人に送りました。");
        }
        redirect($back);
    }
}
