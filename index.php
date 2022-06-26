<?php

use services\TradeService;

require_once 'vendor/autoload.php';
require_once 'services/TradeService.php';
require_once 'services/WechatPayService.php';

ini_set('display_errors', 'On');


class Test {

    /**
     * 支付
     * 
     */
    public function pay(string $orderNumber, string $description = '', int $total = 0, array $attach = []) {
        $result = (new TradeService)->nativePay(
            $orderNumber, 
            $description, 
            $total, 
            $attach, 
            'https://ab.com/payNotify'
        );
        echo "<pre>";
        print_r($result);
        die;
    }

    /**
     * 支付回调
     * 
     */
    public function payNotify() {

        // 获取消息体
        $data = file_get_contents('php://input');
        // 获取 header 头【可根据框架写法进行调整】
        $header = [
            'wechatpay-signature' => $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ?? '',
            'wechatpay-timestamp' => $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ?? '',
            'wechatpay-serial' => $_SERVER['HTTP_WECHATPAY_SERIAL'] ?? '',
            'wechatpay-nonce' => $_SERVER['HTTP_WECHATPAY_NONCE'] ?? ''
        ];

        // 解密数据
        $content = (new TradeService)->decryptNotifyData($header, $data);

        // 将回调数据写入文件，方便开发
        // file_put_contents('log/payNotify.txt', print_r([
        //     'time' => date('Y-m-d H:i:s'),
        //     'header' => $header,
        //     'data' => $data,
        //     'content' => $content
        // ], true) , FILE_APPEND);
        
        if(!empty($content)) {
            // 处理逻辑
            if($content['trade_state'] == 'SUCCESS') {

                // 解析回调数据
                list($orderNumber, $transactionId, $total, $payerTotal) = [
                    $content['out_trade_no'],
                    $content['transaction_id'],
                    $content['amount']['total'],
                    $content['amount']['payer_total']
                ];
                // 回调逻辑处理结果
                $handleNotifyResult = true;// or false
                if($handleNotifyResult === true) {
                    return json_encode([
                        'code' => 'SUCCESS',
                        'message' => ''
                    ]);
                }

                return json_encode([
                    'code' => 'FAIL',
                    'message' => '失败'
                ]);
            }
        } else {
            // 返回失败
            return json_encode([
                'code' => 'FAIL',
                'message' => '失败'
            ]);
        }
    }

    /**
     * 退款
     * 
     */
    public function refund($orderNumber = '', $payNumber = '', $refundNumber, $refundPrice, $total) {
        $result = (new TradeService)->nativePayRefund(
            $orderNumber,
            $payNumber,
            $refundNumber,
            $refundPrice,
            $total,
            'https://ab.com/refundNotify'
        );

        echo "<pre>";
        print_r($result);
        die;
    }

    /**
     * 退款回调
     * 
     * 此回调方法可参考 payNotify 方法
     */
    public function refundNotify() {

    }

}

$orderNumber = '789987';
$total = 1;

// 发起支付
// (new Test)->pay($orderNumber, 'ovim测试支付', $total, []);

$refundNumber = '0789987';
$refundPrice = 1;
// 发起退款
// (new Test)->refund('', $orderNumber, $refundNumber, $refundPrice, $total);

// 回调测试
// (new Test)->payNotify();