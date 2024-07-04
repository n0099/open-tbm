<sitemapindex
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($tidsKeyByFid as $fid => $tids)
        @php($forumSitemapPath = config('app.url') . "/sitemaps/forums/$fid/threads")
        <sitemap>
            <loc>{{ $forumSitemapPath }}</loc>
        </sitemap>
        @foreach ($tids as $tid)
            <sitemap>
                <loc>{{ $forumSitemapPath }}?cursor={{ $tid }}</loc>
            </sitemap>
        @endforeach
    @endforeach
</sitemapindex>
