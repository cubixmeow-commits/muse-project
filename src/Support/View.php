<?php

declare(strict_types=1);

namespace Yatsn\Support;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $path = Config::root() . '/templates/' . ltrim($template, '/') . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $html = (string) ob_get_clean();
        $basePath = Config::basePath();
        if ($basePath !== '') {
            // Templates use root-absolute URLs; prefix them with the mount
            // path so the app works from a subfolder. The lookahead keeps
            // already-prefixed URLs (nested renders) untouched.
            $quoted = preg_quote(ltrim($basePath, '/'), '#');
            $html = (string) preg_replace(
                '#(href|src|action)="/(?!/|' . $quoted . '/)#',
                '$1="' . $basePath . '/',
                $html
            );
        }
        return $html;
    }

    public static function page(string $template, array $data = [], string $layout = 'layouts/main'): string
    {
        AssetRelease::sendHtmlNoStoreHeaders();
        $content = self::render($template, $data);
        return self::render($layout, array_merge($data, ['content' => $content]));
    }
}
