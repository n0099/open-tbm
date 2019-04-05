<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Tieba\Eloquent\UserModel;

class UsersQuery extends Controller
{
    private $pagingPerPageItems = 20;

    public function query(\Illuminate\Http\Request $request): array
    {
        $queryParams = $request->validate([
            'uid' => 'integer',
            'name' => 'string',
            'displayName' => 'string',
            'gender' => 'integer|in:0,1,2'
        ]);
        if (empty($queryParams)) {
            Helper::abortApi(40402);
        }

        $queriedInfo = UserModel::where($queryParams)
            ->orderBy('id', 'DESC')
            ->hidePrivateFields()->simplePaginate($this->pagingPerPageItems);
        Helper::abortApiIf($queriedInfo->isEmpty(), 40402);

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
