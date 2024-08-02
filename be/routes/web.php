<?php

use App\Eloquent\Model\Forum;
use App\Eloquent\Model\Post\PostFactory;
use App\Helper;
use App\Http\Controllers;

Route::get('/sitemaps/forums', static fn () => Cache::remember('/sitemaps/forums', 86400,
    static fn () => Helper::xmlResponse(view('sitemaps.forums', [
        'tidsKeyByFid' => Forum::get('fid')->pluck('fid')->mapWithKeys(fn (int $fid) => [
            $fid => DB::query()
                ->fromSub(PostFactory::newThread($fid)
                    ->selectRaw('ROW_NUMBER() OVER (ORDER BY tid DESC) AS rn, tid'), 't')
                ->whereRaw('rn % ' . Controllers\ThreadsSitemap::$maxUrls . ' = 0')
                ->pluck('tid')
        ])]))));
Route::get('/sitemaps/forums/{fid}/threads', [Controllers\ThreadsSitemap::class, 'query']);
