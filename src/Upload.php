<?php

/**
 * 第三方上传实例抽象类
 * @author: JiaMeng <666@majiameng.com>
 */

namespace tinymeng\uploads;

use tinymeng\uploads\Connector\GatewayInterface;
use tinymeng\payment\Gateways\Alipay;
use tinymeng\payment\Gateways\Wechat;
use tinymeng\uploads\Helper\Str;
/**
 * @method static \tinymeng\uploads\Gateways\Oss oss(array $config) 阿里云Oss
 * @method static \tinymeng\uploads\Gateways\Qiniu qiniu(array $config) 七牛云
 * @method static \tinymeng\uploads\Gateways\Cos cos(array $config) 腾讯云Cos
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
            $app = new $class($config);
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
        return self::init($gateway, ...$config);
    }

}
