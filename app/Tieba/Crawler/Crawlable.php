<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use GuzzleHttp;
use function GuzzleHttp\json_encode;

abstract class Crawlable
{
    protected $forumId;

    protected $clientVersion = '6.0.2';

    abstract public function doCrawl();

    protected function parseUsersList(array $usersList)
    {
        if (count($usersList) == 0) {
            throw new \LengthException('Users list is empty');
        }

        $usersInfo = [];
        foreach ($usersList as $user) {
            //debug($user);
            $usersInfo[] = [
                'uid' => $user['id'],
                'name' => $user['name'],
                'displayName' => $user['name'] == $user['name_show'] ? null : $user['name_show'],
                'avatarUrl' => $user['portrait'],
                'gender' => $user['gender'],
                'fansNickname' => isset($user['fans_nickname']) ? self::valueValidate($user['fans_nickname']) : null,
                'iconInfo' => self::valueValidate($user['iconinfo'], true),
                'alaInfo' => ((! isset($user['ala_info'])) || self::valueValidate($user['ala_info']) != null
                    && $user['ala_info']['lat'] == 0 && $user['ala_info']['lng'] == 0)
                    ? null
                    : self::valueValidate($user['ala_info'], true)
            ];
        }

        (new Eloquent\UserModel())->insertOnDuplicateKey($usersInfo);
    }

    protected function getClientHelper() : ClientHelper
    {
        $debugBar = resolve('debugbar');

        $timeline = $debugBar->getCollector('time');
        $profiler = new GuzzleHttp\Profiling\Debugbar\Profiler($timeline);

        $stack = GuzzleHttp\HandlerStack::create();
        $stack->unshift(new GuzzleHttp\Profiling\Middleware($profiler));

        $logger = $debugBar->getCollector('messages');
        $stack->push(GuzzleHttp\Middleware::log($logger, new GuzzleHttp\MessageFormatter()));

        return new ClientHelper(['handler' => $stack, 'client_version' => $this->clientVersion, 'request.options' => [
            'timeout' => 5,
            'connect_timeout' => 5
        ]]);
    }

    protected static function valueValidate($value, bool $isJson = false)
    {
        if ($value == '""' || $value == '[]' || blank($value)) {
            return null;
        }

        return $isJson ? json_encode($value) : $value;
    }
}
