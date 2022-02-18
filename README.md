# 通用文件上传到第三方说明文档

* oss
* qiniu

### 安装

```
composer require tinymeng/uploads -vvv
```

> 类库使用的命名空间为`\\tinymeng\\uploads`



### 目录结构

```
.
├── src                              代码源文件目录
│   ├── Connector
│   │   ├── Gateway.php            必须继承的抽象类
│   │   └── GatewayInterface.php   必须实现的接口
│   ├── exception
│   │   └── TinymengException.php
│   ├── Gateways
│   │   ├── Cos.php
│   │   └── Oss.php
│   │   └── Qiniu.php
│   ├── Helper
│   │   ├── FileFunction.php
│   │   ├── MimeType.php
│   │   ├── PathLibrary.php
│   │   └── Str.php                 字符串辅助类
│   └── Upload.php                   抽象实例类
├── composer.json                    composer文件
├── LICENSE                          MIT License
└── README.md                        说明文件
```

###### 开始

*  use Storage

```
use Storage;
```

* 示例代码

```
        $config = [
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
        $drive = Upload::oss($config);
        $a = $drive->uploadFile("test6.png", $_FILES['file']['tmp_name'] );//file上传文件


        $image  = "11/22/33/7125_yangxiansen.jpg";
        $image2 = "111.png";
        $image3 = "2.txt";

        $drive = Upload::oss($config);
        $drive->write("test2.txt", "111222",$config);
        $drive->getMetadata($image2);                                                         //判断文件是否存在
        $drive->geturl($image2);                                                              //获得文件的 url
        $drive->has($image2);                                                                 //判断文件是否存在
        $drive->listContents('');                                                             //列出文件列表
        $drive->getSize($image2);                                                             //获得图片大小
        $drive->getMimetype($image2);                                                         //获得图片mime类型
        $drive->getTimestamp($image2);                                                        //获得图片上传时间戳
        $drive->read($image3);                                                                //获得文件信息
        $drive->readStream($image3);                                                          //获得文件信息
        $drive->rename($image3, '4.txt/');                                                    //重命名文件
        $drive->copy('4.txt/', '/txt/5.txt');                                                 //复制文件
        $drive->delete('/txt/5.txt');                                                         //删除文件
        $drive->write("/txt/4.txt", $drive->read("/4.txt");                                //上传文件
        $drive->write("/test2.txt", "111222");                                                //上传文件
        $drive->deleteDir('txt/');                                                            //删除文件夹
        $drive->createDir('test3/');                                                          //创建文件夹
        $handle = fopen('/tmp/email.png', 'r');
        $drive->writeStream("/write/test3.png", $handle);                                  //上传文件(文件流方式)
        $drive->writeStream("/test6.png", $drive->readStream('/write/test3.png') );         //上传文件(文件流方式)

```

>注意：详细使用，可以参考单元测试里面的代码。

###### 配置信息


```

'qiniu' => [
    'driver'        => 'qiniu',
    'domain'        => '',//你的七牛域名
    'access_key'    => '',//AccessKey
    'secret_key'    => '',//SecretKey
    'bucket'        => '',//Bucket名字
    'transport'     => 'http',//如果支持https，请填写https，如果不支持请填写http
],

'oss'	=> [
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
],

public Qcloud\Cos\Client upload(string $bucket, string $key, $body, array $options = array());

'cos'	=> [
    'accessKeyId'		=> '',
    'accessKeySecret' 	=> '',
    'bucket'			=> '',
    'isCName'			=> false,
    'securityToken'		=> null,
    'bucket'            => '',
    'timeout'           => '5184000',
    'connectTimeout'    => '10',
    'transport'     	=> 'http',//如果支持https，请填写https，如果不支持请填写http
    'max_keys'          => 1000,//max-keys用于限定此次返回object的最大数，如果不设定，默认为100，max-keys取值不能大于1000
],

```


### 版本修复

2021-03-12 更新以下功能
Tag v1.0.2
```
1.增加oss上传设置元信息

可直接下载的文件
$option = [
    OssClient::OSS_CONTENT_TYPE => 'application/octet-stream',
];
$oss_upload_result = $drive->uploadFile($save_file_path, $upload_path,$option);
```

2020-11-12 更新以下功能
Tag v1.0.1
```
1.修改TinymengException异常类
```

###### 协议

MIT
