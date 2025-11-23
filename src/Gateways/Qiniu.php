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
use tinymeng\uploads\exception\TinymengException;

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
     * @param string $path
     * @return array
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function read($path)
    {
        try {
            $url = $this->applyPathPrefix(static::normalizerPath($path));
            $contents = file_get_contents($url);
            if ($contents === false) {
                throw new TinymengException("文件读取失败: {$path}");
            }
            return ['contents' => $contents];
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 获得文件流
     *
     * @param string $path
     * @return array
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function readStream($path)
    {
        try {
            $url = $this->applyPathPrefix(static::normalizerPath($path));
            $handle = fopen($url, 'r');
            if ($handle === false) {
                throw new TinymengException("文件流读取失败: {$path}");
            }
            return ['stream' => $handle];
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 写入文件
     *
     * @param string $path
     * @param string $contents
     * @param array $option
     * @return array|bool|false
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function write($path, $contents, $option = [])
    {
        try {
            list($result, $error) = $this->getClient()->put($this->token, static::normalizerPath($path), $contents, $option);
            if ($error) {
                throw new TinymengException("文件写入失败: " . $error->message());
            }
            return $result !== null ? $result : true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 写入文件流
     *
     * @param string $path
     * @param resource $resource
     * @param array $option
     * @return array|bool|false
     * @throws TinymengException
     */
    public function writeStream($path, $resource, $option = [])
    {
        try {
            //获得一个临时文件
            $tmpfname = FileFunction::getTmpFile();
            
            // 将资源流写入临时文件
            $contents = stream_get_contents($resource);
            file_put_contents($tmpfname, $contents);
            
            // 读取文件内容并上传
            $fileContent = file_get_contents($tmpfname);
            list($result, $error) = $this->getClient()->put($this->token, static::normalizerPath($path), $fileContent, $option);
            
            //删除临时文件
            FileFunction::deleteTmpFile($tmpfname);
            
            if ($error) {
                throw new TinymengException("文件流写入失败: " . $error->message());
            }
            return $result !== null ? $result : true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * Name: 上传文件
     * Author: Tinymeng <666@majiameng.com>
     * @param $path
     * @param $tmpfname
     * @param array $option
     * @return array 返回文件信息，包含 url, etag, size 等
     * @throws TinymengException
     */
    public function uploadFile($path, $tmpfname, $option = [])
    {
        try {
            $path = static::normalizerPath($path);
            if (!file_exists($tmpfname)) {
                throw new TinymengException("文件不存在: {$tmpfname}");
            }
            
            // 读取文件内容
            $fileContent = file_get_contents($tmpfname);
            
            // 上传文件
            list($result, $error) = $this->getClient()->put($this->token, $path, $fileContent, $option);
            
            if ($error) {
                throw new TinymengException("文件上传失败: " . $error->message());
            }
            
            // 构建返回信息
            $fileInfo = [
                'success' => true,
                'path' => $path,
                'key' => $path,
                'etag' => isset($result['hash']) ? $result['hash'] : '',
                'size' => filesize($tmpfname),
            ];
            
            // 获取文件 URL
            try {
                $fileInfo['url'] = $this->getUrl($path, 0); // 0 表示永久 URL
            } catch (Exception $e) {
                // 如果获取 URL 失败，使用路径前缀构建 URL
                $fileInfo['url'] = $this->applyPathPrefix($path);
            }
            
            // 添加其他可能的响应信息
            if (isset($result['key'])) {
                $fileInfo['key'] = $result['key'];
            }
            
            return $fileInfo;
        } catch (Exception $e) {
            throw new TinymengException($e->getMessage());
        }
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
     * @param array $option
     * @return array|bool|false
     * @throws TinymengException
     */
    public function updateStream($path, $resource, $option = [])
    {
        return $this->writeStream($path, $resource, $option);
    }
    /**
     * 列出目录文件
     *
     * @param string $directory
     * @param bool|false $recursive
     * @return array
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function listContents($directory = '', $recursive = false)
    {
        try {
            list($file_list, $marker, $error) = $this->getBucketManager()->listFiles($this->bucket, static::normalizerPath($directory));
            if ($error) {
                throw new TinymengException("列出目录文件失败: " . $error->message());
            }
            $data = [];
            if (is_array($file_list)) {
                foreach ($file_list as &$file) {
                    $data[] = [
                        'path'   => $file['key'],
                        'marker' => $marker, //用于下次请求的标识符
                        'file_type' => isset($file['type']) ? $file['type'] : 'file',
                        'file_size' => isset($file['fsize']) ? $file['fsize'] : 0,
                        'last_modified' => isset($file['putTime']) ? $file['putTime'] : '',
                    ];
                }
            }
            return $data;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 获取资源的元信息，但不返回文件内容
     *
     * @param string $path
     * @return array|bool
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function getMetadata($path)
    {
        try {
            list($info, $error) = $this->getBucketManager()->stat($this->bucket, static::normalizerPath($path));
            if ($error) {
                return false;
            }
            return $info;
        } catch (Exception $e) {
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 获得文件大小
     *
     * @param string $path
     * @return array
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function getSize($path)
    {
        try {
            $file_info = $this->getMetadata($path);
            if ($file_info === false) {
                return ['size' => 0];
            }
            $fsize = isset($file_info['fsize']) ? $file_info['fsize'] : 0;
            return $fsize > 0 ? [ 'size' => $fsize ] : ['size' => 0];
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 获得文件Mime类型
     *
     * @param string $path
     * @return array|false
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function getMimetype($path)
    {
        try {
            $file_info = $this->getMetadata($path);
            if ($file_info === false) {
                return false;
            }
            $mimeType = isset($file_info['mimeType']) ? $file_info['mimeType'] : '';
            return !empty($mimeType) ? ['mimetype' => $mimeType ] : false;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 获得文件最后修改时间
     *
     * @param string $path
     * @return array 时间戳
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function getTimestamp($path)
    {
        try {
            $file_info = $this->getMetadata($path);
            if ($file_info === false) {
                return ['timestamp' => 0];
            }
            $timestamp = isset($file_info['putTime']) ? $file_info['putTime'] : 0;
            // 七牛的 putTime 是微秒时间戳，需要转换为秒
            if ($timestamp > 0) {
                $timestamp = intval($timestamp / 10000000);
            }
            return $timestamp > 0 ? ['timestamp' => $timestamp] : ['timestamp' => 0];
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
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
     * @param string $path
     * @param string $newpath
     * @return bool
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function rename($path, $newpath)
    {
        try {
            $error = $this->getBucketManager()->rename($this->bucket, static::normalizerPath($path), static::normalizerPath($newpath));
            if ($error !== null) {
                throw new TinymengException("文件重命名失败: " . $error->message());
            }
            return true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 复制文件
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function copy($path, $newpath)
    {
        try {
            $error = $this->getBucketManager()->copy($this->bucket, static::normalizerPath($path), $this->bucket, static::normalizerPath($newpath));
            if ($error !== null) {
                throw new TinymengException("文件复制失败: " . $error->message());
            }
            return true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 删除文件或者文件夹
     *
     * @param string $path
     * @return bool
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function delete($path)
    {
        try {
            $error = $this->getBucketManager()->delete($this->bucket, static::normalizerPath($path));
            if ($error !== null) {
                throw new TinymengException("文件删除失败: " . $error->message());
            }
            return true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
    }
    /**
     * 删除文件夹
     *
     * @param string $path
     * @return bool
     * @throws TinymengException
     * @author tinymeng <666@majiameng.com>
     */
    public function deleteDir($path)
    {
        try {
            list($file_list, , $error) = $this->getBucketManager()->listFiles($this->bucket, static::normalizerPath($path));
            if ($error) {
                throw new TinymengException("列出目录文件失败: " . $error->message());
            }
            if (is_array($file_list)) {
                foreach ($file_list as $file) {
                    $this->delete($file['key']);
                }
            }
            return true;
        } catch (Exception $e) {
            if ($e instanceof TinymengException) {
                throw $e;
            }
            throw new TinymengException($e->getMessage());
        }
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
     * @param string $file 文件名
     * @param int $expire_at 有效期，单位：秒（0 表示永久有效，使用路径前缀）
     * @return string
     */
    public function getUrl($file, $expire_at = 3600)
    {
        $file = static::normalizerPath($file);
        
        // 构建完整的基础 URL
        $baseUrl = rtrim($this->config['transport'] . '://' . $this->config['domain'], '/');
        $filePath = '/' . ltrim($file, '/');
        $fullUrl = $baseUrl . $filePath;
        
        // 如果不需要签名 URL，直接返回完整 URL
        if ($expire_at == 0) {
            return $fullUrl;
        }
        
        // 生成签名 URL
        try {
            $signedUrl = $this->auth->privateDownloadUrl($fullUrl, $expire_at);
            return $signedUrl;
        } catch (Exception $e) {
            // 如果生成签名 URL 失败，返回普通 URL
            return $fullUrl;
        }
    }
}