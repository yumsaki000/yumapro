<?php

declare(strict_types=1);

namespace App\Web;

use App\Events;
use App\Follows;
use App\Form;
use App\Normalize;
use App\Session;
use App\Settings;
use App\View;

/**
 * 次回のお知らせの登録（参加者向け）：登録・確認・形式の変更・停止。
 */
final class FollowController
{
    /** 登録（イベント一覧・イベントページの登録欄から） */
    public static function subscribe(): void
    {
        $back = self::safeBack(Form::str($_POST, 'back'));
        if (Settings::get('follow_enabled') !== '1') {
            redirect($back);
            return;
        }
        // ロボット避け：見えない欄に何か入っていたら、受け付けたふりをして終える
        if (Form::str($_POST, 'website') !== '') {
            redirect('/follow/sent');
            return;
        }
        $email = Normalize::email(Form::str($_POST, 'email'));
        $typeIds = Follows::validTypeIds($_POST['types'] ?? []);
        $errors = [];
        if ($email === null) {
            $errors[] = 'メールアドレスの形を確かめてください。';
        }
        if ($typeIds === []) {
            $errors[] = 'お知らせを受け取る形式を1つ以上選んでください。';
        }
        if (!Form::checked($_POST, 'consent')) {
            $errors[] = 'お知らせのメールを受け取ることに同意してください。';
        }
        if ($errors !== []) {
            Session::flash('error', implode("\n", $errors));
            redirect($back . '#follow');
            return;
        }
        $result = Follows::subscribe($email, $typeIds);
        if ($result['needsConfirm']) {
            Follows::sendConfirm($result['follow']);
        }
        // 登録済みかどうかが他人に分からないよう、どの場合も同じ画面にする
        redirect('/follow/sent');
    }

    public static function sent(): void
    {
        echo View::render('follow/sent', ['title' => '確認メールを送りました']);
    }

    /** 本人のリンク：初めて開いたときに登録が完了し、形式の変更・停止ができる */
    public static function manage(string $token): void
    {
        $follow = Follows::findByToken($token) ?? abort_not_found();
        if (is_post()) {
            $action = Form::choice($_POST, 'action', ['save', 'stop', 'resume'], 'save');
            if ($action === 'stop') {
                Follows::unsubscribe((int) $follow['id']);
                Session::flash('notice', 'お知らせの受け取りを止めました。');
            } elseif ($action === 'resume') {
                Follows::resume((int) $follow['id']);
                Session::flash('notice', 'お知らせの受け取りを再開しました。');
            } else {
                $typeIds = Follows::validTypeIds($_POST['types'] ?? []);
                if ($typeIds === []) {
                    Session::flash('error', '形式を1つ以上選んでください。受け取りを止めるときは「お知らせを止める」を押してください。');
                } else {
                    Follows::setTypes((int) $follow['id'], $typeIds);
                    Session::flash('notice', 'お知らせを受け取る形式を保存しました。');
                }
            }
            redirect('/follow/' . $follow['token']);
            return;
        }
        if ($follow['confirmed_at'] === null && $follow['unsubscribed_at'] === null) {
            Follows::confirm((int) $follow['id']);
            Session::flash('notice', '登録が完了しました。募集が始まったらメールでお知らせします。');
            redirect('/follow/' . $follow['token']);
            return;
        }
        header('Cache-Control: no-store');
        echo View::render('follow/manage', [
            'title' => '次回のお知らせ',
            'follow' => $follow,
            'types' => Events::types(),
            'selected' => Follows::typeIds((int) $follow['id']),
        ]);
    }

    /** 戻り先はこのサイトの中（/ で始まり // でない）だけ */
    private static function safeBack(string $back): string
    {
        return preg_match('#\A/(?!/)[^\s]*\z#', $back) ? $back : '/';
    }
}
