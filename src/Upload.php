<?php

/**
 * 第三方上传实例抽象类
 * @author: JiaMeng <666@majiameng.com>
 */

namespace tinymeng\uploads;

// 目录入口
if (!defined('UPLOADS_ROOT_PATH')) {
    define('UPLOADS_ROOT_PATH', dirname(__DIR__));
}

use tinymeng\uploads\Connector\GatewayInterface;
use tinymeng\uploads\Helper\Str;
/**
 * @method static \tinymeng\uploads\Gateways\Oss oss(array|null $config) 阿里云Oss
 * @method static \tinymeng\uploads\Gateways\Qiniu qiniu(array|null $config) 七牛云
 * @method static \tinymeng\uploads\Gateways\Cos cos(array|null $config) 腾讯云Cos
 */
abstract class Upload
{

    /**
     * Description:  init
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $gateway
     * @param null $config
     * @return mixed
     * @throws \Exception
     */
    protected static function init($gateway, $config = null)
    {
        $gateway = Str::uFirst($gateway);
        $class = __NAMESPACE__ . '\\Gateways\\' . $gateway;
        if (class_exists($class)) {
            // 加载配置文件
            $configFile = UPLOADS_ROOT_PATH . "/config/TUploads.php";
            $baseConfig = [];
            if (file_exists($configFile)) {
                $allConfig = require $configFile;
                // 获取对应驱动的默认配置
                $driver = strtolower($gateway);
                if (isset($allConfig[$driver])) {
                    $baseConfig = $allConfig[$driver];
                }
            }
            
            // 合并配置：用户配置优先
            if ($config === null) {
                $config = [];
            }
            $finalConfig = array_replace_recursive($baseConfig, $config);
            
            $app = new $class($finalConfig);
            if ($app instanceof GatewayInterface) {
                return $app;
            }
            throw new \Exception("第三方上传类基类 [$class] 必须继承父类 [tinymeng\uploads\Connector\GatewayInterface]");
        }
        throw new \Exception("第三方上传基类 [$class] 不存在");
    }

    /**
     * Description:  __callStatic
     * @author: JiaMeng <666@majiameng.com>
     * Updater:
     * @param $gateway
     * @param $config
     * @return mixed
     */
    public static function __callStatic($gateway, $config)
    {
        $config = isset($config[0]) ? $config[0] : null;
        return self::init($gateway, $config);
    }

}
