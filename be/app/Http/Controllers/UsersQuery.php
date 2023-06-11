<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Eloquent\Model\UserModel;

class UsersQuery extends Controller
{
    private int $pagingPerPageItems = 200;

    public function query(\Illuminate\Http\Request $request): array
    {
        $queryParams = $request->validate([
            'uid' => 'integer',
            'name' => 'string',
            'displayName' => 'string',
            'gender' => 'string|in:0,1,2,NULL'
        ]);
        Helper::abortAPIIf(40402, empty($queryParams));

        $queryBuilder = (new UserModel())->newQuery();

        $nullableParams = ['name', 'displayName', 'gender'];
        foreach ($nullableParams as $nullableParamName) {
            if (\array_key_exists($nullableParamName, $queryParams) && $queryParams[$nullableParamName] === 'NULL') {
                $queryBuilder = $queryBuilder->whereNull($nullableParamName);
                unset($queryParams[$nullableParamName]);
            }
        }

        $queriedInfo = $queryBuilder
            ->where($queryParams)
            ->orderBy('id', 'DESC')
            ->hidePrivateFields()
            ->simplePaginate($this->pagingPerPageItems);
        Helper::abortAPIIf(40402, $queriedInfo->isEmpty());

        return [
            'pages' => [
                'firstItem' => $queriedInfo->firstItem(),
                'itemCount' => $queriedInfo->count(),
                'currentPage' => $queriedInfo->currentPage()
            ],
            'users' => $queriedInfo->toArray()['data']
        ];
    }
}
