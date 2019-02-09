<?php

namespace App\Tieba\Crawler;

use App\Tieba\Eloquent;
use Carbon\Carbon;
use GuzzleHttp;
use function GuzzleHttp\json_encode;

abstract class Crawlable
{
    protected $clientVersion;

    protected $indexesList;

    protected $usersList = [];

    abstract public function doCrawl();

    abstract public function saveLists();

    protected function getClientHelper(): ClientRequester
    {
        /*$debugBar = resolve('debugbar');

        $timeline = $debugBar->getCollector('time');
        $profiler = new GuzzleHttp\Profiling\Debugbar\Profiler($timeline);

        $stack = GuzzleHttp\HandlerStack::create();
        $stack->unshift(new GuzzleHttp\Profiling\Middleware($profiler));

        $logger = $debugBar->getCollector('messages');
        $stack->push(GuzzleHttp\Middleware::log($logger, new GuzzleHttp\MessageFormatter()));
        $stack->push(GuzzleHttp\Middleware::log(\Log::getLogger(), new GuzzleHttp\MessageFormatter('{code} {host}{target} {error}')));*/

        return new ClientRequester([
            //'handler' => $stack,
            'client_version' => $this->clientVersion,
            'request.options' => [
                'timeout' => 5,
                'connect_timeout' => 5
            ]
        ]);
    }

    protected static function valueValidate($value, bool $isJson = false)
    {
        if ($value === '""' || $value === '[]' || blank($value)) {
            return null;
        }

        return $isJson ? json_encode($value) : $value;
    }

    protected static function getSubKeyValueByKeys(array $haystack, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $haystack[$key];
        }
        return $values;
    }

    /**
     * Sort SQL INSERT data to prevent mutual insert intention gap deadlock
     *
     * @param array $array
     * @param string $key
     */
    /*protected function sortArrayBySubKeyValue(array &$array, string $key)
    {
        usort($array, function ($first, $second) use ($key) {
            return $first[$key] <=> $second[$key];
        });
    }*/

    protected function parseUsersList(array $usersList): self
    {
        if (count($usersList) == 0) {
            throw new \LengthException('Users list is empty');
        }

        $usersInfo = [];
        foreach ($usersList as $user) {
            $now = Carbon::now();
            if ($user['id'] == '' || $user['id'] < 0) { // TODO: compatible with anonymous user
                $usersInfo[] = [
                ];
            } else {
                $usersInfo[] = [
                    'uid' => $user['id'],
                    'name' => $user['name'],
                    'displayName' => $user['name'] == $user['name_show'] ? null : $user['name_show'],
                    'avatarUrl' => $user['portrait'],
                    'gender' => self::valueValidate($user['gender']),
                    'fansNickname' => isset($user['fans_nickname']) ? self::valueValidate($user['fans_nickname']) : null,
                    'iconInfo' => self::valueValidate($user['iconinfo'], true),
                    'alaInfo' => (
                        ! isset($user['ala_info']['lat'])
                        || self::valueValidate($user['ala_info']) != null
                        && ($user['ala_info']['lat'] == 0 && $user['ala_info']['lng'] == 0)
                    )
                        ? null
                        : self::valueValidate($user['ala_info'], true),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }
        // lazy saving to Eloquent model
        $this->pushUsersList($usersInfo);

        return $this;
    }

    private function pushUsersList($newUsersList): void
    {
        foreach ($newUsersList as $newUser) {
            $existedInListKey = array_search($newUser['uid'], array_column($this->usersList, 'uid'));
            if ($existedInListKey === false) {
                $this->usersList[] = $newUser;
            } else {
                $this->usersList[$existedInListKey] = $newUser;
            }
        }
    }

    protected function saveUsersList(): void
    {
        // group INSERT sql statement to prevent update with null values
        $usersList = [];
        foreach ($this->usersList as $user) {
            $nullValueFields = ['gender' => false, 'fansNickname' => false, 'alaInfo' => false];
            foreach ($nullValueFields as $nullableFieldName => $isNull) {
                $nullValueFields[$nullableFieldName] = $user[$nullableFieldName] == null;
            }
            $nullValueFieldsCount = array_sum($nullValueFields);
            if ($nullValueFieldsCount == count($nullValueFields)) {
                $usersList['allNull'][] = $user;
            } elseif ($nullValueFieldsCount == 0) {
                $usersList['notAllNull'][] = $user;
            } else {
                $nullValueFieldName = array_search(false, $nullValueFields);
                $usersList[$nullValueFieldName][] = $user;
            }
        }
        // same functional with above
        /*foreach ($this->usersList as $user) {
            if ($user['fansNickname'] == null && $user['alaInfo'] == null) {
                unset($user['fansNickname']);
                unset($user['alaInfo']);
                $usersList['null'][] = $user;
            } elseif ($user['fansNickname'] == null) {
                unset($user['fansNickname']);
                $usersList['nullFansNickname'][] = $user;
            } elseif ($user['alaInfo'] == null) {
                unset($user['alaInfo']);
                $usersList['nullAlaInfo'][] = $user;
            } else {
                $usersList['update'][] = $user;
            }
        }*/

        foreach ($usersList as $usersListGroup) {
            $userListExceptFields = array_diff(array_keys($usersListGroup[0]), ['created_at']);
            (new Eloquent\UserModel())->insertOnDuplicateKey($usersListGroup, $userListExceptFields);
        }

        //$this->usersList = [];
    }
}
