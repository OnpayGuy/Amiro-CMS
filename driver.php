<?php
/**
 * OnPay.ru payment system driver.
 *
 * <lang:ru>
 * Драйвер для платёжной системы OnPay.
 * </lang:ru>
 *
 * @copyright atonator.com. All rights reserved.
 * @category  ATO
 * @package   Driver_PaymentSystem
 * @version   1.0
 */

/**
 * OnPay.ru payment system driver.
 *
 * @package Driver_PaymentSystem
 */
class AtoOnpay_PaymentSystemDriver extends AMI_PaymentSystemDriver{
    /**#@+
     * Internal error code
     *
     * <lang:ru>
     * Внутренний код ошибки, возвращаемый драйвером в Amiro.CMS.
     * </lang:ru>
     */

    const ERROR_MISSING_OBLIGATORY_PARAMETER = 1;
    const ERROR_RUR_ONLY = 2;
    const ERROR_UNSUPPORTED_CURRENCY = 3;

    /**#@-*/

    /**
     * Driver name
     *
     * @var string
     */
    protected $driverName = 'ato_onpay';

    /**
     * Obligatory parameters
     *
     * @var array
     */
    private $_obligatoryParams =
        array(
            'onpay_login',
            'onpay_secret_key'
        );

    /**
     * Get checkout button HTML form.
     *
     * <lang:ru>
     * Метод, проверяющий допустимость отображения кнопки для совершения
     * оплаты через драйвер и подготавливающий данные для следующего шага.
     * </lang:ru>
     *
     * @param  array &$aRes         Will contain "error" (error description,
     *                              'Success by default') and "errno"
     *                              (error code, 0 by default). "forms" will
     *                              contain a created form
     * @param  array $aData         The data list for button generation
     * @param  bool  $bAutoRedirect If form autosubmit required
     *                              (directly from checkout page)
     * @return bool  True if form is generated, false otherwise
     */
    public function getPayButton(&$aRes, $aData, $bAutoRedirect = false)
    {
        // Format fields
        foreach (array('return', 'description') as $k){
            $aData[$fldName] = htmlspecialchars($aData[$k]);
        }
        $currency = $aData['driver_currency'];
        switch ((int)$aData['onpay_payment']) {
            case 0:
                // RUR currency only
                if ($currency != 'RUR') {
                $aRes['error'] = 'Only RUR currency is supported';
                $aRes['errno'] = self::ERROR_RUR_ONLY;
                    return false;
                }
                break;
            case 2:
                // Supported currence only
                if (!isset($aData['onpay_payment_' . $currency])) {
                    $aRes['error'] = 'Unsupported currency ' . $currency;
                    $aRes['errno'] = self::ERROR_UNSUPPORTED_CURRENCY;
                    return false;
                }
                break;
        }

        // Wipe fields starting with 'onpay_' from form hidden fields
        foreach (array_keys($aData) as $key) {
            if (mb_strpos($key, 'onpay_') === 0) {
                unset($aData[$key]);
            }
        }
        // Wipe exclusions from form hidden fields
        $aEclusion =
            array(
                'return',
                'cancel',
                'callback',
                'pay_to_email',
                'amount',
                'currency',
                'description_title',
                'description',
                'order',
                'button_name',
                'button'
            );
        // Fill form hidden fields
        $hiddens = '';
        foreach ($aData as $key => $value) {
            if (!in_array($key, $aEclusion)) {
                $hiddens .=
                    '<input type="hidden" name="' . $key .
                    '" value="' . (is_null($value) ? $aData[$key] : $value) .
                    '" />' . "\n";
            }
        }
        $aData['hiddens'] = $hiddens;

        return parent::getPayButton($aRes, $aData, $bAutoRedirect);
    }

