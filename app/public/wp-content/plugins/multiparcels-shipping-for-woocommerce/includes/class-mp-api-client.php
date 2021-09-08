<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Api_Client
 */
class MP_Api_Client
{
    /** @var string */
    private $api_url = 'https://api.multiparcels.com/v1/';

    /**
     * @param $endpoint
     * @param string $method
     * @param array $data
     *
     * @return MP_Api_Client_Response
     */
    public function request($endpoint, $method = 'GET', $data = [])
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . MultiParcels()->options->get_api_key(),
        ];

        if (get_locale() == 'lt_LT' || get_locale() == 'lt') {
            $headers[] = 'Accept-Language: lt';
        }

        $url = $this->api_url . $endpoint;
        $ch  = curl_init();

        if ($method == 'GET') {
            $url = $this->api_url . $endpoint . '?' . http_build_query($data);
        } elseif ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method == 'DELETE') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $protocol = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";

        if ($protocol == 'http') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $output   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ( ! $ch || ! $output) {
            return new MP_Api_Client_Response(null, null, curl_error($ch));
        }

        curl_close($ch);

        return new MP_Api_Client_Response($output, $httpCode);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function request_api_key()
    {
        $response = $this->request('restricted_api', 'POST', [
            'email'  => MultiParcels()->options->get('email'),
            'domain' => get_bloginfo('wpurl'),
        ]);

        if ($response->was_successful()) {
            $data = $response->get_data();
            MultiParcels()->options->set('api_key', $data['api_key']);
        }
    }
}

return new MP_Api_Client();
