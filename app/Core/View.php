<?php
declare(strict_types=1);

namespace Learmingo\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views/';
        $viewFile = $root . $view . '.php';
        if (!is_file($viewFile)) {
            Response::html('View not found', 500);
            return;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string)ob_get_clean();

        if ($layout === null) {
            Response::html($content);
            return;
        }

        $layoutFile = $root . $layout . '.php';
        require $layoutFile;
    }
}
