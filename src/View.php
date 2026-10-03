<?php

declare(strict_types=1);

namespace Ozoto;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $__data */
    private static function capture(string $__template, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require BASE_PATH . '/templates/' . $__template . '.php';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
