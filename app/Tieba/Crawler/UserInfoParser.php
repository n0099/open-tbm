<?php

namespace app\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Tieba\Eloquent\UserModel;
use Carbon\Carbon;

class UserInfoParser
{
    protected $usersList = [];

    public function parseUsersList(array $usersList): self
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
                    'gender' => Crawlable::valueValidate($user['gender']),
                    'fansNickname' => isset($user['fans_nickname']) ? Crawlable::valueValidate($user['fans_nickname']) : null,
                    'iconInfo' => Crawlable::valueValidate($user['iconinfo'], true),
                    'alaInfo' => (
                        ! isset($user['ala_info']['lat'])
                        || Crawlable::valueValidate($user['ala_info']) != null
                        && ($user['ala_info']['lat'] == 0 && $user['ala_info']['lng'] == 0)
                    )
                        ? null
                        : Crawlable::valueValidate($user['ala_info'], true),
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
