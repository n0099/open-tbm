<urlset
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($tids as $tid)
        <url>
            <loc>{{ config('app.fe_url') }}/posts/tid/{{ $tid }}</loc>
        </url>
    @endforeach
</urlset>
