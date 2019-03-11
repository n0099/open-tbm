<?php

namespace App\Console\Commands;

use App\Eloquent\BilibiliVoteModel;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Regex\Regex;

class BilibiliVote extends Command
{
    protected $signature = 'tbm:bilibiliVote';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $bilibiliForumId = 2265748;
        $voteThreadId = 6062186860;
        $replyModel = PostModelFactory::newReply($bilibiliForumId);
        $voteResultModel = new BilibiliVoteModel();
        $replyModel->where('tid', $voteThreadId)->chunk(2000, function (Collection $voteReplies) use ($voteResultModel) {
            $voteResults = [];
            $candidateIdRange = range(1, 1056);
            $votersPerviousValidVotesCount = $voteResultModel
                ::select('authorUid')
                ->selectRaw('COUNT(*)')
                ->whereIn('authorUid', $voteReplies->pluck('authorUid'))
                ->where('isValid', true)
                ->groupBy('authorUid')
                ->get();
            $votersUsername = UserModel::uid($voteReplies->pluck('authorUid'))->select('uid', 'name')->get();
            foreach ($voteReplies as $voteReply) {
                $voterUid = $voteReply['authorUid'];
                $voteRegex = Regex::match('/"text": "(.*?)投(.*?)号候选人/', $voteReply['content'] ?? '');
                $voteBy = $voteRegex->groupOr(1, '');
                $voteFor = $voteRegex->groupOr(2, '');
                $isVoteValid = $voteRegex->hasMatch()
                    && $voteReply['authorExpGrade'] >= 4
                    && $voteBy == ($votersUsername->where('uid', $voterUid)->first()['name'] ?? false)
                    && in_array($voteFor, $candidateIdRange)
                    && $votersPerviousValidVotesCount->where('authorUid', $voterUid)->first() == null;
                $voteResults[] = [
                    'pid' => $voteReply['pid'],
                    'authorUid' => $voteReply['authorUid'],
                    'authorExpGrade' => $voteReply['authorExpGrade'],
                    'isValid' => $isVoteValid,
                    'voteBy' => Helper::nullableValidate($voteBy),
                    'voteFor' => Helper::nullableValidate($voteFor),
                    'replyContent' => $voteReply['content'],
                    'postTime' => $voteReply['postTime']
                ];
            }
            $voteResultModel->chunkInsertOnDuplicate($voteResults, ['authorExpGrade'], 2000); // never update isValid field to prevent covering wrong value
        });
    }
}
