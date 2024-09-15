<?php

namespace App\Http\Controllers;

use App\Eloquent\Model\Forum;
use App\Eloquent\Model\Post\PostFactory;
use App\Helper;
use Illuminate\Http;
use Illuminate\Support\Facades\Cache;

class ThreadsSitemap extends Controller
{
    public static int $maxUrls = 50000;

    public function query(Http\Request $request, int $fid): Http\Response
    {
        // https://stackoverflow.com/questions/59554777/laravel-how-to-set-default-value-in-validator-at-post-registeration/78707950#78707950
        ['cursor' => $cursor] = $request->validate([
            'cursor' => 'integer',
        ]) + ['cursor' => 0];
        Helper::abortAPIIfNot(40406, Forum::fid($fid)->exists());

        return Cache::remember(
            "/sitemaps/forums/$fid/threads?cursor=$cursor",
            86400,
            static fn() => Helper::xmlResponse(view('sitemaps.threads', [
                'tids' => PostFactory::newThread($fid)
                    ->where('tid', '>', $cursor)->limit(self::$maxUrls)
                    ->orderBy('tid')->pluck('tid'),
            ])),
        );
    }
}
