<?php declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Twin\Support\Yandex\SpellChecker;

class SpellCheckerTest extends TestCase
{
    public function fixDataProvider():  array
    {
        return [
            ['мама мыла рому', 'мама мыла раму', '[{"code":1,"pos":10,"row":0,"col":10,"len":4,"word":"рому","s":["раму","рамму"]}]'],
            ['мама мыла раму', 'мама мыла раму', '[]']
        ];
    }

    /**
     * @dataProvider fixDataProvider
     * @param string $message
     * @param string $expected
     * @param string $json
     */
    public function testFix(string $message, string $expected, string $json)
    {
        $mock = new MockHandler([
            new Response(200, [], $json),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $spellChecker = new SpellChecker($client);
        $result = $spellChecker->fix($message);
        $this->assertEquals($expected, $result);
    }

    public function testFixInternalErrorException()
    {
        Log::shouldReceive('error')->withSomeOfArgs('YANDEX SPELLER SERVICE FAILED');

        $mock = new MockHandler([
            new Response(500),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $spellChecker = new SpellChecker($client);
        $message = 'мама мыла рому';
        $this->assertSame($message, $spellChecker->fix($message));

        Log::partialMock()->shouldHaveReceived('error');
    }

    public function getCorrectionInfoInvalidResponsesDataProvider(): array
    {
        return [
            // Invalid json
            [new Response(200, [], '')],
            // Invalid status
            [new Response(500)],
            // Invalid schema
            [new Response(200, [], '[{"s":["раму","рамму"]}]')]
        ];
    }

    /**
     * @dataProvider getCorrectionInfoInvalidResponsesDataProvider
     * @param Response $response
     * @throws ReflectionException
     */
    public function testGetCorrectionInfoInvalidResponsesDataProvider(Response $response)
    {
        Log::shouldReceive('error')->withSomeOfArgs('YANDEX SPELLER SERVICE FAILED');

        $mock = new MockHandler([$response]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $spellChecker = new SpellChecker($client);

        $message = 'мама мыла раму';
        $reflection = new ReflectionMethod($spellChecker, 'getCorrectionInfo');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($spellChecker, $message);

        $this->assertSame([], $result);

        Log::partialMock()->shouldHaveReceived('error');
    }
}
