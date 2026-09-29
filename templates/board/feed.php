<?php
/**
 * 回の一覧の RSS 2.0。公式サイト（WordPress）の「RSS」ブロックなどで新着として出せる。
 *
 * @var array $events
 * @var string $name
 * @var string $siteUrl
 * @var string $intro
 */
$x = fn (?string $value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
    <title><?= $x($name . ' イベント') ?></title>
    <link><?= $x($siteUrl) ?></link>
    <description><?= $x($intro) ?></description>
    <language>ja</language>
<?php foreach ($events as $event): ?>
<?php $link = app_url('/e/' . $event['slug'] . '?from=site'); ?>
    <item>
        <title><?= $x(fmt_dt($event['starts_at'], false) . '　' . $event['title']) ?></title>
        <link><?= $x($link) ?></link>
        <guid isPermaLink="false"><?= $x('event-' . $event['slug']) ?></guid>
        <description><?= $x(fmt_dt($event['starts_at']) . '・' . (App\Calendar::location($event) ?: '場所は追ってご案内') . '・' . yen($event['fee']) . '　' . App\Markup::plain($event['summary'] ?: $event['description'], 140)) ?></description>
        <pubDate><?= $x(date(DATE_RSS, strtotime((string) $event['created_at']))) ?></pubDate>
    </item>
<?php endforeach; ?>
</channel>
</rss>
