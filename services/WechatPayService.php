<?php

namespace services;

use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

/**
 * wechat pay service
 * 
 */
class WechatPayService {


    // APP ID
    protected static $appId = '';

    // 商户ID
    protected static $mchId = '';

    // API V3 Key
    protected static $apiKey = '';

    // 「商户API证书」的「证书序列号」
    protected static $merchantCertificateSerial = '';

    protected static $apiClientKeyPemPath = '';

    protected static $certPemPath = '';


    public function __construct()
    {
        self::$appId = '';
        self::$mchId = '';
        self::$apiKey = '';
        self::$merchantCertificateSerial = '';
        self::$apiClientKeyPemPath = $_SERVER['DOCUMENT_ROOT'].'/cert/apiclient_key.pem';
        self::$certPemPath = $_SERVER['DOCUMENT_ROOT'].'/cert/cert.pem';
    }

    /**
     * 获取配置参数
     * 
     * @param string $option 获取配置值 可能值[ appId、mchId、 apiKey、 merchantCertificateSerial、 apiClientKeyPemPath、 certPemPath]
     * @return string
     */
    public static function getConfig($option) {
        new self();

        return self::$$option;
    }

    /**
     * 获取运行实例
     * 
     */
    public static function getInstance() {

        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = 'file://'. self::$apiClientKeyPemPath;
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = 'file://'. self::$certPemPath;
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);

        // 构造一个 APIv3 客户端实例
        $instance = Builder::factory([
            // 商户ID
            'mchid'      => self::$mchId,
            // 「商户API证书」的「证书序列号」
            'serial'     => self::$merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);

        return $instance;
    }

    /**
     * 生成 cert.pem 证书文件
     * 
     * @return mixed
     */
    public static function generateCertIficates(){
        //请求参数(报文主体)
        $headers = self::sign('GET','https://api.mch.weixin.qq.com/v3/certificates','');
        $result = self::curl_get('https://api.mch.weixin.qq.com/v3/certificates',$headers);
        $result = json_decode($result,true);
        // 解密内容
        $content = self::decryptToString($result['data'][0]['encrypt_certificate']['associated_data'],$result['data'][0]['encrypt_certificate']['nonce'],$result['data'][0]['encrypt_certificate']['ciphertext']);
        echo $content;die;
    }

    // ==========================     助手函数       ================================================

    // 获取私钥
    public static function getMchKey(){
        //path->私钥文件存放路径
        return openssl_get_privatekey(file_get_contents(self::$apiClientKeyPemPath));
    }

    /**
     * 签名
     * @param string $http_method    请求方式GET|POST
     * @param string $url            url
     * @param string $body           报文主体
     * @return array
     */
    public static function sign($http_method = 'POST',$url = '',$body = ''){
        $mch_private_key = self::getMchKey();//私钥
        $timestamp = time();//时间戳
        $nonce = self::getRandomStr(32);//随机串
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        //构造签名串
        $message = $http_method."\n".
            $canonical_url."\n".
            $timestamp."\n".
            $nonce."\n".
            $body."\n";//报文主体
        //计算签名值
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
        //设置HTTP头
        $token = sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            self::$mchId, $nonce, $timestamp, self::$merchantCertificateSerial, $sign);
        $headers = [
            'Accept: application/json',
            'User-Agent: */*',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: '.$token,
        ];
        return $headers;
    }
    
    /**
     * 获得随机字符串
     * @param $len      integer       需要的长度
     * @param $special  bool      是否需要特殊符号
     * @return string       返回随机字符串
     */
    public static function getRandomStr($len, $special=false){
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if($special){
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for($i=0; $i<$len; $i++){
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        return $str;
    }

    //get请求
    public static function curl_get($url,$headers=array())
    {
        $info = curl_init();
        curl_setopt($info,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($info,CURLOPT_HEADER,0);
        curl_setopt($info,CURLOPT_NOBODY,0);
        curl_setopt($info,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($info,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($info,CURLOPT_SSL_VERIFYHOST,false);
        //设置header头
        curl_setopt($info, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($info,CURLOPT_URL,$url);
        $output = curl_exec($info);
        curl_close($info);
        return $output;
    }
    
    const KEY_LENGTH_BYTE = 32;
    const AUTH_TAG_LENGTH_BYTE = 16;

    /**
     * Decrypt AEAD_AES_256_GCM ciphertext
     *
     * @param string    $associatedData     AES GCM additional authentication data
     * @param string    $nonceStr           AES GCM nonce
     * @param string    $ciphertext         AES GCM cipher text
     *
     * @return string|bool      Decrypted string on success or FALSE on failure
     */
    public static function decryptToString($associatedData, $nonceStr, $ciphertext) {
        $aesKey = self::$apiKey;
        $ciphertext = \base64_decode($ciphertext);
        if (strlen($ciphertext) <= self::AUTH_TAG_LENGTH_BYTE) {
            return false;
        }

        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_is_available') && \sodium_crypto_aead_aes256gcm_is_available()) {
            return \sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $aesKey);
		}

        // ext-libsodium (need install libsodium-php 1.x via pecl)
        if (function_exists('\Sodium\crypto_aead_aes256gcm_is_available') && \Sodium\crypto_aead_aes256gcm_is_available()) {
            return \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $aesKey);
		}

        // openssl (PHP >= 7.1 support AEAD)
        if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', \openssl_get_cipher_methods())) {
            $ctext = substr($ciphertext, 0, -self::AUTH_TAG_LENGTH_BYTE);
            $authTag = substr($ciphertext, -self::AUTH_TAG_LENGTH_BYTE);

            return \openssl_decrypt($ctext, 'aes-256-gcm', $aesKey, \OPENSSL_RAW_DATA, $nonceStr,
				$authTag, $associatedData);
		}

        throw new \RuntimeException('AEAD_AES_256_GCM需要PHP 7.1以上或者安装libsodium-php');
    }

}