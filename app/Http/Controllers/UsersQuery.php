<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Tieba\Eloquent\UserModel;

class UsersQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    public function query(\Illuminate\Http\Request $request): array
    {
        $queryParams = $request->validate([
            'uid' => 'integer',
            'name' => 'string',
            'displayName' => 'string',
            'gender' => 'integer|in:0,1,2'
        ]);
        Helper::abortAPIIf(40402, empty($queryParams));

        $queriedInfo = UserModel::where($queryParams)
            ->orderBy('id', 'DESC')
            ->hidePrivateFields()->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40402, $queriedInfo->isEmpty());

        return [
            'pages' => [
                'firstItem' => $queriedInfo->firstItem(),
                'currentItems' => $queriedInfo->count(),
                'currentPage' => $queriedInfo->currentPage(),
            ],
            'users' => $queriedInfo->toArray()['data']
        ];
    }
}
