<?php

declare(strict_types=1);

namespace App\Web;

use App\Courses;
use App\Crew;
use App\CustomerAuth;
use App\MailTemplates;
use App\Session;
use App\Settings;
use App\View;

/**
 * 講座（動画・記事）の公開ページ。見られる範囲は Courses::canView で決める。
 */
final class LearnController
{
    public static function index(): void
    {
        $customer = CustomerAuth::current();
        $courses = [];
        foreach (Courses::published() as $course) {
            $purchase = $customer !== null ? Courses::purchase((int) $course['id'], (int) $customer['id']) : null;
            $course['lock'] = Courses::lockReason($course, $customer, $purchase);
            $courses[] = $course;
        }
        echo View::render('learn/index', [
            'title' => '講座・動画',
            'intro' => Settings::get('learn_intro'),
            'courses' => $courses,
            'customer' => $customer,
        ]);
    }

    public static function course(string $slug): void
    {
        $course = self::findPublished($slug);
        $customer = CustomerAuth::current();
        $purchase = $customer !== null ? Courses::purchase((int) $course['id'], (int) $customer['id']) : null;
        $lessons = [];
        foreach (Courses::lessons((int) $course['id'], true) as $i => $lesson) {
            $lesson['no'] = $i + 1;
            $lesson['viewable'] = Courses::canView($course, $lesson, $customer, $purchase);
            $lessons[] = $lesson;
        }
        echo View::render('learn/course', [
            'title' => $course['title'],
            'course' => $course,
            'lessons' => $lessons,
            'customer' => $customer,
            'purchase' => $purchase,
            'lock' => Courses::lockReason($course, $customer, $purchase),
            'isCrew' => Crew::isActive($customer),
        ]);
    }

    public static function lesson(string $slug, string $id): void
    {
        $course = self::findPublished($slug);
        $lesson = Courses::findLesson((int) $id);
        if ($lesson === null || (int) $lesson['course_id'] !== (int) $course['id'] || $lesson['status'] !== 'published') {
            abort_not_found();
        }
        $customer = CustomerAuth::current();
        $purchase = $customer !== null ? Courses::purchase((int) $course['id'], (int) $customer['id']) : null;
        $published = Courses::lessons((int) $course['id'], true);
        $index = array_search((int) $lesson['id'], array_map(fn ($l) => (int) $l['id'], $published), true);
        echo View::render('learn/lesson', [
            'title' => $lesson['title'],
            'course' => $course,
            'lesson' => $lesson,
            'no' => $index === false ? null : $index + 1,
            'prev' => $index !== false && $index > 0 ? $published[$index - 1] : null,
            'next' => $index !== false && $index < count($published) - 1 ? $published[$index + 1] : null,
            'viewable' => Courses::canView($course, $lesson, $customer, $purchase),
            'lock' => Courses::lockReason($course, $customer, $purchase),
            'customer' => $customer,
        ]);
    }

    /** 講座を購入する（入金待ちにして、振込先のメールを送る） */
    public static function purchase(string $slug): void
    {
        $course = self::findPublished($slug);
        $customer = CustomerAuth::requireLogin();
        if ($course['access'] !== 'paid' || $course['price'] === null) {
            redirect('/learn/' . $course['slug']);
            return;
        }
        if (Courses::lockReason($course, $customer, Courses::purchase((int) $course['id'], (int) $customer['id'])) === 'ok') {
            Session::flash('notice', 'この講座はすでにご覧いただけます。');
            redirect('/learn/' . $course['slug']);
            return;
        }
        $result = Courses::requestPurchase((int) $course['id'], (int) $customer['id'], (int) $course['price']);
        $base = rtrim((string) \App\Config::get('APP_URL', ''), '/');
        $bank = trim(Settings::get('bank_account'));
        MailTemplates::sendCustomerMail('purchase', $customer, [
            'course_title' => $course['title'],
            'price' => yen($course['price']),
            'bank_account' => $bank !== '' ? "■ お振込先\n{$bank}\n" : '',
            'course_url' => $base . '/learn/' . $course['slug'],
        ]);
        if ($result['created']) {
            MailTemplates::notifyStaffText(
                "【講座の購入】{$course['title']}：{$customer['name']}",
                ['講座の購入の申込がありました。入金を確認したら管理画面で「入金確認済み」にしてください。', '', "■ 講座：{$course['title']}（" . yen($course['price']) . '）', "■ 名前：{$customer['name']}", '■ メール：' . ($customer['email'] ?? '—'), '', $base . '/admin/purchases'],
                (int) $customer['id']
            );
        }
        Session::flash('notice', '講座のお申込みを受け付けました。お振込先をメールでお送りしました。入金を確認しましたらご覧いただけます。');
        redirect('/learn/' . $course['slug']);
    }

    private static function findPublished(string $slug): array
    {
        $course = Courses::findBySlug($slug);
        if ($course === null || $course['status'] !== 'published') {
            abort_not_found();
        }
        return $course;
    }
}
