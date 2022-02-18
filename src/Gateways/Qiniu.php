<?php
// +----------------------------------------------------------------------
// | date: 2015-09-12
// +----------------------------------------------------------------------
// | Upload.php: 七牛上传实现
// +----------------------------------------------------------------------
// | Author: tinymeng <666@majiameng.com>
// +----------------------------------------------------------------------
namespace tinymeng\uploads\Gateways;

use Exception;
use Qiniu\Auth;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
use Qiniu\Config AS QiniuConfig;
use InvalidArgumentException;
use tinymeng\uploads\Helper\MimeType;
use tinymeng\uploads\Connector\Gateway;
use tinymeng\uploads\Helper\PathLibrary;
use tinymeng\uploads\Helper\FileFunction;

class Qiniu extends Gateway
{
    /**
     * Auth
     *
     * @var Auth
     */
    protected $auth;
    /**
     * token
     *
     * @var string
     */
    protected $token;
    /**
     * bucket
     *
     * @var
     */
    protected $bucket;
    /**
     * 七牛空间管理对象
     *
     * @var
     */
    protected $bucketManager;
    /**
     * 上传对象
     *
     * @var
     */
    protected $uploadManager;
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config;
    /**
     * 构造方法
     *
     * @param array $config 配置信息
     * @author tinymeng <666@majiameng.com>
     */
    public function __construct($config)
    {
        $baseConfig = [
            'domain'        => '',//你的七牛域名
            'access_key'    => '',//AccessKey
            'secret_key'    => '',//SecretKey
            'bucket'        => '',//Bucket名字
            'transport'     => 'http',//如果支持https，请填写https，如果不支持请填写http
        ];
        $this->config   = array_replace_recursive($baseConfig,$config);
        $this->bucket   = $this->config['bucket'];
        $this->auth     = new Auth($this->config['access_key'], $this->config['secret_key']);
        $this->token    = $this->auth->uploadToken($this->bucket);
        //设置路径前缀
        $this->setPathPrefix($this->config['transport'] . '://' . $this->config['domain']);
    }
    /**
     * 格式化路径
     *
     * @param $path
     * @return string
     */
    protected static function normalizerPath($path)
    {
        $path = ltrim(PathLibrary::normalizerPath($path), '/');
        return $path == '/' ? '' : $path;
    }
    /**
     * 获得七牛空间管理对象
     *
     * @return BucketManager
     * @author tinymeng <666@majiameng.com>
     */
    protected function getBucketManager()
    {
        if (!$this->bucketManager) {
            $this->bucketManager = new BucketManager($this->auth);
        }
        return $this->bucketManager;
    }
    /**
     * 获得七牛上传对象
     *
     * @return UploadManager
     * @author tinymeng <666@majiameng.com>
     */
    protected function getClient()
    {
        if (!$this->uploadManager) {
            $this->uploadManager = new UploadManager();
        }
        return $this->uploadManager;
    }
    /**
     * 获得 Qiniu 实例
     *
     * @return UploadManager
     */
    public function getInstance()
    {
        return $this->getClient();
    }
    /**
     * 获得二进制流上传对象
     *
     * @param string $key          上传文件名
     * @param resource $inputStream  上传二进制流
     * @param array $params       自定义变量
     * @return ResumeUploader
     * @author tinymeng <666@majiameng.com>
     */
    protected function getResumeUpload($key, $inputStream, $params = null)
    {
        return new ResumeUploader( $this->token, $key, $inputStream, $this->getResourceSize($inputStream), $params, $config->get('mimetype'), (new QiniuConfig()) );
    }
    /**
     * 获得文件大小
     *
     * @param $inputStream
     * @return int
     */
    protected function getResourceSize($inputStream)
    {
        $size = 0;
        $a = &$inputStream;
        while( !feof($a) ) {
            $str = fgets($a);
            $size += strlen($str);
        }
        fseek($inputStream, 0);
        return $size;
    }
    /**
     * 判断文件是否存在
     *
     * @param string $path
     * @return bool
     * @author tinymeng <666@majiameng.com>
     */
    public function has($path)
    {
        $file_stat = $this->getMetadata($path);
        return !empty($file_stat) ? true : false;
    }
    /**
     * 读取文件
     *
     * @param $file_name
     * @author tinymeng <666@majiameng.com>
     */
    public function read($path)
    {
        return ['contents' => file_get_contents($this->applyPathPrefix($path)) ];
    }
    /**
     * 获得文件流
     *
     * @param string $path
     * @return array
     * @author tinymeng <666@majiameng.com>
     */
    public function readStream($path)
    {
        return ['stream' => fopen($this->applyPathPrefix($path), 'r')];
    }
    /**
     * 写入文件
     *
     * @param $file_name
     * @param $contents
     * @author tinymeng <666@majiameng.com>
     */
    public function write($path, $contents)
    {
        list(, $error) = $this->getUploadManager()->put($this->token, static::normalizerPath($path), $contents);
        if ($error) {
            return false;
        }
        return true;
    }
    /**
     * 写入文件流
     *
     * @param string $path
     * @param resource $resource
     */
    public function writeStream($path, $resource)
    {
        $config->set('mimetype', MimeType::detectByContent(fgets($resource)));
        list(, $error) = $this->getResumeUpload(static::normalizerPath($path), $resource, $config)->upload();
        return $error ? false : true;
    }
    /**
     * 更新文件
     *
     * @param string $path
     * @param string $contents
     */
    public function update($path, $contents)
    {
        return $this->write($path, $contents);
    }
    /**
     * 更新文件流
     *
     * @param string $path
     * @param resource $resource
     */
    public function updateStream($path, $resource)
    {
        return $this->writeStream($path, $resource);
    }
    /**
     * 列出目录文件
     *
     * @param string $directory
     * @param bool|false $recursive
     * @return mixed
     * @author tinymeng <666@majiameng.com>
     */
    public function listContents($directory = '', $recursive = false)
    {
        list($file_list, $marker, $error) = $this->getBucketManager()->listFiles($this->bucket, static::normalizerPath($directory));
        if (!$error) {
            foreach ($file_list as &$file) {
                $file['path']   = $file['key'];
                $file['marker'] = $marker;//用于下次请求的标识符
            }
            return $file_list;
        }
        return false;
    }
    /**
     * 获取资源的元信息，但不返回文件内容
     *
     * @param $path
     * @return mixed
     * @author tinymeng <666@majiameng.com>
     */
    public function getMetadata($path)
    {
        list($info, $error) = $this->getBucketManager()->stat($this->bucket, static::normalizerPath($path));
        if ($error) {
            return false;
        }
        return $info;
    }
    /**
     * 获得文件大小
     *
     * @param string $path
     * @return array
     * @author tinymeng <666@majiameng.com>
     */
    public function getSize($path)
    {
        list($fsize, , , ) = array_values($this->getMetadata($path));
        return $fsize > 0 ? [ 'size' => $fsize ] : ['size' => 0];
    }
    /**
     * 获得文件Mime类型
     *
     * @param string $path
     * @return mixed string|null
     * @author tinymeng <666@majiameng.com>
     */
    public function getMimetype($path)
    {
        list(, , $mimeType,) = array_values($this->getMetadata($path));
        return !empty($mimeType) ? ['mimetype' => $mimeType ] : ['mimetype' => ''];
    }
    /**
     * 获得文件最后修改时间
     *
     * @param string $path
     * @return mixed 时间戳
     * @author tinymeng <666@majiameng.com>
     */
    public function getTimestamp($path)
    {
        list(, , , $timestamp) = array_values($this->getMetadata($path));
        return !empty($timestamp) ? ['timestamp' => substr($timestamp, 0, -7) ] : ['timestamp' => 0];
    }
    /**
     * 获得文件模式 (未实现)
     *
     * @param string $path
     * @author tinymeng <666@majiameng.com>
     */
    public function getVisibility($path)
    {
        return self::VISIBILITY_PUBLIC;
    }
    /**
     * 重命名文件
     *
     * @param $oldname
     * @param $newname
     * @return boolean
     * @author tinymeng <666@majiameng.com>
     */
    public function rename($path, $newpath)
    {
        return $this->getBucketManager()->rename($this->bucket, static::normalizerPath($path), static::normalizerPath($newpath)) == null
            ? true
            : false;
    }
    /**
     * 复制文件
     *
     * @param $path
     * @param $newpath
     * @return boolean
     * @author tinymeng <666@majiameng.com>
     */
    public function copy($path, $newpath)
    {
        return $this->getBucketManager()->copy($this->bucket, static::normalizerPath($path), $this->bucket, static::normalizerPath($newpath)) == null
            ? true
            : false;
    }
    /**
     * 删除文件或者文件夹
     *
     * @param $path
     * @author tinymeng <666@majiameng.com>
     */
    public function delete($path)
    {
        return $this->getBucketManager()->delete($this->bucket, static::normalizerPath($path)) == null ? true : false;
    }
    /**
     * 删除文件夹
     *
     * @param string $path
     * @return mixed
     * @author tinymeng <666@majiameng.com>
     */
    public function deleteDir($path)
    {
        list($file_list, , $error) = $this->getBucketManager()->listFiles($this->bucket, static::normalizerPath($path));
        if (!$error) {
            foreach ( $file_list as $file) {
                $this->delete($file['key']);
            }
        }
        return true;
    }

    /**
     * 创建文件夹(因为七牛没有文件夹的概念，所以此方法没有实现)
     *
     * @param string $dirname
     * @author tinymeng <666@majiameng.com>
     */
    public function createDir($dirname)
    {
        return true;
    }
    /**
     * 设置文件模式 (未实现)
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     * @author tinymeng <666@majiameng.com>
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }
    /**
     * 获取当前文件的URL访问路径
     *
     * @param $file
     * @param int $expire_at
     * @return mixed
     */
    public function getUrl($file, $expire_at = 3600)
    {
        return $this->applyPathPrefix($file);
    }
}