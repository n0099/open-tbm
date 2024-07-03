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
        $primaryKey = $thread->getTable() . '.tid';
        $paginators = $thread->cursorPaginate(1000, columns: 'tid', cursor: new Cursor([$primaryKey => $cursor]));

        return [
            'pages' => [
                'currentCursor' => $paginators->cursor()->parameter($primaryKey),
                'nextCursor' => $paginators->nextCursor()->parameter($primaryKey)
            ],
            'tid' => array_column($paginators->items(), 'tid')
        ];
    }
}
