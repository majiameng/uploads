<?php

// +----------------------------------------------------------------------
// | date: 2015-09-09
// +----------------------------------------------------------------------
// | OssAdapter.php: oss上传
// +----------------------------------------------------------------------
// | Author: Tinymeng <666@majiameng.com>
// +----------------------------------------------------------------------


namespace tinymeng\uploads\Gateways;

use Exception;
use Qcloud\Cos\Client;
use tinymeng\uploads\exception\TinymengException;
use tinymeng\uploads\Connector\Gateway;
use tinymeng\uploads\Helper\PathLibrary;
use tinymeng\uploads\Helper\FileFunction;

class Cos extends  Gateway
{

    const FILE_TYPE_FILE    = 'file';//类型是文件
    const FILE_TYPE_DIR     = 'dir';//类型是文件夹


    /**
     * oss client 上传对象
     *
     * @var OssClient
     */
    protected $upload;

    /**
     * bucket
     *
     * @var string
     */
    protected $bucket;

    /**
     * 构造方法
     *
     * @param array $config   配置信息
     * @author Tinymeng <666@majiameng.com>
     */
    public function __construct($config)
    {
        $baseConfig = [
            'secretId'	=> '',
            'secretKey' => '',
            'bucket'    => '',
            'region' 	=> 'ap-beijing',
        ];
        
        // 兼容 accessKeyId/accessKeySecret 配置（映射到 secretId/secretKey）
        if (isset($config['accessKeyId']) && empty($config['secretId'])) {
            $config['secretId'] = $config['accessKeyId'];
        }
        if (isset($config['accessKeySecret']) && empty($config['secretKey'])) {
            $config['secretKey'] = $config['accessKeySecret'];
        }
        $this->config   = array_replace_recursive($baseConfig, $config);
        $this->bucket   = $this->config['bucket'];
    }

    /**
     * 格式化路径
     *
     * @param $path
     * @return string
     */
    protected static function normalizerPath($path, $is_dir = false)
    {
        $path = ltrim(PathLibrary::normalizerPath($path, $is_dir), '/');

        return $path == '/' ? '' : $path;
    }

    /**
     * 获得COS client上传对象
     *
     * @return Client
     * @author Tinymeng <666@majiameng.com>
     */
    protected function getClient()
    {
        if (!$this->client) {
            // 检查必需的配置
            if (empty($this->config['secretId']) || empty($this->config['secretKey'])) {
                throw new TinymengException('COS配置错误：secretId 和 secretKey 不能为空');
            }
            if (empty($this->config['region'])) {
                throw new TinymengException('COS配置错误：region 不能为空');
            }
            
            $schema = isset($this->config['transport']) && $this->config['transport'] === 'https' ? 'https' : 'http';
            
            $config = array(
                'region' => $this->config['region'],
                'schema' => $schema, //协议头部，默认为https
                'credentials'=> array(
                    'secretId'  => $this->config['secretId'],
                    'secretKey' => $this->config['secretKey']
                )
            );
            
            // 可选配置
            if (isset($this->config['timeout'])) {
                $config['timeout'] = (int)$this->config['timeout'];
            }
            if (isset($this->config['connectTimeout'])) {
                $config['connectTimeout'] = (int)$this->config['connectTimeout'];
            }
            
            $this->client = new Client($config);
        }
        return $this->client;
    }

    /**
     * 获得 Oss 实例
     * @return OssClient
     */
    public function getInstance()
    {
        return $this->getClient();
    }

