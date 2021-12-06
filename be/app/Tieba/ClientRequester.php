<?php

namespace App\Tieba;

/**
 * Class ClientRequester
 *
 * @package App\Tieba\WebHelper
 */
class ClientRequester extends \GuzzleHttp\Client
{
    /**
     * @param $method
     * @param string $uri
     * @param array $options
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function requestAsync($method, $uri = '', array $options = []): \GuzzleHttp\Promise\PromiseInterface
    {
        if (!isset($options['form_params'])) {
            throw new \InvalidArgumentException('Post form params must be determined');
        }
        if ($method !== 'POST') {
            throw new \BadFunctionCallException('Client request must be HTTP POST');
        }

        $clientInfo = [
            '_client_id' => 'wappc_' . mt_rand(1000000000000, 9999999999999) . '_' . mt_rand(100, 999),
            '_client_type' => 2, // 0:WAP|1:iPhone|2:Android|3:WindowsPhone|4:Windows8UWP
            '_client_version' => $this->getConfig('client_version')
        ];
        $clientData = array_merge($clientInfo, $options['form_params']);

        $clientSign = null;
        foreach ($clientData as $key => $value) {
            $clientSign .= "{$key}={$value}";
        }
        $clientData['sign'] = strtoupper(md5($clientSign . 'tiebaclient!!!'));

        $options['form_params'] = $clientData;
        $options['headers']['Referer'] = $uri;

        return parent::requestAsync($method, $uri, $options);
    }

    /**
     * ClientHelper constructor
     *
     * @param array $config
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [])
    {
        if ($config['client_version'] === null) {
            throw new \InvalidArgumentException('Tieba client version must be determined');
        }

        //Note7 (￣▽￣)~*
        $config['headers']['User-Agent'] = 'Mozilla/5.0 (Linux; Android 6.0; SM-N930F Build/MMB29K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.81 Mobile Safari/537.36';
        $config['timeout'] = 3; // default 3 sec timeout

        parent::__construct($config);
    }
}
