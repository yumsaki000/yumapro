<?php

declare(strict_types=1);

namespace App\Admin;

use App\Admins;
use App\Auth;
use App\Form;
use App\Session;
use App\View;

/**
 * 運営メンバー（管理画面のアカウント）の管理。オーナーだけが使える。
 */
final class MembersController
{
    public static function index(): void
    {
        $admin = Auth::requireOwner();
        echo View::render('admin/members/index', [
            'title' => '運営メンバー',
            'admin' => $admin,
            'members' => Admins::all(),
        ], 'admin/layout');
    }

    public static function create(): void
    {
        $admin = Auth::requireOwner();
        $values = ['login_id' => '', 'display_name' => '', 'role' => 'staff'];
        $errors = [];

        if (is_post()) {
            $values = [
                'login_id' => Form::str($_POST, 'login_id'),
                'display_name' => Form::str($_POST, 'display_name'),
                'role' => Form::str($_POST, 'role'),
            ];
            $password = Form::raw($_POST, 'password');
            $errors = Admins::validate($values['login_id'], $values['display_name'], $values['role'], $password, true);
            if ($errors === []) {
                $generated = $password === '';
                if ($generated) {
                    $password = Admins::generatePassword();
                }
                $id = Admins::create($values['login_id'], $values['display_name'], $values['role'], $password);
                Session::flash('notice', "「{$values['display_name']}」を追加しました。");
                if ($generated) {
                    Session::flash('password', $password);
                }
                redirect('/admin/members/' . $id);
                return;
            }
        }

        echo View::render('admin/members/form', [
            'title' => 'メンバーを追加',
            'admin' => $admin,
            'values' => $values,
            'errors' => $errors,
        ], 'admin/layout');
    }

    public static function edit(string $id): void
    {
        $admin = Auth::requireOwner();
        $member = Admins::find((int) $id) ?? abort_not_found();
        $isSelf = (int) $member['id'] === (int) $admin['id'];
        $values = [
            'display_name' => $member['display_name'],
            'role' => $member['role'],
            'is_active' => (int) $member['is_active'] === 1,
        ];
        $errors = [];

        if (is_post()) {
            $values = [
                'display_name' => Form::str($_POST, 'display_name'),
                'role' => $isSelf ? 'owner' : Form::str($_POST, 'role'),
                'is_active' => $isSelf ? true : Form::checked($_POST, 'is_active'),
            ];
            $errors = Admins::validate($member['login_id'], $values['display_name'], $values['role'], null, false);
            $wasActiveOwner = $member['role'] === 'owner' && (int) $member['is_active'] === 1;
            $staysActiveOwner = $values['role'] === 'owner' && $values['is_active'];
            if ($wasActiveOwner && !$staysActiveOwner && Admins::countActiveOwners() <= 1) {
                $errors[] = 'オーナーが1人もいなくなるため、この変更はできません。先に別の人をオーナーにしてください。';
            }
            if ($errors === []) {
                Admins::update((int) $member['id'], $values['display_name'], $values['role'], $values['is_active']);
                Session::flash('notice', "「{$values['display_name']}」を保存しました。");
                redirect('/admin/members');
                return;
            }
        }

        echo View::render('admin/members/edit', [
            'title' => $member['display_name'],
            'admin' => $admin,
            'member' => $member,
            'isSelf' => $isSelf,
            'values' => $values,
            'errors' => $errors,
            'generatedPassword' => Session::flash('password'),
        ], 'admin/layout');
    }

    public static function password(string $id): void
    {
        Auth::requireOwner();
        $member = Admins::find((int) $id) ?? abort_not_found();
        $password = Form::raw($_POST, 'password');
        if ($password !== '' && mb_strlen($password) < Admins::MIN_PASSWORD_LENGTH) {
            Session::flash('error', 'パスワードは' . Admins::MIN_PASSWORD_LENGTH . '文字以上にしてください。');
            redirect('/admin/members/' . $member['id']);
            return;
        }
        $generated = $password === '';
        if ($generated) {
            $password = Admins::generatePassword();
        }
        Admins::setPassword((int) $member['id'], $password);
        Session::flash('notice', "「{$member['display_name']}」のパスワードを再設定しました。ログインの一時停止も解除しています。");
        if ($generated) {
            Session::flash('password', $password);
        }
        redirect('/admin/members/' . $member['id']);
    }
}
