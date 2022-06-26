## 介绍

*** 微信支付 API V3 ***

[微信支付文档](https://pay.weixin.qq.com/wiki/doc/apiv3/index.shtml)

[PHP SDK](https://github.com/wechatpay-apiv3/wechatpay-php)

本 DEMO 封装了微信支付V3 的调用方法，让PHP对接微信支付更加简单。

已 Native 支付为例，进行了简单的封装。也可自行修改，加入更多场景的支付案例

## 安装

```shell
composer require wechatpay/wechatpay
```

## 配置

### `services/WechatPayService.php` 配置支付参数

```php
// __construct 方法
// APP ID
self::$appId = '';
// 商户ID
self::$mchId = '';
// API V3 Key
self::$apiKey = '';
// 「商户API证书」的「证书序列号」
self::$merchantCertificateSerial = '';
// 商户API证书
self::$apiClientKeyPemPath = $_SERVER['DOCUMENT_ROOT'].'/cert/apiclient_key.pem';
// 平台证书（需要手动生成）
self::$certPemPath = $_SERVER['DOCUMENT_ROOT'].'/cert/cert.pem';
```

### 根据配置参数生成 `cert.pem`

#### 方案一

调用SDK内置方法

```shell
> vendor/bin/CertificateDownloader.php

Usage: 微信支付平台证书下载工具 [-hV]
                    -f=<privateKeyFilePath> -k=<apiV3key> -m=<merchantId>
                    -s=<serialNo> -o=[outputFilePath] -u=[baseUri]
Options:
  -m, --mchid=<merchantId>   商户号
  -s, --serialno=<serialNo>  商户证书的序列号
  -f, --privatekey=<privateKeyFilePath>
                             商户的私钥文件
  -k, --key=<apiV3key>       ApiV3Key
  -o, --output=[outputFilePath]
                             下载成功后保存证书的路径，可选参数，默认为临时文件目录夹
  -u, --baseuri=[baseUri]    接入点，默认为 https://api.mch.weixin.qq.com/
  -V, --version              Print version information and exit.
  -h, --help                 Show this help message and exit.
```

#### 方案二

调用封装好的方法，获取到证书内容，对内容手动格式化，放入 `/cert/cert.pem` 文件中

```php
(new WechatPayService)::generateCertIficates();
```

## 使用

> `services/WechatPayService.php` 可以理解为 支付类的基类
>
> `services/TradeService.php` 公共的支付类
>
> `index.php` 入口文件，演示测试

** `index.php`方法的输出示例 **

```
Array
(
    [code] => 200
    [code_url] => weixin://wxpay/bizpayurl?pr=MTyYVP1zz
)
```
```
Array
(
    [code] => PARAM_ERROR
    [detail] => Array
        (
            [location] => body
            [value] => 3
        )

    [message] => 输入源“/body/out_trade_no”映射到值字段“商户订单号”字符串规则校验失败，字节数 3，小于最小值 6
)
```

** 常见问题 **

- 解密消息失败（有可能是两个小时之前的消息，需要在解密消息中配置回调消息有效时长 `services/TradeService.php $timeOffsetStatus变量`）