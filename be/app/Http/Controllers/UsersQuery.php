<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Eloquent\Model\User;

class UsersQuery extends Controller
{
    private int $perPageItems = 200;

    public function query(\Illuminate\Http\Request $request): array
    {
        $queryParams = $request->validate([
            'uid' => 'integer',
            'name' => 'string',
            'displayName' => 'string',
            'gender' => 'string|in:0,1,2,NULL'
        ]);
        Helper::abortAPIIf(40402, empty($queryParams));

        $queryBuilder = User::newQuery();

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
            ->selectPublicFields()
            ->simplePaginate($this->perPageItems);
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
