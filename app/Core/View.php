<?php
namespace App\Core;

class View
{
    public static $baseDir = '';

    /**
     * @param string $tpl 模板路径，如 'shop/index' 对应 app/Views/shop/index.php
     * @param array $data 模板变量
     * @param string|null $layout 布局名，如 'layout' 对应 app/Views/layout.php；null 则不使用布局
     */
    public static function render(string $tpl, array $data = [], ?string $layout = 'layout'): string
    {
        $file = self::$baseDir . '/' . $tpl . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("视图不存在: {$tpl}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        if ($layout === null) {
            return $content;
        }
        $layoutFile = self::$baseDir . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            return $content;
        }
        ob_start();
        include $layoutFile;
        return ob_get_clean();
    }
}
