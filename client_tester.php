<?php
ini_set('display_errors', 'On');

function tieba_client_sign($post_data, $client_version) {
    $client_info = [
        '_client_id' => 'wappc_'.mt_rand(1000000000000, 9999999999999).'_'.mt_rand(100, 999),
        '_client_type' => 2,
        '_client_version' => $client_version
    ];
    $post_data = $client_info + $post_data;

    $client_sign = null;
    foreach ($post_data as $key => $value) {
        $client_sign .= "{$key}={$value}";
    }
    $post_data['sign'] = strtoupper(md5($client_sign.'tiebaclient!!!'));
    return $post_data;
}

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
//Note7 (￣▽￣)~*
curl_setopt($curl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Linux; Android 6.0; SM-N930F Build/MMB29K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.81 Mobile Safari/537.36');
//curl_setopt($curl, CURLOPT_COOKIE, "BDUSS={$bduss};");
//curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

switch ($_GET['type']) {
    case 'posts':
        $curl_url = 'http://c.tieba.baidu.com/c/f/frs/page';
        $post_data = [
            'kw' => $_GET['forum'],
            'pn' => $_GET['pn'],
            'rn' => $_GET['rn']
        ];
        break;
    case 'replies':
        $curl_url = 'http://c.tieba.baidu.com/c/f/pb/page';
        $post_data = [
            'kz' => $_GET['tid'],
            'pn' => $_GET['pn']
        ];
        break;
    case 'lzls':
        $curl_url = 'http://c.tieba.baidu.com/c/f/pb/floor';
        $post_data = [
            'kz' => $_GET['tid'],
            'pid' => $_GET['pid'],
            'pn' => $_GET['pn']
        ];
        break;
    case 'lzl_reverse':
        $curl_url = 'http://c.tieba.baidu.com/c/f/pb/floor';
        $post_data = [
            'kz' => $_GET['tid'],
            'spid' => $_GET['spid']
        ];
        break;
    default:
        $curl_url = '';
        $post_data = [];
}
curl_setopt($curl, CURLOPT_URL, $curl_url);
$post_data = tieba_client_sign($post_data, $_GET['client_version']);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
$result = curl_exec($curl);

//echo 'curl timing:' . curl_getinfo($curl, CURLINFO_TOTAL_TIME) . '\n';
//echo '<pre>';
print_r($result);
//echo '</pre>';