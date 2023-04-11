<?php

use Automattic\Jetpack\Constants;

/**
 * Yow Payment gateway class
 */
class Woocommerce_Yow extends WC_Payment_Gateway
{
    /**
     * Payment api base url
     */
    const TRANSACTION_BASE_URL = 'https://yowpay.com/';

    /**
     * Payment api path
     */
    const TRANSACTION_PATH_REQ = 'api/createTransaction';

    /**
     * Payment page path
     */
    const TRANSACTION_PATH_RESP = 'transaction/display/';

    /**
     * update config path
     */
    const TRANSACTION_PATH_CONF = 'api/updateConfig/';

    /**
     * Bank data api path
     */
    const BANK_DATA_PATH = 'api/getBankData';

    /**
     * POST timeout parameter
     */
    const POST_TIMEOUT = 90;

    /**
     * POST sslverify parameter
     */
    const POST_SSLVERIFY = true;

    /**
     * Required params for webhook
     */
    const REQUIRED_WEBHOOK_FIELDS = [
        'orderId',
        'reference',
        'amountPaid',
        'currencyPaid',
        'senderIban',
        'senderSwift',
        'senderAccountHolder'
    ];

    /**
     * Success page slug
     */
    const SUCCESS_PAGE_SLUG = 'yowpay-success-page';

    /**
     * Return payment icon's path
     */
    private string $iconPath = WC_YOW_ASSETS . 'img/handshake-violet-light.svg';

