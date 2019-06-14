<?php
// +----------------------------------------------------------------------
// | date: 2016-11-13
// +----------------------------------------------------------------------
// | UtilityLibrary.php: 工具库
// +----------------------------------------------------------------------
// | Author: yangyifan <666@majiameng.com>
// +----------------------------------------------------------------------
namespace tinymeng\uploads\Helper;
class PathLibrary
{
    /*
     * 内部方法, 规整文件路径
     * @param  string  $path      文件路径
     * @param  string  $isfolder  是否为文件夹
     */
    public static function normalizerPath($path, $isfolder = False)
    {
        if (preg_match('/^\//', $path) == 0) {
            $path = '/' . $path;
        }
        if ($isfolder == True) {
            if (preg_match('/\/$/', $path) == 0) {
                $path = $path . '/';
            }
        }
        // Remove unnecessary slashes.
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }
}