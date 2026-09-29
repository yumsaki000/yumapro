<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Stats;
use App\View;

/**
 * 集客の集計：窓口別・知った経路別・新規／リピート・男女（要件の効果測定）。
 */
final class StatsController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        echo View::render('admin/stats/index', [
            'title' => '集計',
            'admin' => $admin,
            'events' => Stats::perEvent(30),
            'monthly' => Stats::monthly(12),
            'referrers' => Stats::referrers(10),
        ], 'admin/layout');
    }
}
