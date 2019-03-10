<?php

namespace App\Console\Commands;

use App\Eloquent\BilibiliVoteModel;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\UserModel;
use App\Tieba\Post\Post;
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
        $replyModel->where('tid', $voteThreadId)->chunk(1000, function (Collection $voteReplies) use ($voteResultModel) {
            $voteResults = [];
            $candidateIdRange = range(1, 1056);
            $votersPerviousVaildVotesCount = $voteResultModel
                ::select('authorUid')
                ->selectRaw('COUNT(*)')
                ->whereIn('authorUid', $voteReplies->pluck('authorUid'))
                ->where('isVaild', true)
                ->groupBy('authorUid')
                ->get();
            foreach ($voteReplies as $voteReply) {
                $voterUid = $voteReply['authorUid'];
                $voteRegex = Regex::match('/"text": "(.*?)投(.*?)号候选人/', $voteReply['content']);
                $voteBy = $voteRegex->groupOr(1, '');
                $voteFor = $voteRegex->groupOr(2, '');
                $isVoteVaild = $voteRegex->hasMatch()
                    && $voteReply['authorExpGrade'] >= 4
                    && $voteBy == UserModel::uid($voterUid)->first/*OrFail*/()['name']
                    && in_array($voteFor, $candidateIdRange)
                    && $votersPerviousVaildVotesCount->where('authorUid', $voterUid)->isEmpty();
                $voteResults[] = [
                    'pid' => $voteReply['pid'],
                    'authorUid' => $voteReply['authorUid'],
                    'authorExpGrade' => $voteReply['authorExpGrade'],
                    'isVaild' => $isVoteVaild,
                    'voteBy' => $voteBy,
                    'voteFor' => $voteFor,
                    'replyContent' => $voteReply['content'],
                    'postTime' => $voteReply['postTime']
                ];
            }
            $voteResultModel->chunkInsertOnDuplicate($voteResults, null, 1000);
        });
    }
}
