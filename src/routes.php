<?php

declare(strict_types=1);

use App\Admin\AccountingController;
use App\Admin\BansController;
use App\Admin\ChannelsController;
use App\Admin\CheckinController;
use App\Admin\CoursesController;
use App\Admin\CrewController as AdminCrewController;
use App\Admin\CustomersController;
use App\Admin\EventsController;
use App\Admin\FollowsController;
use App\Admin\MailController;
use App\Admin\MembersController;
use App\Admin\RegistrationsController;
use App\Admin\SettingsController;
use App\Admin\StatsController;
use App\Auth;
use App\Config;
use App\Database;
use App\Events;
use App\Router;
use App\Session;
use App\View;
use App\Web\BoardController;
use App\Web\CrewController;
use App\Web\FollowController;
use App\Web\LearnController;
use App\Web\LoginController;
use App\Web\MyPageController;

$router = new Router();

// ── 参加者向け：掲示板と個人専用ページ ─────────────────────────
$router->get('/', [BoardController::class, 'index']);
$router->get('/photos/{name}', fn (string $name) => App\Photos::serve($name));
$router->get('/e/{slug}', [BoardController::class, 'show']);
$router->get('/e/{slug}/calendar.ics', [BoardController::class, 'calendar']);
$router->get('/embed/events', [BoardController::class, 'embed']);
$router->get('/feed.xml', [BoardController::class, 'feed']);
$router->form('/e/{slug}/apply', [BoardController::class, 'apply']);
$router->get('/e/{slug}/done', [BoardController::class, 'done']);
$router->get('/my', [MyPageController::class, 'mine']);
$router->get('/my/{token}', [MyPageController::class, 'show']);
$router->post('/my/{token}/cancel/{id}', [MyPageController::class, 'cancel']);
$router->post('/my/{token}/mail', [MyPageController::class, 'mail']);
$router->form('/my/{token}/survey/{id}', [MyPageController::class, 'survey']);
$router->post('/follow', [FollowController::class, 'subscribe']);
$router->get('/follow/sent', [FollowController::class, 'sent']);
$router->form('/follow/{token}', [FollowController::class, 'manage']);

// ── 参加者向け：ログイン（メールのリンク）、クルー募集、講座 ──────
$router->get('/login', [LoginController::class, 'form']);
$router->post('/login', [LoginController::class, 'send']);
$router->get('/login/{token}', [LoginController::class, 'verify']);
$router->post('/logout', [LoginController::class, 'logout']);
$router->form('/crew', [CrewController::class, 'page']);
$router->get('/crew/done', [CrewController::class, 'done']);
$router->get('/learn', [LearnController::class, 'index']);
$router->get('/learn/{slug}', [LearnController::class, 'course']);
$router->post('/learn/{slug}/purchase', [LearnController::class, 'purchase']);
$router->get('/learn/{slug}/{id}', [LearnController::class, 'lesson']);

// 動作確認用：アプリとDBがつながっているか
$router->get('/health', function (): void {
    try {
        Database::pdo()->query('SELECT 1');
        json_response(['app' => 'ok', 'db' => 'ok']);
    } catch (PDOException $e) {
        error_log((string) $e);
        $detail = Config::bool('APP_DEBUG') ? $e->getMessage() : 'error';
        json_response(['app' => 'ok', 'db' => $detail], 503);
    }
});

// ── 管理画面：ログイン ─────────────────────────────────

$router->get('/admin/login', function (): void {
    if (Auth::user() !== null) {
        redirect('/admin');
        return;
    }
    header('Cache-Control: no-store');
    echo View::render('admin/login', [
        'title' => 'ログイン',
        'loginId' => '',
        'next' => safe_admin_path($_GET['next'] ?? null),
        'error' => Session::flash('error'),
        'notice' => Session::flash('notice'),
    ], 'admin/layout');
});

