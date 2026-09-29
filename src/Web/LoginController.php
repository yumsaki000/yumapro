<?php

declare(strict_types=1);

namespace App\Web;

use App\CustomerAuth;
use App\Form;
use App\MailTemplates;
use App\Session;
use App\View;

/**
 * 参加者のログイン（メールで届くリンク）。
 */
final class LoginController
{
    public static function form(): void
    {
        if (CustomerAuth::current() !== null) {
            redirect(CustomerAuth::safeNext($_GET['next'] ?? null));
            return;
        }
        echo View::render('auth/login', [
            'title' => 'ログイン',
            'next' => CustomerAuth::safeNext($_GET['next'] ?? null),
            'email' => '',
            'error' => null,
        ]);
    }

    public static function send(): void
    {
        $email = Form::str($_POST, 'email');
        $next = CustomerAuth::safeNext($_POST['next'] ?? null);
        if (Form::str($_POST, 'website') !== '' || $email === '') {
            redirect('/login');
            return;
        }
        $issued = CustomerAuth::issueLink($email);
        if ($issued !== null) {
            MailTemplates::sendCustomerMail('login', $issued['customer'], ['login_link' => $issued['url'] . ($next !== '/my' ? '?next=' . rawurlencode($next) : '')]);
        }
        // 登録の有無が分からないよう、どちらでも同じ画面を出す
        echo View::render('auth/login_sent', ['title' => 'メールをお送りしました', 'email' => $email]);
    }

    public static function verify(string $token): void
    {
        $customer = CustomerAuth::consume($token);
        if ($customer === null) {
            http_response_code(410);
            echo View::render('auth/login', [
                'title' => 'ログイン',
                'next' => CustomerAuth::safeNext($_GET['next'] ?? null),
                'email' => '',
                'error' => 'このリンクは使えません（期限切れか、すでに使われています）。もう一度メールアドレスを入れてください。',
            ]);
            return;
        }
        CustomerAuth::login((int) $customer['id']);
        Session::flash('notice', "{$customer['name']} 様、ログインしました。");
        redirect(CustomerAuth::safeNext($_GET['next'] ?? null));
    }

    public static function logout(): void
    {
        CustomerAuth::logout();
        Session::flash('notice', 'ログアウトしました。');
        redirect('/');
    }
}
