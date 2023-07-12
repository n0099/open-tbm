<?php

namespace App\Console\Commands;

use App\Eloquent\Model\BilibiliVote;
use App\Eloquent\Model\Post\PostFactory;
use App\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Regex\Regex;

class GenerateBilibiliVote extends Command
{
    protected $signature = 'tbm:bilibiliVote';

    protected $description = '专题-bilibili吧公投：生成投票记录';

    public function handle(): void
    {
        $bilibiliFid = 2265748;
        $voteTid = 6062186860;
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $reply = PostFactory::newReply($bilibiliFid);
        $voteResult = new BilibiliVote();
        $reply::where('tid', $voteTid)
            ->whereBetween('postTime', [$voteStartTime, $voteEndTime])
            // set a lower chunk size to minimize influence of ignoring previous valid vote
            ->chunk(10, static function (Collection $voteReplies) use ($voteResult) {
                $voteResults = [];
                $candidateIDRange = range(1, 1056);
                $votersPreviousValidVoteCount = $voteResult::select('authorUid')
                    ->selectRaw('COUNT(*)')
                    ->whereIn('authorUid', $voteReplies->pluck('authorUid'))
                    ->where('isValid', true)
                    ->groupBy('authorUid')
                    ->get();
                // $votersUsername = User::uid($voteReplies->pluck('authorUid'))->select('uid', 'name')->get();
                foreach ($voteReplies as $voteReply) {
                    $voterUid = $voteReply['authorUid'];
                    $voteRegex = Regex::match(
                        '/"text":"(.*?)投(.*?)号候选人/',
                        Helper::jsonEncode($voteReply['content']) ?? ''
                    );
                    $voteBy = $voteRegex->groupOr(1, '');
                    $voteFor = trim($voteRegex->groupOr(2, ''));
                    $isVoteValid = $voteRegex->hasMatch()
                        && $voteReply['authorExpGrade'] >= 4
                        // && $voteBy === ($votersUsername->where('uid', $voterUid)->first()['name'] ?? null)
                        && \in_array((int)$voteFor, $candidateIDRange, true)
                        && $votersPreviousValidVoteCount->where('authorUid', $voterUid)->first() === null;
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
                // never update isValid field to prevent covering wrong value
                $voteResult->chunkInsertOnDuplicate($voteResults, ['authorExpGrade'], 2000);
            });
    }
}
