<?php

namespace App\Console\Commands;

use App\Eloquent\BilibiliVoteModel;
use App\Helper;
use App\Tieba\Eloquent\PostModelFactory;
use App\Tieba\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Regex\Regex;
use function GuzzleHttp\json_encode;

class BilibiliVote extends Command
{
    protected $signature = 'tbm:bilibiliVote';

    protected $description = '专题-bilibili吧公投：生成投票记录';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $bilibiliFid = 2265748;
        $voteTid = 6062186860;
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $replyModel = PostModelFactory::newReply($bilibiliFid);
        $voteResultModel = new BilibiliVoteModel();
        $replyModel->where('tid', $voteTid)
            ->whereBetween('postTime', [$voteStartTime, $voteEndTime])
            ->chunk(10, function (Collection $voteReplies) use ($voteResultModel) { // lower chunk size to minimize influence of ignoring previous valid vote
                $voteResults = [];
                $candidateIDRange = range(1, 1056);
                $votersPreviousValidVotesCount = $voteResultModel
                    ::select('authorUid')
                    ->selectRaw('COUNT(*)')
                    ->whereIn('authorUid', $voteReplies->pluck('authorUid'))
                    ->where('isValid', true)
                    ->groupBy('authorUid')
                    ->get();
                // $votersUsername = UserModel::uid($voteReplies->pluck('authorUid'))->select('uid', 'name')->get();
                foreach ($voteReplies as $voteReply) {
                    $voterUid = $voteReply['authorUid'];
                    $voteRegex = Regex::match('/"text":"(.*?)投(.*?)号候选人/', json_encode($voteReply['content']) ?? '');
                    $voteBy = $voteRegex->groupOr(1, '');
                    $voteFor = trim($voteRegex->groupOr(2, ''));
                    $isVoteValid = $voteRegex->hasMatch()
                        && $voteReply['authorExpGrade'] >= 4
                        // && $voteBy == ($votersUsername->where('uid', $voterUid)->first()['name'] ?? false)
                        && in_array($voteFor, $candidateIDRange, true)
                        && $votersPreviousValidVotesCount->where('authorUid', $voterUid)->first() == null;
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
