<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent\UserModel;
use Carbon\Carbon;

class UserInfoParser
{
    protected $usersList = [];

    public function parseUsersList(array $usersList): int
    {
        if (count($usersList) == 0) {
            throw new \LengthException('Users list is empty');
        }

        $usersInfo = [];
        $parsedUserTimes = 0;
        $now = Carbon::now();
        foreach ($usersList as $user) {
            ExceptionAdditionInfo::set(['parsingUid' => $user['id']]);
            if ($user['id'] == '') {
                return 0; // when thread's author user is anonymous, the first uid in users list will be empty string and will be re-recorded after next
            } elseif ($user['id'] < 0) { // anonymous user
                $usersInfo[] = [
                    'uid' => $user['id'],
                    'name' => $user['name_show'],
                    'avatarUrl' => $user['portrait']
                ];
            } else { // normal or canceled user
                $usersInfo[] = [
                    'uid' => $user['id'],
                    'name' => Crawlable::nullableValidate($user['name']),
                    'displayName' => $user['name'] == $user['name_show'] ? null : $user['name_show'],
                    'avatarUrl' => $user['portrait'],
                    'gender' => Crawlable::nullableValidate($user['gender']),
                    'fansNickname' => isset($user['fans_nickname']) ? Crawlable::nullableValidate($user['fans_nickname']) : null,
                    'iconInfo' => Crawlable::nullableValidate($user['iconinfo'], true),
                    'alaInfo' => (
                        ! isset($user['ala_info']['lat'])
                        || Crawlable::nullableValidate($user['ala_info']) != null
                        && ($user['ala_info']['lat'] == 0 && $user['ala_info']['lng'] == 0)
                    )
                        ? null
                        : Crawlable::nullableValidate($user['ala_info'], true),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
            $parsedUserTimes += 1;
        }
        ExceptionAdditionInfo::remove('parsingUid');
        // lazy saving to Eloquent model
        $this->pushUsersList($usersInfo);

        return $parsedUserTimes;
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

    public function saveUsersList(): void
    {
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present session's transaction isolation level to reduce deadlock
        ExceptionAdditionInfo::set(['insertingUsers' => true]);
        $chunkInsertBufferSize = 100;
        $userModel = new UserModel();
        foreach (Crawlable::groupNullableColumnArray($this->usersList, [
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
