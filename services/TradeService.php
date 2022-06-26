<?php

namespace services;

use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

/**
 * trade service
 * 
 */
class TradeService {

    /**
     * native 支付下单并返回支付二维码内容
     * 
     * @param string $orderNumber 商户订单号
     * @param string $description 订单简介
     * @param int $total 订单金额
     * @param array $attach 附加数据
     * @param string $notifyUrl 回调地址
     * 
     * @return array
     */
    public function nativePay(string $orderNumber, string $description = '', int $total = 0, array $attach = [], string $notifyUrl = '') {

        $instance = (new WechatPayService)->getInstance();
        try {
            $resp = $instance
                ->chain('v3/pay/transactions/native')
                ->post(['json' => [
                    'mchid'        => WechatPayService::getConfig('mchId'),
                    'out_trade_no' => $orderNumber,
                    'appid'        => WechatPayService::getConfig('appId'),
                    'description'  => $description,
                    'notify_url'   => $notifyUrl,
                    'attach' => json_encode($attach),
                    'amount'       => [
                        'total'    => $total,
                        'currency' => 'CNY'
                    ],
                ]]);

            if ($resp->getStatusCode() == 200) {
                $body = json_decode($resp->getBody(), true);
                $codeUrl = $body['code_url'] ?? '';
                return [
                    'code' => 200,
                    'code_url' => $codeUrl,
                ];
            }
            // echo $resp->getStatusCode(), PHP_EOL;
            // echo $resp->getBody(), PHP_EOL;
        } catch (\Exception $e) {

            $errorContent = [];
            // echo $e->getMessage(), PHP_EOL;
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $r = $e->getResponse();
                // echo $r->getStatusCode() . ' ' . $r->getReasonPhrase(), PHP_EOL;
                $errorContent = json_decode($r->getBody(), true);
            }
            // echo $e->getTraceAsString(), PHP_EOL;

            return $errorContent;
        }
    }

    /**
     * 申请退款API
     * 
     * @param string $orderNumber 微信支付单号
     * @param string $payNumber 商户订单号
     * @param string $refundNumber 退款单号
     * @param int $refundPrice 退款金额
     * @param int $total 原订单金额
     * @param string $notifyUrl 回调地址
     * 
     * @return array
     */
    public function nativePayRefund($orderNumber = '', $payNumber = '', $refundNumber, $refundPrice, $total, $notifyUrl) {

        $instance = (new WechatPayService)->getInstance();

        try {
            $resp = $instance
                ->chain('v3/refund/domestic/refunds')
                ->post([
                    'json' => [
                        'out_trade_no' => $orderNumber,
                        'out_refund_no' => $refundNumber,
                        'notify_url'   => $notifyUrl,
                        'amount'       => [
                            'refund' => (int) $refundPrice,
                            'total'    => (int) $total,
                            'currency' => 'CNY'
                        ],
                    ]
                ]);

            return [
                'code' => 200,
                'message' => '退款发起成功，处理中'
            ];

        } catch (\Exception $e) {            
            return [
                'code' => 0,
                'message' => '退款失败，请稍后再试'
            ];
        }

        return [
            'code' => 0,
            'message' => '退款失败，请稍后再试'
        ];
    }

    /**
     * 解密消息
     * 
     * @param array $header header数据
     * @param array $inBody body数据
     * 
     * @return array
     */
    public function decryptNotifyData($header, $inBody) {

        $inWechatpaySignature = $header['wechatpay-signature'] ?? '';// 请根据实际情况获取
        $inWechatpayTimestamp = $header['wechatpay-timestamp'] ?? '';// 请根据实际情况获取
        $inWechatpaySerial = $header['wechatpay-serial'] ?? '';// 请根据实际情况获取
        $inWechatpayNonce = $header['wechatpay-nonce'] ?? '';// 请根据实际情况获取

        // $inBody = '';// 请根据实际情况获取，例如: file_get_contents('php://input');

        $apiv3Key = WechatPayService::getConfig('apiKey');// 在商户平台上设置的APIv3密钥

        // 根据通知的平台证书序列号，查询本地平台证书文件，
        // 假定为 `/path/to/wechatpay/inWechatpaySerial.pem`
        $platformPublicKeyInstance = Rsa::from('file://'. WechatPayService::getConfig('certPemPath'), Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许2小时之内的偏移
        $timeOffsetStatus = 7200 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
            // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        if ($timeOffsetStatus && $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray = (array)json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array)json_decode($inBodyResource, true);
            // var_dump($inBodyResourceArray);// 打印解密后的结果
            return $inBodyResourceArray;
        }
        return [];
    }
}