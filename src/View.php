<?php

declare(strict_types=1);

namespace App;

/**
 * templates/ のPHPファイルを表示する。
 * View::render('home', ['title' => '...']) → templates/home.php を templates/layout.php で包む。
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, ['content' => $content] + $data);
    }

    private static function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_ROOT . '/templates/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