    /**
     * Get the form that will be autosubmitted to payment system.
     * This step is required for some shooping cart actions.
     *
     * <lang:ru>
     * Метод, проверяющий допустимость передачи данных платёжной системе,
     * по необходимости конвертирующий сумму заказа в необходимую валюту и
     * подготавливающий данные для формы, которая будет автоматически
     * отправлена в платёжную систему.
     * </lang:ru>
     *
     * @param  array $aData  The data list for button generation
     * @param  array &$aRes  Will contain "error" (error description,
     *                       'Success by default') and "errno"
     *                       (error code, 0 by default). "forms" will contain
     *                       a created form
     * @return bool  True if form is generated, false otherwise
     */
    public function getPayButtonParams($aData, &$aRes)
    {
        // Check parameters and set fields

        /**
         * @var AMI_Response
         */
        $oResponse = AMI::getSingleton('response');
        $oResponse->HTTP->setCookie('ato_onpay_order', NULL);

        // Check obligatory parameters
        foreach ($this->_obligatoryParams as $key) {
            if(empty($aData[$key])){
                $aRes['errno'] = self::ERROR_MISSING_OBLIGATORY_PARAMETER;
                $aRes['error'] = 'Obligatory parameter "' . $key . ' is missed';
                return false;
            }
        }

        // Set defaults
        $aData += array(
            'onpay_convert'     => false,
            'onpay_price_final' => false,
            'onpay_direct_no'   => false,
            'onpay_payment'     => 0,
            'onpay_one_way'     => false
        );
        $aData['onpay_convert'] = $aData['onpay_convert'] ? 'yes' : 'no';
        $aData['onpay_price_final'] = $aData['onpay_price_final'] ? 'true' : '';
        $aData['onpay_direct_no'] = $aData['onpay_direct_no'] ? 'true' : '';

        // Check currency settings {

        $currency = $aData['driver_currency'];
        switch ((int)$aData['onpay_payment']) {
            case 0:
                // RUR currency only
                if ($currency != 'RUR') {
                    $aRes['error'] = 'Only RUR currency is supported';
                    $aRes['errno'] = self::ERROR_RUR_ONLY;
                    return false;
                }
                break;
            case 1:
                // Convert any order currency to RUR
                if ($currency != 'RUR') {
                    $aData['amount'] =
                        number_format(
                            $GLOBALS['oEshop']
                                ->convertCurrency(
                                    $aData['amount'],
                                    $aData['driver_currency'],
                                    'RUR'
                                ),
                            2,
                            '.',
                            ''
                        );
                    $aData['driver_currency'] = 'RUR';
                }
                break;
            case 2:
                // Supported currence only
                if (isset($aData['onpay_payment_' . $currency])) {
                    $aData['driver_currency'] = 'RUR';
                    $aData['onpay_one_way'] = $aData['onpay_payment_' . $currency];
                } else {
                    $aRes['error'] = 'Currency (' . $currency . ') is not supported';
                    $aRes['errno'] = self::ERROR_UNSUPPORTED_CURRENCY;
                    return false;
                }
                break;
        }

        // } Check currency settings

        $aRes['errno'] = 0;
        $aRes['error'] = 'Success';

        // Sign
        // pay_mode;price;currency;pay_for;convert;secret_key
        $aData['md5'] = md5(
            'fix;' .
            number_format($aData['amount'], 1, '.', '') . ';' .
            $aData['driver_currency'] . ';' .
            $aData['order'] . ';' .
            $aData['onpay_convert'] . ';' .
            $aData['onpay_secret_key']
        );

        $aData['url_success'] = $GLOBALS['ROOT_PATH_WWW'] . 'ato_onpay_success.php';
        $aData['url_fail'] = $GLOBALS['ROOT_PATH_WWW'] . 'ato_onpay_fail.php';

        // In 5.12.4 Amiro session is buggly, using cookie to pass data for
        // ato_onpay_success.php / ato_onpay_fail.php scripts
        $data =
            base64_encode(
                gzcompress(
                    serialize(
                        array(
                            'id'      => $aData['order'],
                            'success' => $aData['return'],
                            'fail'    => $aData['cancel']
                        )
                    ),
                    9
                )
            );
        $oResponse->HTTP->setCookie(
            'ato_onpay_order',
            $data . '|' .
            md5($data . 'ato_onpay' . $aData['onpay_secret_key'])
        );

        return parent::getPayButtonParams($aData, $aRes);
    }

