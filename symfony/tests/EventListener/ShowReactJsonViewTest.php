<?php

namespace App\Tests\EventListener;

use App\EventListener\ShowReactJsonView;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ShowReactJsonView::class)]
class ShowReactJsonViewTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(UrlGenerator::class)->forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        app(UrlGenerator::class)->forceRootUrl(Config::get('app.url'));
        parent::tearDown();
    }

    public function testHandle(): void
    {
        $next = static fn() => JsonResponse::fromJsonString(\Safe\json_encode(['test' => 'test']));
        $sut = new DumpJsonResponse();
        self::assertEquals(<<<JSON
        {
            "test": "test"
        }
        JSON, $sut->handle(Request::create('', server: ['HTTP_ACCEPT' => 'application/json']), $next)->getContent());

        self::assertEquals(<<<HTML
        <h4>15 bytes</h4>
        <div id="root"></div>
        <script type="module">
            import ReactJsonView from 'http://localhost/assets/react-json-view.js';
            import { createElement } from 'http://localhost/assets/react.js';
            import { createRoot } from 'http://localhost/assets/react-dom.js';

            const root = createRoot(document.getElementById('root'));
            root.render(createElement(ReactJsonView.default, { src: {"test":"test"}, quotesOnKeys: false }));
        </script>
        <style>
            .object-content {
                content-visibility: auto;
            }
        </style>
        HTML, ($sut)->handle(Request::create(''), $next)->getContent());
    }
}
