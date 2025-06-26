<?php

namespace App\PostsQuery;

use App\Helper;
use App\Validator\DateTimeRange;
use App\Validator\Validator;
use Illuminate\Support\Arr;
use Symfony\Component\Validator\Constraints as Assert;

class ParamsValidator
{
    public const array UNIQUE_PARAMS_NAME = ['postTypes', 'orderBy'];
    public const array USER_GENDER_VALUES = [0, 1, 2, 'NULL'];

    private QueryParams $params;

    public function __construct(private readonly Validator $validator) {}

    public function getParams(): QueryParams
    {
        return $this->params;
    }

    /** @param array[] $value */
    public function setParams(array $value): static
    {
        array_map($this->validateParamValue(...), $value);
        $this->params = new QueryParams($value);
        $this->validate40005();
        return $this;
    }

    public function addDefaultParamsThenValidate(): void
    {
        $this->params->addDefaultValueOnParams();
        $this->params->addDefaultValueOnUniqueParams();
        $newPostTypes = $this->setRequiredPostTypesByParams();
        Helper::abortAPIIf(40003, $newPostTypes === []);
        $this->params->setUniqueParamValue('postTypes', $newPostTypes);
        $this->validate40004($newPostTypes);
    }

    private function setRequiredPostTypesByParams(): array
    {
        $currentPostTypes = $this->params->getUniqueParamValue('postTypes');
        $requiredPostTypes = array_intersect(Helper::POST_TYPES, ...array_values(Arr::only(
            self::REQUIRED_POST_TYPES_KEY_BY_PARAM_NAME,
            array_map(static fn(QueryParam $p) => $p->name, $this->params->getAll())
        )));
        return collect($currentPostTypes)->intersect($requiredPostTypes)->sort()->values()->toArray();
    }

    private function validateParamValue(array $param): void
    {
        $paramsPossibleValue = [
            'userGender' => self::USER_GENDER_VALUES,
            'userManagerType' => ['NULL', 'manager', 'assist', 'voiceadmin'],
        ];
        $numericParams = collect(QueryParams::PARAM_NAME_KEY_BY_TYPE['numeric'])->push('fid')
            ->mapWithKeys(fn(string $paramName) => [$paramName => new Assert\Type(['digit', 'int'])]);
        $textParams = collect(QueryParams::PARAM_NAME_KEY_BY_TYPE['text'])
            ->mapWithKeys(fn(string $paramName) => [$paramName => new Assert\Type('string')]);
        // note here we haven't validated that is every sub param have a corresponding main param yet
        $this->validator->validate($param, new Assert\Collection([
            ...$numericParams,
            ...$textParams,
            'postTypes' => new Assert\All([new Assert\Choice(Helper::POST_TYPES)]),
            'orderBy' => new Assert\Choice([...Helper::POST_ID, 'postedAt']),
            'direction' => new Assert\Choice(['ASC', 'DESC']),
            'postedAt' => new DateTimeRange(),
            'latestReplyPostedAt' => new DateTimeRange(),
            'threadProperties' => new Assert\All([new Assert\Choice(['good', 'sticky'])]),
            'authorGender' => new Assert\Choice($paramsPossibleValue['userGender']),
            'authorManagerType' => new Assert\Choice($paramsPossibleValue['userManagerType']),
            'latestReplierGender' => new Assert\Choice($paramsPossibleValue['userGender']),

            'not' => new Assert\Type('boolean'),
            // sub param of tid, pid, spid
            // threadViewCount, threadShareCount, threadReplyCount, replySubReplyCount
            // authorUid, authorExpGrade, latestReplierUid
            'range' => new Assert\Choice(['<', '=', '>', 'IN', 'BETWEEN']),
            // sub param of threadTitle, postContent
            // authorName, authorDisplayName
            // latestReplierName, latestReplierDisplayName
            'matchBy' => new Assert\Choice(['implicit', 'explicit', 'regex']),
            'spaceSplit' => new Assert\Type('boolean'),
        ], allowMissingFields: true));
    }

    private function validate40005(): void
    {
        foreach (self::UNIQUE_PARAMS_NAME as $uniqueParamName) { // is all unique param only appeared once
            Helper::abortAPIIf(40005, \count($this->params->pick($uniqueParamName)) > 1);
        }
    }

    private static function isRequiredPostTypes(array $current, array $required): bool
    {
        return array_diff($required, $current) === [];
    }

    public const array REQUIRED_POST_TYPES_KEY_BY_PARAM_NAME = [
        'pid' => ['reply', 'subReply'],
        'spid' => ['subReply'],
        'latestReplyPostedAt' => ['thread'],
        'threadTitle' => ['thread'],
        'postContent' => ['reply', 'subReply'],
        'threadViewCount' => ['thread'],
        'threadShareCount' => ['thread'],
        'threadReplyCount' => ['thread'],
        'replySubReplyCount' => ['reply'],
        'threadProperties' => ['thread'],
        'authorExpGrade' => ['reply', 'subReply'],
        'latestReplierUid' => ['thread'],
        'latestReplierName' => ['thread'],
        'latestReplierDisplayName' => ['thread'],
        'latestReplierGender' => ['thread'],
    ];

    public const array REQUIRED_POST_TYPES_KEY_BY_ORDER_BY_VALUE = [
        'pid' => ['reply', 'subReply'],
        'spid' => ['subReply'],
    ];

    private function validate40004(array $currentPostTypes): void
    {
        $currentOrderBy = (string) $this->params->getUniqueParamValue('orderBy');
        if (\array_key_exists($currentOrderBy, self::REQUIRED_POST_TYPES_KEY_BY_ORDER_BY_VALUE)) {
            Helper::abortAPIIfNot(
                40004,
                self::isRequiredPostTypes(
                    $currentPostTypes,
                    self::REQUIRED_POST_TYPES_KEY_BY_ORDER_BY_VALUE[$currentOrderBy],
                ),
            );
        }
    }
}