    /**
     * Verify the order from user back link.
     * In success case 'accepted' status will be setup for order.
     *
     * <lang:ru>
     * Метод, проверяющий валидность обратных ссылок, на которые передаёт
     * управление платёжная система.
     *
     * Все проверки на текущий момент делаются в скриптах, находящихся в корне
     * сайта и методе AtoOnpay_PaymentSystemDriver::payCallback().
     * </lang:ru>
     *
     * @param  array $aGet        HTTP-GET data
     * @param  array $aPost       HTTP-POST data
     * @param  array &$aRes       Reserved array reference
     * @param  array $aCheckData  Data that provided in driver configuration
     * @param  array $aOrderData  Order data that contains such fields as id, total, order_date, status
     * @return bool  True if order is correct and false otherwise
     * @see AMI_PaymentSystemDriver::payProcess()
     */
    public function payProcess($aGet, $aPost, &$aRes, $aCheckData, $aOrderData)
    {
        $aRes['errno'] = 0;
        $aRes['error'] = 'Success';

        return parent::payProcess($aGet, $aPost, $aRes, $aCheckData, $aOrderData);
    }

    /**
     * Verify the order by payment system background responce.
     * In success case 'confirmed' status will be setup for order.
     *
     * <lang:ru>
     * Метод, проверяющий валидность данных о подтверждении платежа
     * (номер заказа, сумма, подпись), переданные из сркипта
     * ato_onpay_callback.php.
     * </lang:ru>
     *
     * @param  array $aGet        HTTP-GET data
     * @param  array $aPost       HTTP-POST data
     * @param  array &$aRes       Reserved array reference
     * @param  array $aCheckData  Data that provided in driver configuration
     * @param  array $aOrderData  Order data that contains such fields as id, total, order_date, status
     * @return int   -1 - ignore post, 0 - reject(cancel) order, 1 - confirm order
     * @see AMI_PaymentSystemDriver::payCallback()
     */
    public function payCallback($aGet, $aPost, &$aRes, $aCheckData, $aOrderData)
    {
        /**
         * @var AMI_Response
         */
        $response = AMI::getSingleton('response');

        // Check internal obligatory parameters in
        // ato_onpay_callback.php HTTP ttp request context

        foreach (array('order_id', 'order_amount', 'sign') as $param) {
            if (empty($aGet[$param])) {
                $response->start()->write('ato_onpay[1]');
                return -1;
            }
        }
        $sign = md5(
            $aGet['order_id'] .
            'ato_onpay' .
            $aGet['order_amount'] .
            $aCheckData['onpay_secret_key']
        );
        if ($aGet['sign'] != $sign) {
            $response->start()->write('ato_onpay[2]');
            return -1;
        }
        $response->start()->write('ato_onpay[0]');
        return 1;
    }

    /**
     * Return real system order id from data that provided by payment system.
     *
     * <lang:ru>
     * Метод, вызываемый для определения номера заказа внутри Amiro.CMS по
     * данным, переданным из платёжной системы.
     * До версии Amiro.CMS 5.12.8.0 метод не вызывается.
     * </lang:ru>
     *
     * @param  array $aGet               HTTP-GET data
     * @param  array $aPost              HTTP-POST data
     * @param  array &$aRes              Rreserved array reference
     * @param  array $aAdditionalParams  Reserved array
     * @return int   Order Id
     * @see AMI_PaymentSystemDriver::getProcessOrder()
     */
    public function getProcessOrder($aGet, $aPost, &$aRes, $aAdditionalParams)
    {
        $aRes['error'] = 'Success';
        $aRes['errno'] = 0;

        return parent::getProcessOrder($aGet, $aPost, $aRes, $aAdditionalParams);
    }
}