    /**
     * Initialize payment class
     */
    public function __construct()
    {
        $this->id = "woocommerce_yow";
        $this->method_title = __("YowPay Instant SEPA", 'woocommerce-yow-payment');
        $this->method_description = __("Peer to Peer SEPA Payments made easy", 'woocommerce-yow-payment');
        $this->title = __("YowPay Payment", 'woocommerce-yow-payment');
        $this->icon = $this->iconPath;
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        add_filter('woocommerce_available_payment_gateways', [$this, 'checkCurrency']);

        add_action('admin_notices', [$this, 'doSslCheck']);
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }
    }

    /**
     * Disable YowPay payment method if currency isn't the EUR
     *
     * @param $available_gateways
     * @return mixed
     */
    public function checkCurrency($available_gateways)
    {
        if (get_woocommerce_currency() !== 'EUR') {
            unset($available_gateways['woocommerce_yow']);
        }
        return $available_gateways;
    }

    /**
     * {@inheritDoc}
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            // description
            'payment_description' => [
                'type' => 'description_text',
                'text' => __('<p>Welcome to the YowPay plugin ! In a few minutes, your customers will be able to pay with SEPA Instant Transfers !</p><p>To start accepting payments with SEPA Instant Transfer from you customers, you\'ll need to follow three simple steps:</p><ol><li><a href="https://yowpay.com/signup/">Sign up for YowPay</a> if you don\'t have an account already</li><li><a href="https://yowpay.com/account/ecommerce/new">Create your E-commerce website entry</a> in the YowPay admin</li><li>Get your App Token & Secret Key and paste them in the corresponding fields below</li></ol><p>If you\'d like to know more about how to configure this plugin for your needs, check out our documentation.</p>', 'woocommerce-yow-payment'),
                'icon' => WC_YOW_ASSETS . 'img/logo-txt-only.png'
            ],
            'space1' => [
                'px' => 60,
                'type' => 'space'
            ],
            // General Settings
            'general_title' => [
                'title' => __('General Settings', 'woocommerce-yow-payment'),
                'type' => 'title'
            ],
            'enabled' => [
                'title' => __('Enable / Disable', 'woocommerce-yow-payment'),
                'label' => __('Enable YowPay?', 'woocommerce-yow-payment'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('This controls whether or not "Pay with SEPA instant Transfer - by YowPay" is enable in the payment mode list within Woocommerce', 'woocommerce-yow-payment'),
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-yow-payment'),
                'type' => 'text',
                'default' => __('Pay with SEPA Instant transfer - by YowPay', 'woocommerce-yow-payment'),
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-yow-payment'),
            ],
            'description' => [
                'title' => __('Description', 'woocommerce-yow-payment'),
                'type' => 'textarea',
                'default' => __('Pay with SEPA Instant transfer using your banking application.', 'woocommerce-yow-payment'),
                'description' => __('This controls the description which user sees during checkout.', 'woocommerce-yow-payment'),
                'css' => 'width: 400px;',
            ],
            'enabled_image_desc' => [
                'title' => __('Enable / Disable', 'woocommerce-yow-payment'),
                'label' => __('Display full explanation?', 'woocommerce-yow-payment'),
                'type' => 'checkbox_image_desc',
                'default' => '1',
                'description' => __('Display full explanation of the YowPay process with icons in the payment description, during the checkout', 'woocommerce-yow-payment'),
            ],
            'space2' => [
                'px' => 60,
                'type' => 'space'
            ],
            // Production Settings
            'keys_title' => [
                'title' => __('Production Settings', 'woocommerce-yow-payment'),
                'type' => 'title'
            ],
            'api_token' => [
                'title' => __('App Token', 'woocommerce-yow-payment'),
                'type' => 'text',
                'default' => '',
                'description' => __('Enter the App Token created in your YowPay account and related to this E-commerce website', 'woocommerce-yow-payment'),
            ],
            'app_secret_key' => [
                'title' => __('App Secret Key', 'woocommerce-yow-payment'),
                'type' => 'password',
                'default' => '',
                'description' => __('Enter the App Secret created in your YowPay account and related to this E-commerce website', 'woocommerce-yow-payment'),
            ],
            'space3' => [
                'px' => 60,
                'type' => 'space'
            ],
            // Open Banking Connexion
            'bank_title' => [
                'title' => __('Open Banking Connexion', 'woocommerce-yow-payment'),
                'type' => 'title'
            ],
            'bank_data' => [
                'title_account' => __('Account owner', 'woocommerce-yow-payment'),
                'title_iban' => __('IBAN', 'woocommerce-yow-payment'),
                'title_bic_swift' => __('BIC/SWIFT', 'woocommerce-yow-payment'),
                'title_status' => __('Open banking Status', 'woocommerce-yow-payment'),
                'type' => 'bank_data',
                'status_not_provided' => __('NOT CONNECTED', 'woocommerce-yow-payment'),
                'status_ok' => __('CONNECTED', 'woocommerce-yow-payment'),
                'status_expired' => __('EXPIRED', 'woocommerce-yow-payment'),
                'status_lost' => __('LOST', 'woocommerce-yow-payment'),
                'desc_expired' => __('Open Banking Consent expire at ', 'woocommerce-yow-payment'),
                'description' => __('Take care to renew before the expiration date. YowPay use the open banking access to validate payments', 'woocommerce-yow-payment'),
                'button_link' => __('https://yowpay.com/account/banking', 'woocommerce-yow-payment'),
                'button_text' => __('Renew the consent', 'woocommerce-yow-payment'),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function payment_fields() {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }
        if ($this->enabled_image_desc) {
            echo $this->getFullExplanation();
        }
    }

    /**
     * Used in parent class for settings fields
     * Generate Checkbox With Image Description HTML.
     *
     * @param string $key Field key.
     * @param array $data Field data.
     * @return string
     *@since  1.0.0
     */
    public function generate_checkbox_image_desc_html(string $key, array $data): string
    {
        $field_key = $this->get_field_key($key);
        $defaults  = array(
            'title'             => '',
            'label'             => '',
            'class'             => '',
            'css'               => '',
            'type'              => 'text',
            'desc_tip'          => false,
        );

        $data = wp_parse_args($data, $defaults);
        if (!$data['label']) {
            $data['label'] = $data['title'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key);?>">
                    <?php echo wp_kses_post($data['title']);?> <?php echo $this->get_tooltip_html($data);?>
                </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo wp_kses_post($data['title']);?></span>
                    </legend>
                    <label for="<?php echo esc_attr($field_key);?>">
                        <input class="<?php echo esc_attr($data['class']);?>"
                               type="checkbox" name="<?php echo esc_attr($field_key);?>"
                               id="<?php echo esc_attr($field_key);?>"
                               style="<?php echo esc_attr($data['css']);?>"
                               value="1" <?php checked($this->get_option($key), '1');?> />
                        <?php echo wp_kses_post($data['label']);?>
                    </label>
                    <br/>
                    <?php echo $this->get_description_html($data);?>

                    <?php
                    if ($this->get_option($key) === '1') {
                        ?><br/><?php
                        echo $this->getFullExplanation();
                    }
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Return html with content for full explanation which is showing on payment page
     *
     * @return false|string
     */
    private function getFullExplanation()
    {
        $step1 = __('Connect to your banking app', 'woocommerce-yow-payment');
        $step2 = __('Scan the QR Code or enter the payment details manually', 'woocommerce-yow-payment');
        $step3 = __('Validate your transfer', 'woocommerce-yow-payment');

        $image1 = WC_YOW_ASSETS . 'img/black-explain-bank.png';
        $image2 = WC_YOW_ASSETS . 'img/black-explain-qrcode.png';
        $image3 = WC_YOW_ASSETS . 'img/black-explain-ok.png';

        ob_start();
        ?>
        <div class="yow-full-explanation-container">
            <div class="yow-full-explanation-element">
                <img class="yow-full-explanation-element-img" src="<?php echo $image1;?>" alt="">
                <p class="yow-full-explanation-element-p"><?php echo sanitize_text_field(wp_unslash($step1));?></p>
            </div>
            <div class="yow-full-explanation-separator"></div>
            <div class="yow-full-explanation-element">
                <img class="yow-full-explanation-element-img" src="<?php echo $image2;?>" alt="">
                <p class="yow-full-explanation-element-p"><?php echo sanitize_text_field(wp_unslash($step2));?></p>
            </div>
            <div class="yow-full-explanation-separator"></div>
            <div class="yow-full-explanation-element">
                <img class="yow-full-explanation-element-img" src="<?php echo $image3;?>" alt="">
                <p class="yow-full-explanation-element-p"><?php echo sanitize_text_field(wp_unslash($step3));?></p>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Used in parent class for settings fields
     * Generate empty row with height (just space).
     *
     * @param string $key Field key.
     * @param array $data Field data.
     * @return string
     */
    public function generate_space_html(string $key, array $data): string
    {
        $defaults = [
            'px' => 50,
        ];
        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top" style="height: <?php echo $data['px'] . 'px';?>">
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Used in parent class for settings fields
     * Generate Description Text.
     *
     * @param string $key Field key.
     * @param array $data Field data.
     * @return string
     */
    public function generate_description_text_html(string $key, array $data): string
    {
        $defaults = [
            'icon' => $this->iconPath,
            'text' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc yow-desc-img">
                <img src="<?php echo $data['icon'];?>" width="200px" alt="icon">
            </th>
            <td class="forminp">
                <div>
                    <?php echo $data['text'];?>
                </div>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Used in parent class for settings fields
     * Generate Disabled Text Input HTML.
     *
     * @param string $key Field key.
     * @param array $data Field data.
     * @return string
     */
    public function generate_bank_data_html(string $key, array $data): string
    {
        $defaults = [
            'title_account' => '',
            'title_iban' => '',
            'title_bic_swift' => '',
            'title_status' => '',
            'status_not_provided' => '',
            'status_ok' => '',
            'status_expired' => '',
            'status_lost' => '',
            'desc_expired' => '',
            'description' => '',
            'button_link' => '',
            'button_text' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        $bankData = $this->getBankData();

        ob_start();
        if (empty($bankData)) {
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc"></th>
                <td class="forminp">
                    <div>
                        <?php echo __('No Bank data was provided, please, go to your yowpay account and add it.', 'woocommerce-yow-payment');?>
                    </div>
                </td>
            </tr>
            <?php
        } else {
            ?>
            <tr class="yow-bank-tr" valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo $data['title_account'];?></label>
                </th>
                <td class="forminp">
                    <div>
                        <?php echo sanitize_text_field(wp_unslash($bankData['accountHolder'] ?? ''));?>
                    </div>
                </td>
            </tr>
            <tr class="yow-bank-tr" valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo $data['title_iban'];?></label>
                </th>
                <td class="forminp">
                    <div>
                        <?php echo sanitize_text_field(wp_unslash($bankData['iban'] ?? ''));?>
                    </div>
                </td>
            </tr>
            <tr class="yow-bank-tr" valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo $data['title_bic_swift'];?></label>
                </th>
                <td class="forminp">
                    <div>
                        <?php echo sanitize_text_field(wp_unslash($bankData['swift'] ?? ''));?>
                    </div>
                </td>
            </tr>
            <tr class="yow-bank-tr" valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo $data['title_status'];?></label>
                </th>
                <td class="forminp">
                    <?php
                        $statusCode = (int)htmlspecialchars($bankData['statusCode'] ?? 0);
                        $expirationTime = sanitize_text_field(wp_unslash($bankData['consentExpirationTime'] ?? ''));
                        $originalTimezone = date_default_timezone_get();
                        date_default_timezone_set('UTC');
                        $dateTimestamp = strtotime($expirationTime);
                        $date = date("Y-m-d", $dateTimestamp);
                        date_default_timezone_set($originalTimezone);
                    ?>
                    <div class="bank-status-wrap">
                        <?php switch ($statusCode) {
                            case 0:?>
                                <img src="<?php echo WC_YOW_ASSETS . 'img/not_ok_icon.png';?>" width="25px" alt="icon">
                                <span class="bank-status-not-ok"><?php echo $data['status_not_provided'];?></span>
                                <?php break;
                            case 1:?>
                                <img src="<?php echo WC_YOW_ASSETS . 'img/ok_icon.png';?>" width="25px" alt="icon">
                                <span class="bank-status-ok"><?php echo $data['status_ok'];?></span>
                                <?php break;
                            case 2:?>
                                <img src="<?php echo WC_YOW_ASSETS . 'img/not_ok_icon.png';?>" width="25px" alt="icon">
                                <span class="bank-status-not-ok"><?php echo $data['status_expired'];?></span>
                                <?php break;
                            case 3:?>
                                <img src="<?php echo WC_YOW_ASSETS . 'img/not_ok_icon.png';?>" width="25px" alt="icon">
                                <span class="bank-status-not-ok"><?php echo $data['status_lost'];?></span>
                        <?php }?>
                    </div>
                    <p>
                        <?php echo $data['desc_expired'] . $date;?>
                    </p>
                    <p>
                        <?php echo $data['description'];?>
                    </p>
                    <br/>
                    <a href="<?php echo $data['button_link'];?>"
                       class="button-primary woocommerce-save-button"
                       target="_blank">
                        <?php echo $data['button_text'];?>
                    </a>
                </td>
            </tr>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Return Bank data by api
     *
     * @return array
     */
    private function getBankData(): array
    {
        $url = self::TRANSACTION_BASE_URL . self::BANK_DATA_PATH;
        $timestamp = time();

        $payload = ['timestamp' => $timestamp];
        $headers = [
            'X-App-Access-Ts' => $timestamp,
            'X-App-Token' => $this->api_token,
            'X-App-Access-Sig' => $this->createHash($payload),
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => self::POST_TIMEOUT,
            'sslverify' => self::POST_SSLVERIFY,
        ]);

        if (is_wp_error($response)) {
            addErrorLog($response->get_error_message());
            return [];
        }

        if (!$response || !isset($response['response']['code']) || $response['response']['code'] !== 200) {
            $msg = 'There is issue for connection Bank Data';
            addErrorLog($msg);
            return [];
        }

        if (empty($response['body'])) {
            $msg = "Response from Bank Data hasn't body.";
            addErrorLog($msg);
            return [];
        }

        $body = json_decode($response['body'], true);

        return $body['content'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function process_admin_options(): bool
    {
        if (!parent::process_admin_options()) {
            return false;
        }

        $url = self::TRANSACTION_BASE_URL . self::TRANSACTION_PATH_CONF;
        $timestamp = time();
        $payload = [
            'returnUrl' => get_permalink(get_page_by_path(self::SUCCESS_PAGE_SLUG)),
            'cancelUrl' => wc_get_checkout_url(),
            'webhookUrl' => Woocommerce_Yow_Webhook::getWebhookUrl(),
            'timestamp' => $timestamp
        ];

        $headers = [
            'X-App-Access-Ts' => $timestamp,
            'X-App-Token' => $this->getParameter('api_token'),
            'X-App-Access-Sig' => $this->createHash($payload),
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => self::POST_TIMEOUT,
            'sslverify' => self::POST_SSLVERIFY,
        ]);

        if (is_wp_error($response)) {
            addErrorLog($response->get_error_message());
            return false;
        }

        if ($response['response']['code'] !== 200) {
            $msg = 'Failed to update config. Message: ' . $response['response']['message'];
            $msg .= ', POST data: ' . json_encode($payload) . ', headers: ' . json_encode($headers);
            addErrorLog($msg);
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $url = self::TRANSACTION_BASE_URL . self::TRANSACTION_PATH_REQ;
        $timestamp = time();
        $payload = $this->createPayload($order, $timestamp);

        $headers = [
            'X-App-Access-Ts' => $timestamp,
            'X-App-Token' => $this->api_token,
            'X-App-Access-Sig' => $this->createHash($payload),
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => self::POST_TIMEOUT,
            'sslverify' => self::POST_SSLVERIFY,
        ]);

        if (is_wp_error($response)) {
            addErrorLog($response->get_error_message());
            return [];
        }

        if (
            isset($response['http_response']) && !empty($response['http_response']) &&
            $response['http_response'] instanceof WP_HTTP_Requests_Response
        ) {
            $url = $response['http_response']->get_response_object()->url;
            $needle = self::TRANSACTION_BASE_URL . self::TRANSACTION_PATH_RESP;
            if (!empty($url) && strpos($url, $needle) !== false) {
                $transaction = explode('/', str_replace($needle, '', $url));

                $originalTimezone = date_default_timezone_get();
                date_default_timezone_set('UTC');
                $transactionData = [
                    'order_id' => $order_id,
                    'transaction_id' => $transaction[0],
                    'transaction_code' => $transaction[1] ?? '',
                    'price' => (float)$payload['amount'],
                    'currency' => $payload['currency'],
                    'created_at' => date("Y-m-d H:i:s", $timestamp),
                    'status' => Woocommerce_Yow_Transactions_Table::STATUS_PENDING,
                ];
                date_default_timezone_set($originalTimezone);

                Woocommerce_Yow_Db::insertTransaction($transactionData);

                return [
                    'result'   => 'success',
                    'redirect' => $url,
                ];
            } else {
                wc_add_notice(
                    __('Response from payment gateway is not processable.', 'woocommerce-yow-payment'),
                    'error'
                );
                $msg = "Response from Yow Payment has invalid url: $url";
                addErrorLog($msg);
            }
        } else {
            wc_add_notice(
                __('There is issue for connection payment gateway. Sorry for the inconvenience.', 'woocommerce-yow-payment'),
                'error'
            );
            $msg = "Response from Yow Payment has invalid body: " . json_encode($response);
            addErrorLog($msg);
        }
        return [];
    }

    /**
     * Function for webhook. Execute when Yow Payment send transaction confirmation
     *
     * @return string
     */
    public static function completeOrder(): string
    {

        $post = file_get_contents('php://input');

        if ($post) {
            $post = json_decode($post, true);
        } else {
            addErrorLog("Webhook didn't get POST data");
            return '';
        }

        if (!self::checkPostData($post)) {
            return '';
        }

        $transactionsData = self::getTransactionsData($post);
        if (!$transactionsData) {
            return '';
        }

        if ($transactionsData['orderSum'] >= $transactionsData['totalPrice']) {
            $order = new WC_Order($post['orderId']);
            $order->payment_complete();
        }

        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $transactionData = [
            'updated_at' => date("Y-m-d H:i:s", $post['timestamp']),
            'status' => self::getTransactionStatus($transactionsData['transactionSum'], $transactionsData['totalPrice']),
            'paid' => $transactionsData['paid'],
            'paid_currency' => $post['currencyPaid'],
            'sender_iban' => $post['senderIban'] ?? '',
            'sender_swift' => $post['senderSwift'] ?? '',
            'sender_account_holder' => $post['senderAccountHolder'] ?? ''
        ];
        date_default_timezone_set($originalTimezone);

        $transactionWhere = [
            'order_id' => $post['orderId'],
            'transaction_code' => $post['reference']
        ];

        if (Woocommerce_Yow_Db::updateTransaction($transactionData, $transactionWhere)) {
            return 'ok'; // should be returned otherwise Yow Pay will send duplicated messages
        }
        return '';
    }

    /**
     * Success page will be created if it doesn't exist
     *
     * @return void
     */
    public static function createSuccessPage()
    {
        if (get_page_by_path(self::SUCCESS_PAGE_SLUG)) {
            return;
        }

        global $wp_rewrite;
        if (!$wp_rewrite) {
            $wp_rewrite = new wp_rewrite;
        }

        $postTitle = __('YowPay success Page', 'woocommerce-yow-payment');
        $img = WC_YOW_ASSETS . 'img/logo-txt-only.png';
        $successText = __('Your order will be validated soon automatically!', 'woocommerce-yow-payment');
        $buttonText1 = __('Continue Shopping', 'woocommerce-yow-payment');
        $buttonUrl1 = get_home_url();
        $buttonText2 = __('Go to order list', 'woocommerce-yow-payment');
        $buttonUrl2 = wc_get_account_endpoint_url('orders');

        ob_start();
        ?>
        <div class="yow-success-page">
            <div class="yow-success-page-img-wrap">
                <img src="<?php echo $img;?>" class="yow-success-page-img" alt="<?php echo $postTitle;?>">
            </div>
            <div class="yow-success-page-text-wrap">
                <p class="yow-success-page-text"><?php echo $successText;?></p>
            </div>
            <div class="yow-success-page-button-wrap"><a href="<?php echo $buttonUrl1;?>" class="yow-success-page-button button alt wp-element-button"><?php echo $buttonText1;?></a><a href="<?php echo $buttonUrl2;?>" class="yow-success-page-button button alt wp-element-button"><?php echo $buttonText2;?></a>
            </div></div>
        <?php
        $content = ob_get_clean();
        $newPage = array(
            'post_type' => 'page',
            'post_title' => $postTitle,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_author' => 1,
            'post_name' => self::SUCCESS_PAGE_SLUG
        );

        wp_insert_post($newPage);
    }

    /**
     * Check post data's timestamp and required fields
     *
     * @param array $post
     * @return bool
     */
    private static function checkPostData(array $post): bool
    {
        $settings = get_option('woocommerce_woocommerce_yow_settings', null);
        $sig = hash_hmac('sha256', json_encode($post), $settings['app_secret_key']);
        if (
            !(
                isset($_SERVER['HTTP_X_APP_ACCESS_SIG']) &&
                isset($_SERVER['HTTP_X_APP_ACCESS_TS']) &&
                isset($post['timestamp']) &&
                $_SERVER['HTTP_X_APP_ACCESS_TS'] == $post['timestamp'] &&
                $_SERVER['HTTP_X_APP_ACCESS_SIG'] == $sig &&
                $post['timestamp'] <= time() &&
                $post['timestamp'] > (time() - 15)
            )
        ) {
            $msg = "Webhook got invalid POST data: " . json_encode($post) . "or Headers: " . json_encode($_SERVER);
            addErrorLog($msg);
            return false;
        }

        $missedFields = [];
        foreach (self::REQUIRED_WEBHOOK_FIELDS as $requiredWebhookParam) {
            if (!isset($post[$requiredWebhookParam])) {
                $missedFields[] = $requiredWebhookParam;
            }
        }
        if (!empty($missedFields)) {
            $msg = "POST data hadn't required fields: " . implode(', ', $missedFields);
            addErrorLog($msg);
            return false;
        }

        return true;
    }

    /**
     * Generate data for completeOrder() process
     *
     * @param array $post
     * @return array|float[]
     */
    private static function getTransactionsData(array $post): array
    {
        $transactions = Woocommerce_Yow_Db::getTransactionsByOrderId($post['orderId']);

        if (!$transactions) {
            $msg = "Transactions didn't register for order with id: " . $post['orderId'];
            addErrorLog($msg);
            return [];
        }

        $stopProcess = true;
        $orderSum = 0.00;
        $transactionSum = 0.00;
        $totalPrice = 0.00;

        foreach ($transactions as $item) {
            $orderSum += (float)$item['paid'];
            if ((string)$item['transaction_code'] === (string)$post['reference']) {
                $stopProcess = false;
                $transactionSum += (float)$item['paid'];
                $totalPrice = (float)$item['price'];
            }
        }

        if ($stopProcess) {
            $msg = "Transaction with code: " . $post['orderId'] . " didn't register for order with id: " . $post['orderId'];
            addErrorLog($msg);
            return [];
        }

        $paid = (float)str_replace('_', '.', $post['amountPaid']);
        $orderSum += $paid;
        $transactionSum += $paid;

        return [
            'orderSum' => $orderSum,
            'transactionSum' => $transactionSum,
            'totalPrice' => $totalPrice,
            'paid' => $paid
        ];
    }

    /**
     * Return status for transaction by paid price
     *
     * @param float $paid
     * @param float $price
     * @return int
     */
    private static function getTransactionStatus(float $paid, float $price): int
    {
        switch ($paid <=> $price) {
            case -1:
                return Woocommerce_Yow_Transactions_Table::STATUS_PARTIALLY_PAID;
            case 1:
                return Woocommerce_Yow_Transactions_Table::STATUS_OVERPAID;
            default:
                return Woocommerce_Yow_Transactions_Table::STATUS_APPROVED;
        }
    }

    /**
     * Create body for payment api
     * @param WC_Order $order
     * @param int $timestamp Must be the same as
     * @return array
     */
    private function createPayload(WC_Order $order, int $timestamp): array
    {
        return [
            'amount' => $order->order_total,
            'currency' => $order->get_currency(),
            'timestamp' => $timestamp,
            'orderId' => $order->get_id(),
            'language' => $this->getLanguage2letterCode(),
        ];
    }

    /**
     * Create hash for header X-App-Access-Sig
     * @param array $payload
     * @return false|string
     */
    private function createHash(array $payload)
    {
        return hash_hmac('sha256', json_encode($payload), $this->getParameter('app_secret_key'));
    }

    /**
     * return 2 letter language code
     * @return string
     */
    private function getLanguage2letterCode(): string
    {
        $code = explode('_', get_locale());
        return $code[0];
    }

    /**
     * Return parameter even if it has just installed
     *
     * @param $name
     * @return mixed|string
     */
    private function getParameter($name)
    {
        if (isset($this->settings[$name])) {
            return $this->settings[$name];
        }
        if ($this->$name) {
            return $this->$name;
        }
        return '';
    }

    /**
     * @return void
     */
    public function doSslCheck()
    {
        if ($this->enabled === "yes" && get_option('woocommerce_force_ssl_checkout') === "no") {
            echo "<div class=\"error\"><p>" .
                sprintf(
                    __("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"),
                    $this->method_title,
                    admin_url('admin.php?page=wc-settings&tab=checkout')
                ) .
                "</p></div>";
        }
    }
}