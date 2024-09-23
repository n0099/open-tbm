<?php

namespace App\Http\PostsQuery;

use App\Helper;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Rule;

class ParamsValidator
{
    public const array UNIQUE_PARAMS_NAME = ['fid', 'postTypes', 'orderBy'];

    public QueryParams $params;


    /** @param array[] $params */
    public function __construct(private readonly Factory $validator, array $params)
    {
        $this->validateParamsValue($params);
        $this->params = new QueryParams($params);
        $this->validate40001();
        $this->validate40005();
    }

    public function addDefaultParamsThenValidate(bool $shouldSkip40003): void
    {
        $this->params->addDefaultValueOnParams();
        $this->params->addDefaultValueOnUniqueParams();
        // sort here to prevent further sort while validating
        $sortedPostTypes = collect($this->params->getUniqueParamValue('postTypes'))->sort()->values()->all();
        $this->params->setUniqueParamValue('postTypes', $sortedPostTypes);
        $currentPostTypes = (array) $this->params->getUniqueParamValue('postTypes');
        if (!$shouldSkip40003) {
            $this->validate40003($currentPostTypes);
        }
        $this->validate40004($currentPostTypes);
    }

    private function validateParamsValue(array $params): void
    {
        $paramsPossibleValue = [
            'userGender' => [0, 1, 2],
            'userManagerType' => ['NULL', 'manager', 'assist', 'voiceadmin'],
        ];
        $dateRangeValidator = function ($_, string $value): void {
            $this->validator->make(
                explode(',', $value),
                ['0' => 'date|before_or_equal:1', '1' => 'date|after_or_equal:0'],
            )->validate();
        };
        // note here we haven't validated that is every sub param have a corresponding main param yet
        $this->validator->make($params, [
            '*.fid' => 'integer',
            '*.postTypes' => 'array|in:thread,reply,subReply',
            '*.orderBy' => 'string|in:postedAt,tid,pid,spid',
            '*.direction' => 'in:ASC,DESC',
            '*.tid' => 'integer',
            '*.pid' => 'integer',
            '*.spid' => 'integer',
            '*.postedAt' => $dateRangeValidator,
            '*.latestReplyPostedAt' => $dateRangeValidator,
            '*.threadViewCount' => 'integer',
            '*.threadShareCount' => 'integer',
            '*.threadReplyCount' => 'integer',
            '*.replySubReplyCount' => 'integer',
            '*.threadProperties' => 'array|in:good,sticky',
            '*.authorUid' => 'integer',
            '*.authorExpGrade' => 'integer',
            '*.authorGender' => Rule::in($paramsPossibleValue['userGender']),
            '*.authorManagerType' => Rule::in($paramsPossibleValue['userManagerType']),
            '*.latestReplierUid' => 'integer',
            '*.latestReplierGender' => Rule::in($paramsPossibleValue['userGender']),

            '*.not' => 'boolean',
            // sub param of tid, pid, spid
            // threadViewCount, threadShareCount, threadReplyCount, replySubReplyCount
            // authorUid, authorExpGrade, latestReplierUid
            '*.range' => 'in:<,=,>,IN,BETWEEN',
            // sub param of threadTitle, postContent
            // authorName, authorDisplayName
            // latestReplierName, latestReplierDisplayName
            '*.matchBy' => 'in:implicit,explicit,regex',
            '*.spaceSplit' => 'boolean',
        ])->validate();
    }

    private function validate40001(): void
    {
        // only fill postTypes and/or orderBy uniqueParam doesn't query anything
        Helper::abortAPIIf(40001, $this->params->count() === \count($this->params->pick('postTypes', 'orderBy')));
    }

    private function validate40005(): void
    {
        foreach (self::UNIQUE_PARAMS_NAME as $uniqueParamName) { // is all unique param only appeared once
            Helper::abortAPIIf(40005, \count($this->params->pick($uniqueParamName)) > 1);
        }
    }

    private static function isRequiredPostTypes(array $current, array $required): bool
    {
        $required[1] = Arr::sort($required[1]);
        return $required[0] === 'SUB'
            ? array_diff($current, $required[1]) === []
            : $current === $required[1];
    }

    private function validate40003(array $currentPostTypes): void
    {
        $paramsRequiredPostTypes = [
            'pid' => ['SUB', ['reply', 'subReply']],
            'spid' => ['ALL', ['subReply']],
            'latestReplyPostedAt' => ['ALL', ['thread']],
            'threadTitle' => ['ALL', ['thread']],
            'postContent' => ['SUB', ['reply', 'subReply']],
            'threadViewCount' => ['ALL', ['thread']],
            'threadShareCount' => ['ALL', ['thread']],
            'threadReplyCount' => ['ALL', ['thread']],
            'replySubReplyCount' => ['ALL', ['reply']],
            'threadProperties' => ['ALL', ['thread']],
            'authorExpGrade' => ['SUB', ['reply', 'subReply']],
            'latestReplierUid' => ['ALL', ['thread']],
            'latestReplierName' => ['ALL', ['thread']],
            'latestReplierDisplayName' => ['ALL', ['thread']],
            'latestReplierGender' => ['ALL', ['thread']],
        ];
        foreach ($paramsRequiredPostTypes as $paramName => $requiredPostTypes) {
            if ($this->params->pick($paramName) !== []) {
                Helper::abortAPIIfNot(40003, self::isRequiredPostTypes($currentPostTypes, $requiredPostTypes));
            }
        }
    }

    private function validate40004(array $currentPostTypes): void
    {
        $orderByRequiredPostTypes = [
            'pid' => ['SUB', ['reply', 'subReply']],
            'spid' => ['SUB', ['subReply']],
        ];
        $currentOrderBy = (string) $this->params->getUniqueParamValue('orderBy');
        if (\array_key_exists($currentOrderBy, $orderByRequiredPostTypes)) {
            Helper::abortAPIIfNot(
                40004,
                self::isRequiredPostTypes($currentPostTypes, $orderByRequiredPostTypes[$currentOrderBy]),
            );
        }
    }
}
