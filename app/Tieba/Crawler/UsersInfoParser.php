<?php

namespace App\Tieba\Crawler;

use App\Exceptions\ExceptionAdditionInfo;
use App\Helper;
use App\Tieba\Eloquent\UserModel;
use Carbon\Carbon;

class UsersInfoParser
{
    protected array $usersInfo = [];

    public function parseUsersInfo(array $usersList): int
    {
        if (\count($usersList) == 0) {
            throw new \LengthException('Users list is empty');
        }

        $usersInfo = [];
        $parsedUserTimes = 0;
        $now = Carbon::now();
        foreach ($usersList as $user) {
            ExceptionAdditionInfo::set(['parsingUid' => $user['id']]);
            if ($user['id'] == '') {
                return 0; // when thread's author user is anonymous, the first uid in users list will be empty string and will be re-recorded after next
            }
            if ($user['id'] < 0) { // anonymous user
                $usersInfo[] = [
                    'uid' => $user['id'],
                    'name' => $user['name_show'],
                    'displayName' => null,
                    'avatarUrl' => $user['portrait'],
                    'gender' => null,
                    'fansNickname' => null,
                    'iconInfo' => null,
                    'alaInfo' => null,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            } else { // normal or canceled user
                $usersInfo[] = [
                    'uid' => $user['id'],
                    'name' => Helper::nullableValidate($user['name']),
                    'displayName' => $user['name'] == $user['name_show'] ? null : $user['name_show'],
                    'avatarUrl' => $user['portrait'],
                    'gender' => Helper::nullableValidate($user['gender']),
                    'fansNickname' => isset($user['fans_nickname']) ? Helper::nullableValidate($user['fans_nickname']) : null,
                    'iconInfo' => Helper::nullableValidate($user['iconinfo'], true),
                    'privacySettings' => null, // set by ReplyCrawler parent thread author info updating
                    'alaInfo' => ! isset($user['ala_info']['lat'])
                        || (Helper::nullableValidate($user['ala_info']) != null
                        && ($user['ala_info']['lat'] == 0 && $user['ala_info']['lng'] == 0))
                        ? null
                        : Helper::nullableValidate($user['ala_info'], true),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
            $parsedUserTimes++;
        }
        ExceptionAdditionInfo::remove('parsingUid');
        // lazy saving to Eloquent model
        $this->pushUsersInfo($usersInfo);

        return $parsedUserTimes;
    }

    private function pushUsersInfo($newUsersInfo): void
    {
        foreach ($newUsersInfo as $newUser) {
            /** @var int $existedUserInfoKey */
            $existedUserInfoKey = array_search($newUser['uid'], array_column($this->usersInfo, 'uid'), true);
            if ($existedUserInfoKey !== false) {
                $this->usersInfo[$existedUserInfoKey] = $newUser;
            } else {
                $this->usersInfo[] = $newUser;
            }
        }
    }

    public function saveUsersInfo(): void
    {
        \DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); // change present session's transaction isolation level to reduce deadlock
        ExceptionAdditionInfo::set(['insertingUsers' => true]);
        $chunkInsertBufferSize = 100;
        $userModel = new UserModel();
        foreach (Crawlable::groupNullableColumnArray($this->usersInfo, [
            'gender',
            'fansNickname',
            'alaInfo'
        ]) as $usersInfoGroup) {
            $userUpdateFields = Crawlable::getUpdateFieldsWithoutExpected($usersInfoGroup[0], $userModel);
            $userModel->chunkInsertOnDuplicate($usersInfoGroup, $userUpdateFields, $chunkInsertBufferSize);
        }

        ExceptionAdditionInfo::remove('insertingUsers');
        $this->usersInfo = [];
    }
}
