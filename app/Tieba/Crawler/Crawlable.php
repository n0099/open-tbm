<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent;
use Carbon\Carbon;
use function GuzzleHttp\json_encode;

abstract class Crawlable
{
    protected $forumID;

    protected $clientVersion;

    protected $indexesList = [];

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
     * Group INSERT sql statement to prevent updating cover old data with null values
     *
     * @param array $arrayToGroup
     * @param array $nullableFields
     *
     * @return array
     */
    protected static function groupNullableColumnArray(array $arrayToGroup, array $nullableFields): array
    {
        $arrayAfterGroup = [];
        foreach ($arrayToGroup as $item) {
            $nullValueFields = array_map(function () {
                return false;
            }, array_flip($nullableFields));
            foreach ($nullValueFields as $nullableFieldName => $isNull) {
                $nullValueFields[$nullableFieldName] = $item[$nullableFieldName] ?? null === null;
            }
            $nullValueFieldsCount = array_sum($nullValueFields); // counts all null value fields
            if ($nullValueFieldsCount == count($nullValueFields)) {
                $arrayAfterGroup['allNull'][] = $item;
            } elseif ($nullValueFieldsCount == 0) {
                $arrayAfterGroup['notAllNull'][] = $item;
            } else {
                $nullValueFieldName = implode(array_keys($nullValueFields, true), '+'); // if there's multi fields having null value, we should group them together
                $arrayAfterGroup[$nullValueFieldName][] = $item;
            }
        }

        return $arrayAfterGroup;
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
            ExceptionAdditionInfo::set(['parsingUid' => $user['id']]);
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
        ExceptionAdditionInfo::remove('parsingUid');
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
        ExceptionAdditionInfo::set(['insertingUsers' => true]);
        $chunkInsertBufferSize = 100;
        $userModel = new Eloquent\UserModel();
        foreach (static::groupNullableColumnArray($this->usersList, [
            'gender',
            'fansNickname',
            'alaInfo'
        ]) as $usersListGroup) {
            $userListUpdateFields = array_diff(array_keys($usersListGroup[0]), $userModel->updateExpectFields);
            $userModel->chunkInsertOnDuplicate($usersListGroup, $userListUpdateFields, $chunkInsertBufferSize);
        }
        ExceptionAdditionInfo::remove('insertingUsers');

        $this->usersList = [];
    }
}