    /**
     * 判断文件是否存在
     * @param string $path
     * @return bool
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function has($path)
    {
        try {
            $path = static::normalizerPath($path);
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $path
            );
            $this->getClient()->headObject($params);
            return true;
        }catch (Exception $e){
            // 如果文件不存在，会抛出异常，返回 false
            if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'NoSuchKey') !== false) {
                return false;
            }
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 读取文件
     * @param $path
     * @return array
     * @throws TinymengException
     * @internal param $file_name
     * @author Tinymeng <666@majiameng.com>
     */
    public function read($path)
    {
        try {
            $path = static::normalizerPath($path);
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $path
            );
            $result = $this->getClient()->getObject($params);
            return ['contents' => $result['Body']->getContents()];
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 获得文件流
     * @param string $path
     * @return array|bool
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function readStream($path)
    {
        try {
            //获得一个临时文件
            $tmpfname       = FileFunction::getTmpFile();

            file_put_contents($tmpfname, $this->read($path)['contents'] );

            $handle         = fopen($tmpfname, 'r');

            //删除临时文件
            FileFunction::deleteTmpFile($tmpfname);

            return ['stream' => $handle];
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 写入文件
     *
     * @param $path
     * @param $contents
     * @param array $option
     * @return mixed
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function write($path, $contents,$option=[])
    {
        try {
            $path = static::normalizerPath($path);
            $params = array_merge([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents
            ], $option);
            return $this->getClient()->putObject($params);
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 写入文件流
     * @param string $path
     * @param resource $resource
     * @param array $option
     * @return array|bool|false
     * @throws TinymengException
     */
    public function writeStream($path, $resource, $option=[])
    {
        try{
            $path = static::normalizerPath($path);
            //获得一个临时文件
            $tmpfname = FileFunction::getTmpFile();

            // 将资源流写入临时文件
            $contents = stream_get_contents($resource);
            file_put_contents($tmpfname, $contents);

            $params = array_merge([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => fopen($tmpfname, 'rb')
            ], $option);
            
            $this->getClient()->putObject($params);

            //删除临时文件
            FileFunction::deleteTmpFile($tmpfname);
            return true;
        } catch (Exception $e){
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
    public function uploadFile($path, $tmpfname, $option = []){
        try{
            $path = static::normalizerPath($path);
            if (!file_exists($tmpfname)) {
                throw new TinymengException("文件不存在: {$tmpfname}");
            }
            
            $params = array_merge([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => fopen($tmpfname, 'rb')
            ], $option);
            
            $result = $this->getClient()->putObject($params);
            
            if ($result === false || $result === null) {
                throw new TinymengException("文件上传失败");
            }
            
            // 构建返回信息
            $fileInfo = [
                'success' => true,
                'path' => $path,
                'key' => $path,
                'etag' => isset($result['ETag']) ? trim($result['ETag'], '"') : '',
                'size' => filesize($tmpfname),
            ];
            
            // 获取文件 URL
            try {
                $fileInfo['url'] = $this->getUrl($path, 0); // 0 表示永久 URL
            } catch (Exception $e) {
                // 如果获取 URL 失败，至少返回路径
                $fileInfo['url'] = $path;
            }
            
            // 添加其他可能的响应信息
            if (isset($result['Location'])) {
                $fileInfo['location'] = $result['Location'];
            }
            if (isset($result['RequestId'])) {
                $fileInfo['request_id'] = $result['RequestId'];
            }
            
            return $fileInfo;
        } catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 更新文件
     * @param string $path
     * @param string $contents
     * @return array|bool|false
     */
    public function update($path, $contents)
    {
        return $this->write($path, $contents);
    }

    /**
     * 更新文件流
     * @param string $path
     * @param resource $resource
     * @return array|bool|false
     */
    public function updateStream($path, $resource){
        return $this->writeStream($path, $resource);
    }

    /**
     * 列出目录文件
     * @param string $directory
     * @param bool|false $recursive
     * @return array
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function listContents($directory = '', $recursive = false){
        try{
            $directory = static::normalizerPath($directory, true);

            $params = [
                'Bucket' => $this->bucket,
                'Delimiter' => '/',
                'Prefix'    => $directory,
                'MaxKeys'  => isset($this->config['max_keys']) ? (int)$this->config['max_keys'] : 1000,
            ];
            
            if (!empty($directory)) {
                $params['Marker'] = '';
            }

            $result_obj = $this->getClient()->listObjects($params);

            $file_list  = isset($result_obj['Contents']) ? $result_obj['Contents'] : [];//文件列表
            $dir_list   = isset($result_obj['CommonPrefixes']) ? $result_obj['CommonPrefixes'] : [];//文件夹列表
            $data       = [];

            if (is_array($dir_list) && count($dir_list) > 0 ) {
                foreach ($dir_list as $key => $dir) {
                    $prefix = isset($dir['Prefix']) ? $dir['Prefix'] : (is_string($dir) ? $dir : '');
                    $data[] = [
                        'path'      => $prefix,
                        'prefix'    => $params['Prefix'],
                        'marker'    => isset($params['Marker']) ? $params['Marker'] : '',
                        'file_type' => self::FILE_TYPE_DIR
                    ];
                }
            }

            if (is_array($file_list) && count($file_list) > 0 ) {
                foreach ($file_list as $key => $file) {
                    $fileKey = isset($file['Key']) ? $file['Key'] : (is_string($file) ? $file : '');
                    if ($fileKey == $directory) {
                        continue; // 跳过目录本身
                    }
                    $data[] = [
                        'path'              => $fileKey,
                        'last_modified'     => isset($file['LastModified']) ? $file['LastModified'] : '',
                        'e_tag'             => isset($file['ETag']) ? $file['ETag'] : '',
                        'file_size'         => isset($file['Size']) ? $file['Size'] : 0,
                        'prefix'            => $params['Prefix'],
                        'marker'            => isset($params['Marker']) ? $params['Marker'] : '',
                        'file_type'         => self::FILE_TYPE_FILE,
                    ];
                }
            }

            return $data;
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 获取资源的元信息，但不返回文件内容
     * @param $path
     * @return array|bool
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function getMetadata($path)
    {
        try {
            $path = static::normalizerPath($path);
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $path
            );
            $result = $this->getClient()->headObject($params);
            if ( !empty($result) ) {
                return $result;
            }
        }catch (Exception $e) {
            throw new TinymengException($e->getMessage());
        }
        return false;
    }

    /**
     * 获得文件大小
     * @param string $path
     * @return array
     * @author Tinymeng <666@majiameng.com>
     */
    public function getSize($path)
    {
        $file_info = $this->getMetadata($path);
        $contentLength = isset($file_info['ContentLength']) ? $file_info['ContentLength'] : (isset($file_info['content-length']) ? $file_info['content-length'] : 0);
        return $file_info != false && $contentLength > 0 ? [ 'size' => $contentLength ] : ['size' => 0];
    }

    /**
     * 获得文件Mime类型
     * @param string $path
     * @return mixed string|null
     * @author Tinymeng <666@majiameng.com>
     */
    public function getMimetype($path)
    {
        $file_info = $this->getMetadata($path);
        $contentType = isset($file_info['ContentType']) ? $file_info['ContentType'] : (isset($file_info['content-type']) ? $file_info['content-type'] : '');
        return $file_info != false && !empty($contentType) ? [ 'mimetype' => $contentType ] : false;
    }

    /**
     * 获得文件最后修改时间
     * @param string $path
     * @return array 时间戳
     * @author Tinymeng <666@majiameng.com>
     */
    public function getTimestamp($path){
        $file_info = $this->getMetadata($path);
        $lastModified = isset($file_info['LastModified']) ? $file_info['LastModified'] : (isset($file_info['last-modified']) ? $file_info['last-modified'] : '');
        return $file_info != false && !empty($lastModified)
            ? ['timestamp' => is_numeric($lastModified) ? $lastModified : strtotime($lastModified) ]
            : ['timestamp' => 0 ];
    }

    /**
     * 获得文件模式 (未实现)
     * @param string $path
     * @author Tinymeng <666@majiameng.com>
     * @return string
     */
    public function getVisibility($path){
        return self::VISIBILITY_PUBLIC;
    }

    /**
     * 重命名文件
     * @param string $path
     * @param string $newpath
     * @return bool
     * @throws TinymengException
     * @internal param $oldname
     * @internal param $newname
     * @author Tinymeng <666@majiameng.com>
     */
    public function rename($path, $newpath)
    {
        try {
            /**
             * 如果是一个资源，请保持最后不是以"/"结尾！
             *
             */
            $path = static::normalizerPath($path);
            $newpath = static::normalizerPath($newpath);

            // 先复制文件
            $copyParams = [
                'Bucket' => $this->bucket,
                'Key' => $newpath,
                'CopySource' => $this->bucket . '/' . $path
            ];
            $this->getClient()->copyObject($copyParams);
            
            // 删除原文件
            return $this->delete($path);
        }catch (Exception $e){
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
     * @author Tinymeng <666@majiameng.com>
     */
    public function copy($path, $newpath)
    {
        try {
            $path = static::normalizerPath($path);
            $newpath = static::normalizerPath($newpath);
            
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $newpath,
                'CopySource' => $this->bucket . '/' . $path
            ];
            $result = $this->getClient()->copyObject($params);
            return $result !== false;
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 删除文件或者文件夹
     * @param string $path
     * @return bool
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function delete($path)
    {
        try{
            $path = static::normalizerPath($path);
            $params = array(
                'Bucket' => $this->bucket,
                'Key' => $path
            );
            $result = $this->getClient()->deleteObject($params);
            return $result !== false;
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 删除文件夹
     * @param string $path
     * @return mixed
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function deleteDir($path)
    {
        try{
            //递归去删除全部文件
            return $this->recursiveDelete($path);
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 递归删除全部文件
     * @param $path
     * @author Tinymeng <666@majiameng.com>
     */
    protected function recursiveDelete($path)
    {
        $file_list = $this->listContents($path);

        // 如果当前文件夹文件不为空,则直接去删除文件夹
        if ( is_array($file_list) && count($file_list) > 0 ) {
            foreach ($file_list as $file) {
                if ($file['path'] == $path) {
                    continue;
                }
                if ($file['file_type'] == self::FILE_TYPE_FILE) {
                    $this->delete($file['path']);
                } else {
                    $this->recursiveDelete($file['path']);
                }
            }
        }

        $params = array(
            'Bucket' => $this->bucket,
            'Key' => static::normalizerPath($path)
        );
        $this->getClient()->deleteObject($params);
    }

    /**
     * 创建文件夹
     * @param string $dirname
     * @return array|false
     * @throws TinymengException
     * @author Tinymeng <666@majiameng.com>
     */
    public function createDir($dirname)
    {
        try{
            $dirname = static::normalizerPath($dirname, true);
            // COS 中创建文件夹实际上是通过上传一个以 "/" 结尾的空对象来实现的
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $dirname,
                'Body' => ''
            ];
            $result = $this->getClient()->putObject($params);
            return $result !== false;
        }catch (Exception $e){
            throw new TinymengException($e->getMessage());
        }
    }

    /**
     * 设置文件模式 (未实现)
     * @param string $path
     * @param string $visibility
     * @return bool
     * @author Tinymeng <666@majiameng.com>
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }

    /**
     * 获取当前文件的URL访问路径
     * @param  string $file 文件名
     * @param  integer $expire_at 有效期，单位：秒（0 表示永久有效，使用配置的 urlPrefix）
     * @return string
     * @throws TinymengException
     * @author Tinymeng <879042886@qq.com>
     */
    public function getUrl($file, $expire_at = 3600)
    {
        try {
            $file = static::normalizerPath($file);
            
            // 如果配置了 urlPrefix 且不需要签名 URL，直接使用配置的 URL
            if ($expire_at == 0 && isset($this->config['urlPrefix'])) {
                return rtrim($this->config['urlPrefix'], '/') . '/' . ltrim($file, '/');
            }
            
            // 需要签名 URL 或没有配置 urlPrefix
            if ($expire_at > 0) {
                $params = [
                    'Bucket' => $this->bucket,
                    'Key' => $file
                ];
                
                // 生成预签名 URL
                $command = $this->getClient()->getCommand('getObject', $params);
                $request = $this->getClient()->createPresignedRequest($command, '+' . $expire_at . ' seconds');
                return (string)$request->getUri();
            } else {
                // 没有配置 urlPrefix 且不需要签名，尝试使用 getObjectUrl
                try {
                    return $this->getClient()->getObjectUrl($this->bucket, $file);
                } catch (Exception $e) {
                    // 如果失败，使用配置的 urlPrefix
                    if (isset($this->config['urlPrefix'])) {
                        return rtrim($this->config['urlPrefix'], '/') . '/' . ltrim($file, '/');
                    }
                    throw $e;
                }
            }
        } catch (Exception $e) {
            // 如果生成签名 URL 失败，尝试使用配置的 urlPrefix
            if (isset($this->config['urlPrefix'])) {
                $file = static::normalizerPath($file);
                return rtrim($this->config['urlPrefix'], '/') . '/' . ltrim($file, '/');
            }
            throw new TinymengException($e->getMessage());
        }
    }

}
