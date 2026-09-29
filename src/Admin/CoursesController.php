<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Config;
use App\Courses;
use App\Form;
use App\MailTemplates;
use App\Registrations;
use App\Session;
use App\View;

/**
 * 講座（動画・記事）と購入の管理。
 */
final class CoursesController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        echo View::render('admin/courses/index', [
            'title' => '講座',
            'admin' => $admin,
            'courses' => Courses::all(),
            'pendingPurchases' => count(Courses::purchases('pending')),
        ], 'admin/layout');
    }

    public static function create(): void
    {
        $admin = Auth::requireAdmin();
        $values = ['title' => '', 'description' => null, 'access' => 'crew', 'price' => null, 'crew_included' => 1, 'status' => 'draft', 'sort_order' => 0];
        $errors = [];
        if (is_post()) {
            [$values, $errors] = self::read($_POST);
            if ($errors === []) {
                $id = Courses::create($values, (int) $admin['id']);
                Session::flash('notice', "講座「{$values['title']}」を作りました。次に講座の回（第1回・第2回…）を追加してください。");
                redirect('/admin/courses/' . $id);
                return;
            }
        }
        echo View::render('admin/courses/form', ['title' => '新しい講座', 'admin' => $admin, 'heading' => '新しい講座', 'action' => '/admin/courses/new', 'course' => null, 'values' => $values, 'errors' => $errors], 'admin/layout');
    }

    public static function show(string $id): void
    {
        $admin = Auth::requireAdmin();
        $course = Courses::find((int) $id) ?? abort_not_found();
        self::renderShow($admin, $course, null, []);
    }

    /** 講座の画面。$addValues は回の追加で誤りがあったときの入力（書いた内容を消さずに戻す） */
    private static function renderShow(array $admin, array $course, ?array $addValues, array $addErrors): void
    {
        $purchases = array_filter(array_merge(Courses::purchases('pending'), Courses::purchases('paid')), fn ($p) => (int) $p['course_id'] === (int) $course['id']);
        echo View::render('admin/courses/show', [
            'title' => $course['title'],
            'admin' => $admin,
            'course' => $course,
            'lessons' => Courses::lessons((int) $course['id']),
            'purchases' => array_values($purchases),
            'baseUrl' => rtrim((string) Config::get('APP_URL', ''), '/'),
            'addValues' => $addValues,
            'addErrors' => $addErrors,
        ], 'admin/layout');
    }

    public static function edit(string $id): void
    {
        $admin = Auth::requireAdmin();
        $course = Courses::find((int) $id) ?? abort_not_found();
        $values = array_intersect_key($course, array_flip(Courses::FIELDS));
        $errors = [];
        if (is_post()) {
            [$values, $errors] = self::read($_POST);
            if ($errors === []) {
                Courses::update((int) $course['id'], $values);
                Session::flash('notice', "講座「{$values['title']}」を保存しました。");
                redirect('/admin/courses/' . $course['id']);
                return;
            }
        }
        echo View::render('admin/courses/form', ['title' => $course['title'] . ' の編集', 'admin' => $admin, 'heading' => '講座の編集', 'action' => '/admin/courses/' . $course['id'] . '/edit', 'course' => $course, 'values' => $values, 'errors' => $errors], 'admin/layout');
    }

    public static function delete(string $id): void
    {
        Auth::requireAdmin();
        $course = Courses::find((int) $id) ?? abort_not_found();
        if ((int) $course['paid_count'] > 0) {
            Session::flash('error', '購入した人がいる講座は消せません。「下書き」にして非公開にしてください。');
            redirect('/admin/courses/' . $course['id']);
            return;
        }
        Courses::delete((int) $course['id']);
        Session::flash('notice', "講座「{$course['title']}」を消しました。");
        redirect('/admin/courses');
    }

    public static function addLesson(string $courseId): void
    {
        $admin = Auth::requireAdmin();
        $course = Courses::find((int) $courseId) ?? abort_not_found();
        [$values, $errors] = self::readLesson($_POST);
        if ($errors !== []) {
            // 書いた説明を消さないよう、入力を残したまま講座の画面に戻す（動画の URL も書いたまま）
            $values['youtube_raw'] = Form::str($_POST, 'youtube');
            self::renderShow($admin, $course, $values, $errors);
            return;
        }
        Courses::addLesson((int) $course['id'], $values);
        Session::flash('notice', "「{$values['title']}」を追加しました。続けて講座の次の回も追加できます。");
        redirect('/admin/courses/' . $course['id'] . '#add-lesson');
    }

    /** 回の順番を1つ上か下へ */
    public static function moveLesson(string $id): void
    {
        Auth::requireAdmin();
        $lesson = Courses::findLesson((int) $id) ?? abort_not_found();
        Courses::moveLesson((int) $lesson['id'], Form::choice($_POST, 'direction', ['up', 'down'], 'up'));
        redirect('/admin/courses/' . $lesson['course_id'] . '#lessons');
    }

    public static function editLesson(string $id): void
    {
        $admin = Auth::requireAdmin();
        $lesson = Courses::findLesson((int) $id) ?? abort_not_found();
        $course = Courses::find((int) $lesson['course_id']) ?? abort_not_found();
        $values = array_intersect_key($lesson, array_flip(Courses::LESSON_FIELDS));
        $errors = [];
        if (is_post()) {
            [$values, $errors] = self::readLesson($_POST);
            $values['youtube_raw'] = Form::str($_POST, 'youtube');
            if ($errors === []) {
                Courses::updateLesson((int) $lesson['id'], $values);
                Session::flash('notice', "「{$values['title']}」を保存しました。");
                redirect('/admin/courses/' . $course['id']);
                return;
            }
        }
        echo View::render('admin/courses/lesson_form', ['title' => $lesson['title'] . ' の編集', 'admin' => $admin, 'course' => $course, 'lesson' => $lesson, 'values' => $values, 'errors' => $errors], 'admin/layout');
    }

    public static function deleteLesson(string $id): void
    {
        Auth::requireAdmin();
        $lesson = Courses::findLesson((int) $id) ?? abort_not_found();
        Courses::deleteLesson((int) $lesson['id']);
        Session::flash('notice', "「{$lesson['title']}」を消しました。");
        redirect('/admin/courses/' . $lesson['course_id']);
    }

    // ── 購入 ──

    public static function purchases(): void
    {
        $admin = Auth::requireAdmin();
        echo View::render('admin/purchases/index', [
            'title' => '講座の購入',
            'admin' => $admin,
            'pending' => Courses::purchases('pending'),
            'paid' => Courses::purchases('paid'),
        ], 'admin/layout');
    }

    public static function purchasePaid(string $id): void
    {
        $admin = Auth::requireAdmin();
        $purchase = Courses::findPurchase((int) $id) ?? abort_not_found();
        $method = Form::choice($_POST, 'payment_method', array_keys(Registrations::PAYMENT_METHODS), 'bank_transfer');
        Courses::markPaid((int) $purchase['id'], $method, (int) $admin['id']);
        $customer = \App\Customers::find((int) $purchase['customer_id']);
        if ($customer !== null) {
            MailTemplates::sendCustomerMail('purchase_paid', $customer, [
                'course_title' => $purchase['course_title'],
                'course_url' => rtrim((string) Config::get('APP_URL', ''), '/') . '/learn/' . $purchase['course_slug'],
            ]);
        }
        Session::flash('notice', "「{$purchase['customer_name']}」の「{$purchase['course_title']}」を入金確認済みにし、視聴のご案内メールを送りました。");
        redirect(RegistrationsController::back('/admin/purchases'));
    }

    public static function purchaseCancel(string $id): void
    {
        Auth::requireAdmin();
        $purchase = Courses::findPurchase((int) $id) ?? abort_not_found();
        Courses::cancelPurchase((int) $purchase['id']);
        Session::flash('notice', "「{$purchase['customer_name']}」の「{$purchase['course_title']}」の購入を取り消しました。");
        redirect(RegistrationsController::back('/admin/purchases'));
    }

    /** @return array{array, list<string>} */
    private static function read(array $input): array
    {
        $errors = [];
        $values = [
            'title' => Form::str($input, 'title'),
            'description' => Form::str($input, 'description') ?: null,
            'access' => Form::choice($input, 'access', array_keys(Courses::ACCESS), 'crew'),
            'price' => Form::int($input, 'price'),
            'crew_included' => Form::checked($input, 'crew_included') ? 1 : 0,
            'status' => Form::choice($input, 'status', array_keys(Courses::STATUSES), 'draft'),
            'sort_order' => Form::int($input, 'sort_order'),
        ];
        if ($values['title'] === '' || mb_strlen($values['title']) > 200) {
            $errors[] = 'タイトルは1〜200文字で入れてください。';
        }
        if ($values['price'] === false || (is_int($values['price']) && $values['price'] < 0)) {
            $errors[] = '料金は0以上の数字で入れてください。';
            $values['price'] = null;
        }
        if ($values['access'] === 'paid' && $values['price'] === null) {
            $errors[] = '「購入した人」の講座には料金を入れてください。';
        }
        if ($values['access'] !== 'paid') {
            $values['price'] = null;
        }
        $values['sort_order'] = is_int($values['sort_order']) ? $values['sort_order'] : 0;
        return [$values, $errors];
    }

    /** @return array{array, list<string>} */
    private static function readLesson(array $input): array
    {
        $errors = [];
        $youtube = Form::str($input, 'youtube');
        $youtubeId = Courses::youtubeId($youtube);
        if ($youtube !== '' && $youtubeId === null) {
            $errors[] = 'YouTubeのURLか動画IDの形が正しくありません。';
        }
        $sortOrder = Form::int($input, 'sort_order');
        $values = [
            'title' => Form::str($input, 'title'),
            'body' => rtrim(str_replace("\r\n", "\n", Form::raw($input, 'body'))) ?: null,
            'youtube_id' => $youtubeId,
            'is_preview' => Form::checked($input, 'is_preview') ? 1 : 0,
            'status' => Form::choice($input, 'status', array_keys(Courses::STATUSES), 'published'),
            'sort_order' => is_int($sortOrder) ? $sortOrder : null,
        ];
        if ($values['title'] === '' || mb_strlen($values['title']) > 200) {
            $errors[] = '講座の回のタイトルは1〜200文字で入れてください。';
        }
        if ($values['body'] !== null && mb_strlen($values['body']) > 20000) {
            $errors[] = '本文は20000文字までにしてください。';
        }
        return [$values, $errors];
    }
}
