<?php

/**
 * Class for webhook
 */
class Woocommerce_Yow_Webhook
{
    /**
     * Namespace part of webhook url
     *
     * @var string
     */
    const NAMESPACE_PATH = 'yow/v1';
    /**
     * Namespace part of webhook url
     *
     * @var string
     */
    const ROUTE_PATH = '/transaction/webhook';

    /**
     * Add api endpoint for webhook
     *
     * @return void
     */
    public static function register_routs()
    {
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    self::NAMESPACE_PATH,
                    self::ROUTE_PATH,
                    [
                        'methods' => 'POST',
                        'callback' => ['Woocommerce_Yow', 'completeOrder'],
                        'permission_callback' => '__return_true'
                    ]
                );
            }
        );
    }

    public static function getWebhookUrl(): string
    {
        return get_rest_url() . self::NAMESPACE_PATH . self::ROUTE_PATH;
    }
}