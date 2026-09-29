<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Channels;
use App\Form;
use App\Normalize;
use App\Session;
use App\View;

/**
 * 設定：「どこで知りましたか」の選択肢の増減と並び順。
 */
final class ChannelsController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $errors = [];
        $newName = '';

        if (is_post()) {
            if (Form::str($_POST, 'action') === 'add') {
                $newName = Normalize::name(Form::str($_POST, 'name')) ?? '';
                if ($newName === '' || mb_strlen($newName) > 50) {
                    $errors[] = '選択肢は1〜50文字で入れてください。';
                } elseif (Channels::exists($newName)) {
                    $errors[] = "「{$newName}」はもうあります。";
                } else {
                    Channels::add($newName);
                    Session::flash('notice', "「{$newName}」を追加しました。");
                    redirect('/admin/channels');
                    return;
                }
            } else {
                $orders = is_array($_POST['sort_order'] ?? null) ? $_POST['sort_order'] : [];
                $actives = is_array($_POST['is_active'] ?? null) ? $_POST['is_active'] : [];
                $rows = [];
                foreach (Channels::all() as $channel) {
                    $id = (int) $channel['id'];
                    $order = Form::int($orders, (string) $id);
                    $rows[$id] = [
                        'sort_order' => is_int($order) ? $order : (int) $channel['sort_order'],
                        'is_active' => isset($actives[$id]),
                    ];
                }
                Channels::updateAll($rows);
                Session::flash('notice', '並び順と表示を保存しました。');
                redirect('/admin/channels');
                return;
            }
        }

        echo View::render('admin/channels/index', [
            'title' => '設定',
            'admin' => $admin,
            'channels' => Channels::all(),
            'errors' => $errors,
            'newName' => $newName,
        ], 'admin/layout');
    }
}
