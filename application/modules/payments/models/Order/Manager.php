<?php
class Payments_Model_Order_Manager extends Core_Model_Manager
{

    /**
     * Create order
     *
     * @param int $userId
     * @param float $amount
     * @return Payments_Model_Order
     */
    public function createOrder($userId, $amount)
    {
        $order = $this->getDbTable()->createRow();
        $order->status = Payments_Model_Order::ORDER_STATUS_WAITING;
        $order->created = date('Y-m-d H:i:s');
        $order->userId = $userId;
        $order->payment = $amount;
        $order->save();
        return $order;
    }


    /**
     * @param int $orderId
     * @param int $userId
     * @param float $amount
     * @param string $txnId
     * @return bool
     */
    public function payOrder($orderId, $userId, $amount, $txnId)
    {
        $select = $this->getDbTable()
            ->select()
            ->where('id = ?', $orderId)
            ->where('userId = ?', $userId)
            ->where('payment = ?', $amount)
            ->where('status = ?', Payments_Model_Order::ORDER_STATUS_WAITING);
        $order = $this->getDbTable()->fetchRow($select);
        if ($order) {
            $order->status = Payments_Model_Order::ORDER_STATUS_COMPLETE;
            $order->transactionId = $txnId;
            $order->paidDate = date('Y-m-d H:i:s');
            $order->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function validateAndPayOrder($params)
    {
        $paypalConfig = false;
        if (Zend_Registry::isRegistered('payments')) {
            $payments = Zend_Registry::get('payments');
            if (isset($payments['paypal']) && $payments['paypal']) {
                $paypalConfig = $payments['paypal'];
            }
        }

        if (!$paypalConfig) {
            if (Zend_Registry::isRegistered('Log')) {
                $log = Zend_Registry::get('Log');
                $log->log("PayPal is not configured.", Zend_Log::CRIT);
            }
            throw new Exception("PayPal is not configured.");
        }

        //check POST data
        if ($this->isCorrectPostParams($params, $paypalConfig)) {
            //Format of custom field
            //{'type'}-{$orderId}-{$userId}-{$planId}
            $customParam = $params['custom'];
            $subscrId = $params['subscr_id'];
            $amount = $params['mc_gross'];
            $txnType = $params['txn_type'];
            $txnId = $params['txn_id'];

            try {
                list($orderType, $orderId, $userId) = explode('-', $customParam);
            } catch (Exception $ex) {
                if (Zend_Registry::isRegistered('Log')) {
                    $log = Zend_Registry::get('Log');
                    $log->log("Incorrect format in PayPal custom param: " . var_export($customParam, true), Zend_Log::WARN);
                }
                return false;
            }

            if ($orderType && $orderId && $userId) {

                if ($txnType === 'subscr_cancel' && $subscrId) {
                    //Cancel subscription
                    if ($orderType === Payments_Model_Order::ORDER_TYPE_SUBSCRIPTION) {
                        $subscriptionManager = new Subscriptions_Model_Subscription_Manager();
                        return $subscriptionManager->cancelSubscriptionByPaypalCustomParam($customParam, $subscrId);
                    } else {
                        //TBD
                        //For other types of order.
                    }

                } elseif ($amount && $txnId) {
                    //Create order
                    if ($this->payOrder($orderId, $userId, $amount, $txnId)) {
                        if ($orderType === Payments_Model_Order::ORDER_TYPE_SUBSCRIPTION) {
                            $subscriptionManager = new Subscriptions_Model_Subscription_Manager();
                            if ($subscriptionManager->createSubscriptionByPaypalCustomParam($customParam, $subscrId)) {
                                return true;
                            }
                        }
                    } else {
                        //Error!
                        //Order has been payed or incorrect data in custom field.
                        if (Zend_Registry::isRegistered('Log')) {
                            $log = Zend_Registry::get('Log');
                            $log->log(
                                'Order has been payed or incorrect data in custom field. Params: $orderId = '
                                . $orderId . ', $userId = ' . $userId . ', $amount = ' . $amount . ', $txnId = '
                                . $txnId . '.', Zend_Log::WARN
                            );
                        }
                    }
                }

            } else {
                //Error!
                //Incorrect data in custom field.
                if (Zend_Registry::isRegistered('Log')) {
                    $log = Zend_Registry::get('Log');
                    $log->log("Incorrect data in PayPal custom param: " . var_export($customParam, true), Zend_Log::WARN);
                }
            }
        }

        return false;
    }


    /**
     * Validate POST data
     *
     * @param array $params
     * @param array $paypalConfig
     * @return bool
     */
    public function isCorrectPostParams($params, $paypalConfig)
    {
        $client = new Zend_Http_Client();
        $client->setMethod('POST');

        foreach ($params as $name => $value) {
            $client->setParameterPost($name, $value);
        }

        $client->setParameterPost('cmd', '_notify-validate');
        $response = $client->setUri($paypalConfig['paypalHost'] . 'cgi-bin/webscr')->request();

        if ($response->getBody() == 'VERIFIED') {
            return true;
        } else {
            if (Zend_Registry::isRegistered('Log')) {
                $log = Zend_Registry::get('Log');
                $log->log("PayPal returned: " . $response->getBody(), Zend_Log::WARN);
            }
            return false;
        }
    }

}