$router->post('/admin/login', function (): void {
    $loginId = is_string($_POST['login_id'] ?? null) ? trim($_POST['login_id']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $next = safe_admin_path($_POST['next'] ?? null);

    $result = ($loginId === '' || $password === '') ? Auth::INVALID : Auth::attempt($loginId, $password, client_ip());
    if ($result === Auth::OK) {
        redirect($next);
        return;
    }

    http_response_code($result === Auth::LOCKED ? 429 : 200);
    header('Cache-Control: no-store');
    echo View::render('admin/login', [
        'title' => 'ログイン',
        'loginId' => $loginId,
        'next' => $next,
        'error' => $result === Auth::LOCKED
            ? 'ログインに続けて失敗したため、しばらく止めています。15分ほどたってからお試しください。'
            : 'ログインIDまたはパスワードが違います。',
        'notice' => null,
    ], 'admin/layout');
});

$router->post('/admin/logout', function (): void {
    Auth::logout();
    Session::flash('notice', 'ログアウトしました。');
    redirect('/admin/login');
});

// ── 管理画面：トップ ─────────────────────────────────

$router->get('/admin', function (): void {
    $admin = Auth::requireAdmin();
    echo View::render('admin/home', [
        'title' => '管理画面',
        'admin' => $admin,
        'upcoming' => Events::upcoming(5),
        'pendingReviews' => count(App\Bans::pendingReviews()),
        'pendingCrew' => count(App\Crew::applications('applied')),
        'pendingPurchases' => count(App\Courses::purchases('pending')),
    ], 'admin/layout');
});

// ── イベント ─────────────────────────────────────────────
$router->get('/admin/events', [EventsController::class, 'index']);
$router->form('/admin/events/new', [EventsController::class, 'create']);
$router->get('/admin/events/{id}', [EventsController::class, 'show']);
$router->form('/admin/events/{id}/edit', [EventsController::class, 'edit']);
$router->get('/admin/events/{id}/preview', [EventsController::class, 'preview']);
$router->post('/admin/events/{id}/copy', [EventsController::class, 'copy']);
$router->post('/admin/events/{id}/status', [EventsController::class, 'status']);
$router->post('/admin/events/{eventId}/mails', [MailController::class, 'send']);
$router->post('/admin/events/{eventId}/announce', [FollowsController::class, 'announce']);
$router->post('/admin/events/{id}/no-shows', [EventsController::class, 'markNoShows']);
$router->get('/admin/events/{id}/registrations.csv', [EventsController::class, 'registrationsCsv']);
$router->get('/admin/follows', [FollowsController::class, 'index']);

// ── 申込（手入力・変更） ─────────────────────────────
$router->form('/admin/events/{eventId}/registrations/new', [RegistrationsController::class, 'create']);
$router->form('/admin/registrations/{id}/edit', [RegistrationsController::class, 'edit']);
$router->post('/admin/registrations/{id}/cancel', [RegistrationsController::class, 'cancel']);
$router->post('/admin/registrations/{id}/restore', [RegistrationsController::class, 'restore']);
$router->post('/admin/registrations/{id}/prepaid', [RegistrationsController::class, 'prepaid']);
$router->post('/admin/registrations/{id}/no-show', [RegistrationsController::class, 'noShow']);
$router->post('/admin/registrations/{id}/ban-check', [BansController::class, 'review']);

// ── 当日受付 ─────────────────────────────────────────
$router->get('/admin/events/{eventId}/checkin', [CheckinController::class, 'index']);
$router->post('/admin/registrations/{id}/checkin', [CheckinController::class, 'arrive']);
$router->post('/admin/registrations/{id}/checkin/undo', [CheckinController::class, 'undo']);

// ── 会計 ─────────────────────────────────────────────
$router->get('/admin/accounting', [AccountingController::class, 'index']);
$router->get('/admin/accounting.csv', [AccountingController::class, 'csv']);
$router->get('/admin/events/{eventId}/accounting', [AccountingController::class, 'show']);
$router->post('/admin/events/{eventId}/expenses', [AccountingController::class, 'addExpense']);
$router->post('/admin/accounting/expenses', [AccountingController::class, 'addCommonExpense']);
$router->form('/admin/expenses/{id}/edit', [AccountingController::class, 'editExpense']);
$router->post('/admin/expenses/{id}/delete', [AccountingController::class, 'deleteExpense']);
$router->post('/admin/events/{eventId}/organizer', [AccountingController::class, 'organizer']);

// ── 顧客台帳 ─────────────────────────────────────────
$router->get('/admin/customers', [CustomersController::class, 'index']);
$router->get('/admin/customers.csv', [CustomersController::class, 'csv']);
$router->form('/admin/customers/new', [CustomersController::class, 'create']);
$router->get('/admin/customers/duplicates', [CustomersController::class, 'duplicates']);
$router->post('/admin/customers/merge', [CustomersController::class, 'merge']);
$router->get('/admin/customers/{id}', [CustomersController::class, 'show']);
$router->form('/admin/customers/{id}/edit', [CustomersController::class, 'edit']);
$router->post('/admin/customers/{id}/ban', [CustomersController::class, 'ban']);

// ── クルー・講座 ─────────────────────────────────────────
$router->get('/admin/crew', [AdminCrewController::class, 'index']);
$router->post('/admin/crew/applications/{id}', [AdminCrewController::class, 'decide']);
$router->post('/admin/crew/{customerId}/status', [AdminCrewController::class, 'status']);
$router->get('/admin/courses', [CoursesController::class, 'index']);
$router->form('/admin/courses/new', [CoursesController::class, 'create']);
$router->get('/admin/courses/{id}', [CoursesController::class, 'show']);
$router->form('/admin/courses/{id}/edit', [CoursesController::class, 'edit']);
$router->post('/admin/courses/{id}/delete', [CoursesController::class, 'delete']);
$router->post('/admin/courses/{id}/lessons', [CoursesController::class, 'addLesson']);
$router->form('/admin/lessons/{id}/edit', [CoursesController::class, 'editLesson']);
$router->post('/admin/lessons/{id}/delete', [CoursesController::class, 'deleteLesson']);
$router->post('/admin/lessons/{id}/move', [CoursesController::class, 'moveLesson']);
$router->get('/admin/purchases', [CoursesController::class, 'purchases']);
$router->post('/admin/purchases/{id}/paid', [CoursesController::class, 'purchasePaid']);
$router->post('/admin/purchases/{id}/cancel', [CoursesController::class, 'purchaseCancel']);

// ── 出禁リスト ─────────────────────────────────────────
$router->get('/admin/bans', [BansController::class, 'index']);
$router->form('/admin/bans/new', [BansController::class, 'create']);

// ── 集計・設定・運営メンバー ─────────────────────────────
$router->get('/admin/stats', [StatsController::class, 'index']);
$router->form('/admin/settings', [SettingsController::class, 'index']);
$router->get('/admin/official-site', [SettingsController::class, 'officialSite']);
$router->form('/admin/channels', [ChannelsController::class, 'index']);
$router->get('/admin/members', [MembersController::class, 'index']);
$router->form('/admin/members/new', [MembersController::class, 'create']);
$router->form('/admin/members/{id}', [MembersController::class, 'edit']);
$router->post('/admin/members/{id}/password', [MembersController::class, 'password']);

return $router;
