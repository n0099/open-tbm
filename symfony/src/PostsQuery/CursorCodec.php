<?php

namespace App\PostsQuery;

use App\Helper;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** @psalm-type PostsKeyByTypePluralName = Collection{
 *     threads: Collection<array{tid: int, postedAt: int}>,
 *     replies: Collection<array{tid: int, pid: int, postedAt: int}>,
 *     subReplies: Collection<array{tid: int, pid: int, spid: int, postedAt: int}>,
 * } */
class CursorCodec
{
    /** @param PostsKeyByTypePluralName $postsKeyByTypePluralName */
    public function encodeNextCursor(Collection $postsKeyByTypePluralName, string $orderByField): string
    {
        $encodedCursorsKeyByPostType = $postsKeyByTypePluralName
            ->mapWithKeys(static fn(Collection $posts, string $type) => [
                Helper::POST_TYPE_PLURAL_TO_SINGULAR[$type] => $posts->last(), // null when no posts
            ]) // [singularPostTypeName => lastPostInResult]
            ->filter() // remove post types that have no posts
            ->map(fn(array $post, string $typePluralName) =>
                [$post[Helper::POST_TYPE_TO_ID[$typePluralName]], $post[$orderByField]])
            ->map(static fn(array $cursors) => collect($cursors)
                ->map(static function (int|string $cursor): string {
                    if ($cursor === 0) { // quick exit to keep 0 as is
                        // to prevent packed 0 with the default format 'P' after 0x00 trimming is an empty string
                        // that will be confused with post types without a cursor that is a blank encoded cursor ',,'
                        return '0';
                    }
                    $prefix = match (true) {
                        \is_int($cursor) && $cursor < 0 => '-',
                        \is_string($cursor) => 'S',
                        default => '',
                    };

                    $value = \is_int($cursor)
                        // remove trailing 0x00 for an unsigned int or 0xFF for a signed negative int
                        ? rtrim(pack('P', $cursor), $cursor >= 0 ? "\x00" : "\xFF")
                        : ($prefix === 'S'
                            // keep string as is since encoded string will always longer than the original string
                            ? $cursor
                            : throw new \RuntimeException('Invalid cursor value'));
                    if ($prefix !== 'S') {
                        // https://en.wikipedia.org/wiki/Base64#URL_applications
                        $value = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value));
                    }

                    return $prefix . ($prefix === '' ? '' : ':') . $value;
                })
                ->join(','));
        return collect(Helper::POST_TYPES)
            // merge cursors into flipped Helper::POST_TYPES with the same post type key
            // value of keys that non exists in $encodedCursorsKeyByPostType will remain as int
            ->flip()->merge($encodedCursorsKeyByPostType)
            // if the flipped value is a default int key there's no posts of this type
            // (type key not exists in $postsKeyByTypePluralName)
            // so we just return an empty ',' as placeholder
            ->map(static fn(string|int $cursor) => \is_int($cursor) ? ',' : $cursor)
            ->join(',');
    }

    /** @psalm-return Collection<'reply'|'subReply'|'thread', array> */
    public function decodeCursor(string $encodedCursors, string $orderByField): Collection
    {
        return collect(Helper::POST_TYPES)
            ->combine(Str::of($encodedCursors)
                ->explode(',')
                ->map(static function (string $encodedCursor): int|string|null {
                    /**
                     * @var string $cursor
                     * @var string $prefix
                     */
                    [$prefix, $cursor] = array_pad(explode(':', $encodedCursor), 2, null);
                    if ($cursor === null) { // no prefix being provided means the value of cursor is a positive int
                        $cursor = $prefix;
                        $prefix = '';
                    }
                    return $cursor === '0' ? 0 : match ($prefix) { // keep 0 as is
                        'S' => $cursor, // string literal is not base64 encoded
                        default => ((array) (
                            unpack(
                                format: 'P',
                                string: str_pad( // re-add removed trailing 0x00 or 0xFF
                                    base64_decode(
                                        // https://en.wikipedia.org/wiki/Base64#URL_applications
                                        str_replace(['-', '_'], ['+', '/'], $cursor),
                                    ),
                                    length: 8,
                                    pad_string: $prefix === '-' ? "\xFF" : "\x00",
                                ),
                            )
                        ))[1], // the returned array of unpack() will starts index from 1
                    };
                })
                ->chunk(2) // split six values into three post type pairs
                ->map(static fn(Collection $i) => $i->values())) // reorder keys after chunk
            ->mapWithKeys(fn(Collection $cursors, string $postType) =>
            [$postType =>
                $cursors->mapWithKeys(fn(int|string|null $cursor, int $index) =>
                [$index === 0 ? Helper::POST_TYPE_TO_ID[$postType] : $orderByField => $cursor]),
            ])
            // filter out cursors with all fields value being null, '' or 0 with their encoded cursor ',,'
            ->reject(static fn(Collection $cursors) =>
                $cursors->every(static fn(int|string|null $cursor) => empty($cursor)))
            ->map(static fn(Collection $cursors) => $cursors->all());
    }
}
