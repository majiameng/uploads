<?php

// +----------------------------------------------------------------------
// | date: 2015-09-09
// +----------------------------------------------------------------------
// | OssAdapter.php: oss上传
// +----------------------------------------------------------------------
// | Author: yangyifan <666@majiameng.com>
// +----------------------------------------------------------------------


namespace tinymeng\uploads\Gateways;

use Exception;
use OSS\OssClient;
use OSS\Core\OssException;
use tinymeng\uploads\Connector\Gateway;
use tinymeng\uploads\Helper\PathLibrary;
use tinymeng\uploads\Helper\FileFunction;

class Oss extends  Gateway
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
     * @author yangyifan <666@majiameng.com>
     */
    public function __construct($config)
    {
        $baseConfig = [
            'accessKeyId'		=> '',
            'accessKeySecret' 	=> '',
            'endpoint'			=> '',
            'isCName'			=> false,
            'securityToken'		=> null,
            'bucket'            => '',
            'timeout'           => '5184000',
            'connectTimeout'    => '10',
            'transport'     	=> 'http',//如果支持https，请填写https，如果不支持请填写http
            'max_keys'          => 1000,//max-keys用于限定此次返回object的最大数，如果不设定，默认为100，max-keys取值不能大于1000
        ];
        $this->config   = array_replace_recursive($baseConfig,$config);
        $this->bucket   = $this->config['bucket'];
        //设置路径前缀
        $this->setPathPrefix($this->config['transport'] . '://' . $this->config['bucket'] . '.' .  $this->config['endpoint']);
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
     * 获得OSS client上传对象
     *
     * @return \OSS\OssClient
     * @author yangyifan <666@majiameng.com>
     */
    protected function getOss()
    {
        if (!$this->upload) {
            $this->upload = new OssClient(
                $this->config['accessKeyId'],
                $this->config['accessKeySecret'],
                $this->config['endpoint'],
                $this->config['isCName'],
                $this->config['securityToken']
            );

            //设置请求超时时间
            $this->upload->setTimeout($this->config['timeout']);

            //设置连接超时时间
            $this->upload->setConnectTimeout($this->config['connectTimeout']);
        }

        return $this->upload;
    }

    /**
     * 获得 Oss 实例
     *
     * @return OssClient
     */
    public function getInstance()
    {
        return $this->getOss();
    }

    /**
     * 判断文件是否存在
     *
     * @param string $path
     * @return bool
     * @author yangyifan <666@majiameng.com>
     */
    public function has($path)
    {
        try {
            return $this->getOss()->doesObjectExist($this->bucket, $path) != false ? true : false;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 读取文件
     *
     * @param $file_name
     * @author yangyifan <666@majiameng.com>
     */
    public function read($path)
    {
        try {
            return ['contents' => $this->getOss()->getObject($this->bucket, static::normalizerPath($path)) ];
        }catch (OssException $e){

        }
        return false;

    }

    /**
     * 获得文件流
     *
     * @param string $path
     * @return array|bool
     * @author yangyifan <666@majiameng.com>
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
        }catch (OssException $e){

        }

        return false;
    }

    /**
     * 写入文件
     *
     * @param $file_name
     * @param $contents
     * @author yangyifan <666@majiameng.com>
     */
    public function write($path, $contents)
    {
        try {
            $this->getOss()->putObject($this->bucket, $path, $contents, $option = []);

            return true;
        }catch (OssException $e){
            var_dump($e->getMessage());
        }
        return false;
    }

    /**
     * 写入文件流
     *
     * @param string $path
     * @param resource $resource
     */
    public function writeStream($path, $resource)
    {
        try{
            //获得一个临时文件
            $tmpfname       = FileFunction::getTmpFile();

            file_put_contents($tmpfname, $resource);

            $this->getOss()->uploadFile($this->bucket, $path, $tmpfname, $option = []);

            //删除临时文件
            FileFunction::deleteTmpFile($tmpfname);

            return true;
        } catch (OssException $e){
            
        }
        return false;
    }

    /**
     * Name: 上传文件
     * Author: Tinymeng <666@majiameng.com>
     * @param $path
     * @param $tmpfname
     * @return bool
     */
    public function uploadFile($path, $tmpfname){
        try{
            $this->getOss()->uploadFile($this->bucket, $path, $tmpfname, $option = []);
            return true;
        } catch (OssException $e){

        }
        return false;
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
     * @return array
     * @author yangyifan <666@majiameng.com>
     */
    public function listContents($directory = '', $recursive = false)
    {
        try{
            $directory = static::normalizerPath($directory, true);

            $options = [
                'delimiter' => '/' ,
                'prefix'    => $directory,
                'max-keys'  => $this->config['max_keys'],
                'marker'    => '',
            ];

            $result_obj = $this->getOss()->listObjects($this->bucket, $options);

            $file_list  = $result_obj->getObjectList();//文件列表
            $dir_list   = $result_obj->getPrefixList();//文件夹列表
            $data       = [];

            if (is_array($dir_list) && count($dir_list) > 0 ) {
                foreach ($dir_list as $key => $dir) {
                    $data[] = [
                        'path'      => $dir->getPrefix(),
                        'prefix'    => $options['prefix'],
                        'marker'    => $options['marker'],
                        'file_type' => self::FILE_TYPE_DIR
                    ];
                }
            }

            if (is_array($file_list) && count($file_list) > 0 ) {
                foreach ($file_list as $key => $file) {
                    if ($key == 0 ) {
                        $data[] = [
                            'path'      => $file->getKey(),
                            'prefix'    => $options['prefix'],
                            'marker'    => $options['marker'],
                            'file_type' => self::FILE_TYPE_DIR
                        ];
                    } else {
                        $data[] = [
                            'path'              => $file->getKey(),
                            'last_modified'     => $file->getLastModified(),
                            'e_tag'             => $file->getETag(),
                            'file_size'         => $file->getSize(),
                            'prefix'            => $options['prefix'],
                            'marker'            => $options['marker'],
                            'file_type'         => self::FILE_TYPE_FILE,
                        ];
                    }
                }
            }

            return $data;
        }catch (Exception $e){

        }
        return [];
    }

    /**
     * 获取资源的元信息，但不返回文件内容
     *
     * @param $path
     * @return array|bool
     * @author yangyifan <666@majiameng.com>
     */
    public function getMetadata($path)
    {
        try {
            $file_info = $this->getOss()->getObjectMeta($this->bucket, $path);
            if ( !empty($file_info) ) {
                return $file_info;
            }
        }catch (OssException $e) {

        }
        return false;
    }

    /**
     * 获得文件大小
     *
     * @param string $path
     * @return array
     * @author yangyifan <666@majiameng.com>
     */
    public function getSize($path)
    {
        $file_info = $this->getMetadata($path);
        return $file_info != false && $file_info['content-length'] > 0 ? [ 'size' => $file_info['content-length'] ] : ['size' => 0];
    }

    /**
     * 获得文件Mime类型
     *
     * @param string $path
     * @return mixed string|null
     * @author yangyifan <666@majiameng.com>
     */
    public function getMimetype($path)
    {
        $file_info = $this->getMetadata($path);
        return $file_info != false && !empty($file_info['content-type']) ? [ 'mimetype' => $file_info['content-type'] ] : false;
    }

    /**
     * 获得文件最后修改时间
     *
     * @param string $path
     * @return array 时间戳
     * @author yangyifan <666@majiameng.com>
     */
    public function getTimestamp($path)
    {
        $file_info = $this->getMetadata($path);
        return $file_info != false && !empty($file_info['last-modified'])
            ? ['timestamp' => strtotime($file_info['last-modified']) ]
            : ['timestamp' => 0 ];
    }

    /**
     * 获得文件模式 (未实现)
     *
     * @param string $path
     * @author yangyifan <666@majiameng.com>
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
     * @author yangyifan <666@majiameng.com>
     */
    public function rename($path, $newpath)
    {
        try {
            /**
             * 如果是一个资源，请保持最后不是以“/”结尾！
             *
             */
            $path = static::normalizerPath($path);

            $this->getOss()->copyObject($this->bucket, $path, $this->bucket, static::normalizerPath($newpath), []);
            $this->delete($path);
            return true;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 复制文件
     *
     * @param $path
     * @param $newpath
     * @return boolean
     * @author yangyifan <666@majiameng.com>
     */
    public function copy($path, $newpath)
    {
        try {
            $this->getOss()->copyObject($this->bucket, $path, $this->bucket, static::normalizerPath($newpath), []);
            return true;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 删除文件或者文件夹
     *
     * @param $path
     * @author yangyifan <666@majiameng.com>
     */
    public function delete($path)
    {
        try{
            $this->getOss()->deleteObject($this->bucket, $path);
            return true;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 删除文件夹
     *
     * @param string $path
     * @return mixed
     * @author yangyifan <666@majiameng.com>
     */
    public function deleteDir($path)
    {
        try{
            //递归去删除全部文件
            $this->recursiveDelete($path);

            return true;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 递归删除全部文件
     *
     * @param $path
     * @author yangyifan <666@majiameng.com>
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

        $this->getOss()->deleteObject($this->bucket, $path);
    }

    /**
     * 创建文件夹
     *
     * @param string $dirname
     * @author yangyifan <666@majiameng.com>
     */
    public function createDir($dirname)
    {
        try{
            $this->getOss()->createObjectDir($this->bucket, static::normalizerPath($dirname, true));
            return true;
        }catch (OssException $e){

        }
        return false;
    }

    /**
     * 设置文件模式 (未实现)
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     * @author yangyifan <666@majiameng.com>
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }

    /**
     * 获取当前文件的URL访问路径
     * @param  string $file 文件名
     * @param  integer $expire_at 有效期，单位：秒
     * @return string
     * @author qsnh <616896861@qq.com>
     */
    public function getUrl($file, $expire_at = 3600)
    {
        try {
            $accessUrl = $this->getOss()->signUrl($this->bucket, $file, $expire_at);
        } catch (OssException $e) {
            return false;
        }

        return $accessUrl;
    }

}