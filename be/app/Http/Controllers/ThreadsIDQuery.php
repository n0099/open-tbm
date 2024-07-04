<?php

namespace App\Http\Controllers;

use App\Eloquent\Model\Forum;
use App\Eloquent\Model\Post\PostFactory;
use App\Helper;
use Illuminate\Pagination\Cursor;

class ThreadsIDQuery extends Controller
{
    public function query(\Illuminate\Http\Request $request, int $fid): array
    {
        ['cursor' => $cursor] = $request->validate([
            'cursor' => 'integer'
        ]) + ['cursor' => 0];
        Helper::abortAPIIfNot(40406, (new Forum())->fid($fid)->exists());
        $thread = PostFactory::newThread($fid);
        $cursorKey = $thread->getTable() . '.tid';
        $paginator = $thread->cursorPaginate(1000, columns: 'tid', cursor: new Cursor([$cursorKey => $cursor]));

        return [
            'pages' => [
                'currentCursor' => $paginator->cursor()->parameter($cursorKey),
                'nextCursor' => $paginator->nextCursor()?->parameter($cursorKey)
            ],
            'tid' => array_column($paginator->items(), 'tid')
        ];
    }
}
