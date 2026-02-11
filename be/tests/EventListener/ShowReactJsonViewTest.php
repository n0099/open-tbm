<?php

namespace App\Tests\EventListener;

use App\EventListener\ShowReactJsonView;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ShowReactJsonView::class)]
class ShowReactJsonViewTest extends KernelTestCase
{
    private ShowReactJsonView $sut;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->sut = static::getContainer()->get(ShowReactJsonView::class);
    }

    public function testShowing(): void
    {
        $baseUrl = '/base/url';
        $event = new ResponseEvent(
            self::$kernel,
            new Request(server: ['HTTP_ACCEPT' => 'text/html']
                + array_fill_keys(['SCRIPT_FILENAME', 'SCRIPT_NAME', 'REQUEST_URI'], $baseUrl)),
            HttpKernelInterface::MAIN_REQUEST,
            JsonResponse::fromJsonString(\Safe\json_encode(['test' => 'test'])),
        );
        ($this->sut)($event);
        self::assertEquals(<<<HTML
        <html lang="en">
            <head>
                <title>15 bytes of json response for route /</title>
            </head>
            <body>
                <h4>15 bytes</h4>
                <div id="root"></div>
                <script type="module">
                    import { createElement, createRoot, ReactJsonView } from '$baseUrl/react-json-view/dist/index.js';

                    const root = createRoot(document.getElementById('root'));
                    root.render(createElement(ReactJsonView.default, { src: {"test":"test"}, quotesOnKeys: false }));
                </script>
                <style>
                    .object-content {
                        content-visibility: auto;
                    }
                </style>
            </body>
        </html>
        HTML, $event->getResponse()->getContent());
    }

    public function testNotShowing(): void
    {
        $event = new ResponseEvent(
            self::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            JsonResponse::fromJsonString(\Safe\json_encode(['test' => 'test'])),
        );
        ($this->sut)($event);
        self::assertEquals('{"test":"test"}', $event->getResponse()->getContent());
    }
}
