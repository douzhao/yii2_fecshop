<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */
namespace fecshop\app\appserver\modules\Checkout\controllers;
use fecshop\app\appserver\modules\AppserverController;
use Yii;
/**
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class CartController extends AppserverController
{
    public $enableCsrfValidation = false;
    public function actionIndex()
    {
        $data = $this->getBlock()->getLastData();
        return $this->render($this->action->id, $data);
    }
    /**
     * �Ѳ�Ʒ���뵽���ﳵ.
     */
    public function actionAdd()
    {
        //echo 1;exit;
        $custom_option = Yii::$app->request->get('custom_option');
        $product_id = Yii::$app->request->get('product_id');
        $qty = Yii::$app->request->get('qty');
        //$custom_option  = \Yii::$service->helper->htmlEncode($custom_option);
        $product_id = \Yii::$service->helper->htmlEncode($product_id);
        $qty = \Yii::$service->helper->htmlEncode($qty);
        $qty = abs(ceil((int) $qty));
        $return = [];
        $code = 400;
        if ($qty && $product_id) {
            if ($custom_option) {
                $custom_option_sku = json_decode($custom_option, true);
            }
            if (empty($custom_option_sku)) {
                $custom_option_sku = null;
            }
            $item = [
                'product_id' => $product_id,
                'qty'        =>  $qty,
                'custom_option_sku' => $custom_option_sku,
            ];
            $innerTransaction = Yii::$app->db->beginTransaction();
            try {
                $addToCart = Yii::$service->cart->addProductToCart($item);
                if ($addToCart) {
                    $return = [
                        'status' => 'success',
                        'items_count' => Yii::$service->cart->quote->getCartItemCount(),
                    ];
                    $code = 200;
                    $innerTransaction->commit();
                } else {
                    $errors = Yii::$service->helper->errors->get(',');
                    $return = [
                        'status' => 'fail',
                        'content'=> $errors,
                        //'items_count' => Yii::$service->cart->quote->getCartItemCount(),
                    ];
                    $code = 400;
                    $innerTransaction->rollBack();
                }
            } catch (Exception $e) {
                $innerTransaction->rollBack();
            }
        }
        
        return [
            'code'      => $code ,
            'content'   => $return ,
        ];
    }
    /**
     * ���ﳵ�������Ż�ȯ.
     */
    public function actionAddcoupon()
    {
        if (Yii::$app->user->isGuest) {
            // ����һ�µ�¼�ɹ����ع��ﳵҳ��
            $cartUrl = Yii::$service->url->getUrl('checkout/cart');
            Yii::$service->customer->setLoginSuccessRedirectUrl($cartUrl);
            echo json_encode([
                'status' => 'fail',
                'content'=> 'nologin',
            ]);
            exit;
        }
        $coupon_code = trim(Yii::$app->request->post('coupon_code'));
        $coupon_code = \Yii::$service->helper->htmlEncode($coupon_code);
        if ($coupon_code) {
            $innerTransaction = Yii::$app->db->beginTransaction();
            try {
                if (Yii::$service->cart->coupon->addCoupon($coupon_code)) {
                    $innerTransaction->commit();
                } else {
                    $innerTransaction->rollBack();
                }
            } catch (Exception $e) {
                $innerTransaction->rollBack();
            }
            $error_arr = Yii::$service->helper->errors->get(true);
            if (!empty($error_arr)) {
                $error_str = implode(',', $error_arr);
                echo json_encode([
                    'status' => 'fail',
                    'content'=> $error_str,
                ]);
                exit;
            } else {
                echo json_encode([
                    'status' => 'success',
                    'content'=> 'add coupon success',
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'status' => 'fail',
                'content'=> 'coupon is empty',
            ]);
            exit;
        }
    }
    /**
     * ���ﳵ��ȡ���Ż�ȯ.
     */
    public function actionCancelcoupon()
    {
        if (Yii::$app->user->isGuest) {
            // ����һ�µ�¼�ɹ����ع��ﳵҳ��
            $cartUrl = Yii::$service->url->getUrl('checkout/cart');
            Yii::$service->customer->setLoginSuccessRedirectUrl($cartUrl);
            echo json_encode([
                'status' => 'fail',
                'content'=> 'nologin',
            ]);
            exit;
        }
        $coupon_code = trim(Yii::$app->request->post('coupon_code'));
        if ($coupon_code) {
            $innerTransaction = Yii::$app->db->beginTransaction();
            try {
                $cancelStatus = Yii::$service->cart->coupon->cancelCoupon($coupon_code);
                if (!$cancelStatus) {
                    echo json_encode([
                        'status' => 'fail',
                        'content'=> 'coupon is not exist;',
                    ]);
                    $innerTransaction->rollBack();
                    exit;
                }
                $error_arr = Yii::$service->helper->errors->get(true);
                if (!empty($error_arr)) {
                    $error_str = implode(',', $error_arr);
                    echo json_encode([
                        'status' => 'fail',
                        'content'=> $error_str,
                    ]);
                    $innerTransaction->rollBack();
                    exit;
                } else {
                    echo json_encode([
                        'status' => 'success',
                        'content'=> 'cacle coupon success',
                    ]);
                    $innerTransaction->commit();
                    exit;
                }
            } catch (Exception $e) {
                $innerTransaction->rollBack();
            }
        } else {
            echo json_encode([
                'status' => 'fail',
                'content'=> 'coupon is empty',
            ]);
            exit;
        }
    }
    public function actionUpdateinfo()
    {
        $item_id = Yii::$app->request->get('item_id');
        $up_type = Yii::$app->request->get('up_type');
        $innerTransaction = Yii::$app->db->beginTransaction();
        try {
            if ($up_type == 'add_one') {
                $status = Yii::$service->cart->addOneItem($item_id);
            } elseif ($up_type == 'less_one') {
                $status = Yii::$service->cart->lessOneItem($item_id);
            } elseif ($up_type == 'remove') {
                $status = Yii::$service->cart->removeItem($item_id);
            }
            if ($status) {
                echo json_encode([
                    'status' => 'success',
                ]);
                $innerTransaction->commit();
            } else {
                echo json_encode([
                    'status' => 'fail',
                ]);
                $innerTransaction->rollBack();
            }
        } catch (Exception $e) {
            $innerTransaction->rollBack();
        }
    }
}