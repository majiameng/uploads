<?php
/**
 * Uploads 组件默认配置文件
 * 
 * @author Tinymeng <666@majiameng.com>
 */
return [
    /**
     * 默认使用的存储驱动
     * 可选值: oss, cos, qiniu
     */
    'default' => 'oss',
    
    /**
     * 阿里云 OSS 配置
     */
    'oss' => [
        'accessKeyId'        => '',           // AccessKey ID
        'accessKeySecret'    => '',           // AccessKey Secret
        'endpoint'           => '',           // OSS 节点地址，如: oss-cn-beijing.aliyuncs.com
        'bucket'             => '',           // Bucket 名称
        'isCName'            => false,         // 是否使用自定义域名
        'securityToken'        => null,       // STS Token（临时访问凭证）
        'timeout'            => '5184000',    // 请求超时时间（秒）
        'connectTimeout'     => '10',         // 连接超时时间（秒）
        'transport'          => 'https',      // 传输协议: http 或 https
        'max_keys'           => 1000,         // 列出文件时的最大数量（1-1000）
    ],
    
    /**
     * 腾讯云 COS 配置
     */
    'cos' => [
        'secretId'           => '',           // Secret ID
        'secretKey'          => '',           // Secret Key
        'bucket'             => '',           // Bucket 名称
        'region'             => 'ap-beijing', // 地域，如: ap-beijing, ap-shanghai
        'isCName'            => false,         // 是否使用自定义域名
        'securityToken'      => null,         // STS Token（临时访问凭证）
        'timeout'            => '5184000',    // 请求超时时间（秒）
        'connectTimeout'     => '10',         // 连接超时时间（秒）
        'transport'          => 'https',      // 传输协议: http 或 https
        'max_keys'           => 1000,         // 列出文件时的最大数量（1-1000）
        'urlPrefix'          => '',           // 自定义域名前缀，如: https://bucket.cos.ap-beijing.myqcloud.com
        // 兼容字段（会自动映射）
        'accessKeyId'        => '',           // 兼容字段，会自动映射到 secretId
        'accessKeySecret'    => '',           // 兼容字段，会自动映射到 secretKey
        'endpoint'            => '',           // 兼容字段，会自动提取 region
    ],
    
    /**
     * 七牛云 Qiniu 配置
     */
    'qiniu' => [
        'driver'             => 'qiniu',      // 驱动类型
        'domain'             => '',           // 七牛域名，如: http://your-domain.com
        'access_key'         => '',           // AccessKey
        'secret_key'         => '',           // SecretKey
        'bucket'             => '',           // Bucket 名称
        'transport'          => 'https',      // 传输协议: http 或 https
    ],
];

