<?php

namespace Tests;

use Nyholm\Psr7\Stream;
use Google\ReCaptcha\ReCaptcha;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Google\ReCaptcha\ReCaptchaErrors;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Google\ReCaptcha\ReCaptchaResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Google\ReCaptcha\FailedReCaptchaException;
use PHPUnit\Framework\Constraint\IsInstanceOf;

class ReCaptchaTest extends TestCase
{
    /** @var \Nyholm\Psr7\Factory\Psr17Factory  */
    protected $factory;

    protected function setUp() : void
    {
        $this->factory = new Psr17Factory;

        parent::setUp(); // TODO: Change the autogenerated stub
    }

    public function testCreatesReCaptchaInstanceWithDefaultClient()
    {
        $recaptcha = ReCaptcha::make('test_secret');

        $this->assertInstanceOf(ReCaptcha::class, $recaptcha);

        $this->assertInstanceOf(ClientInterface::class, $recaptcha->getClient());
    }

    public function testGetAndSetClient()
    {
        $recaptcha = ReCaptcha::make('bar');

        $client = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request) : ResponseInterface
            {
            }
        };

        $this->assertInstanceOf(ClientInterface::class, $recaptcha->getclient());

        $recaptcha->setClient($client);

        $this->assertInstanceOf(get_class($client), $recaptcha->getclient());
    }

    public function testSetRequestFactory()
    {
        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        $client = $this->createMock(ClientInterface::class);

        $request = $this->createMock(RequestInterface::class);

        $request->expects($this->at(0))
            ->method('withBody')
            ->with(http_build_query(array_filter([
                'secret' => 'test_secret',
                'response' => 'test_token',
                'remoteip' => '255.255.255.255',
                'version' => ReCaptcha::VERSION
            ])))
            ->willReturnSelf();

        $request->expects($this->at(1))
            ->method('withProtocolVersion')
            ->with('2.0')
            ->willReturnSelf();

        $request->expects($this->at(2))
            ->method('withHeader')
            ->with('Content-Type', 'application/x-www-form-urlencoded')
            ->willReturnSelf();

        $requestFactory
            ->method('createRequest')
            ->with('POST', ReCaptcha::SITE_VERIFY_URL)
            ->willReturn($request);

        $stream = $this->factory->createStream(json_encode([
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ]));

        $stream->rewind();

        $client->method('sendRequest')
            ->with($request)
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->setRequest($requestFactory)
            ->verify('test_token', '255.255.255.255');

        $this->assertInstanceOf(ReCaptchaResponse::class, $response);
    }

    public function testSetStreamFactory()
    {
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = $this->createMock(ClientInterface::class);

        $string = http_build_query([
            'secret' => 'test_secret',
            'response' => 'test_token',
            'remoteip' => '255.255.255.255',
            'version' => ReCaptcha::VERSION
        ]);

        $streamFactory->method('createStream')
            ->with($string)
            ->willReturnCallback(function ($string) {
                return Stream::create($string);
            });

        $stream = $this->factory->createStream(json_encode([
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ]));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->setStream($streamFactory)
            ->verify('test_token', '255.255.255.255');

        $this->assertInstanceOf(ReCaptchaResponse::class, $response);
    }

    public function testSetSecret()
    {
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = $this->createMock(ClientInterface::class);

        $streamFactory->method('createStream')
            ->with($this->anything())
            ->willReturnCallback(function ($string) {
                $this->assertStringContainsString('secret=foo', $string);
                return Stream::create($string);
            });

        $stream = $this->factory->createStream(json_encode([
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ]));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->setStream($streamFactory)
            ->setSecret('foo')
            ->verify('test_token', '255.255.255.255');

        $this->assertInstanceOf(ReCaptchaResponse::class, $response);
    }

    public function testVerifiesReCaptcha()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')
            ->setClient($client)
            ->verify('test_token');

        $this->assertTrue($response->valid());
        $this->assertInstanceOf(ReCaptchaResponse::class, $response);
        $this->assertContains($array, $response->toArray());
    }

    public function testVerifiesFailedReCaptcha()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => false,
            'error-codes' => [
                'test_error'
            ],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')
            ->setClient($client)
            ->verify('test_token');

        $this->assertTrue($response->invalid());
        $this->assertEquals([
            'test_error'
        ], $response->error_codes);
    }

    public function testVerifiesReCaptchaWithIp()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $recaptcha = ReCaptcha::make('test_secret')->setClient($client);

        $response = $recaptcha->verify('test_token', '255.255.255.255');

        $this->assertInstanceOf(ReCaptchaResponse::class, $response);
        $this->assertContains($array, $response->toArray());
    }

    public function testVerifiesReCaptchaOrThrowsException()
    {
        $this->expectException(FailedReCaptchaException::class);

        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => false,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        try {
            ReCaptcha::make('test_secret')
                ->setClient($client)
                ->verifyOrThrow('test_token', '255.255.255.255');
        } catch (FailedReCaptchaException $exception) {
            $this->assertInstanceOf(ReCaptchaResponse::class, $exception->getResponse());

            throw $exception;
        }
    }

    public function testVerifiesReCaptchaDoesntThrowsException()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:00Z',
            'apk_package_name' => 'test_apk',
            'score' => 1.0,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')
            ->setClient($client)
            ->verifyOrThrow('test_token', '255.255.255.255');

        $this->assertTrue($response->valid());
    }

    public function testBuildsConstraints()
    {
        $recaptcha = ReCaptcha::make('test_secret');

        $build = $recaptcha->hostname($hostname = 'test_hostname')
            ->apkPackageName($apkPackageName = 'test_apk_package_name')
            ->action($action = 'test_action')
            ->threshold($threshold = 0.7)
            ->challengeTs($challengeTs = rand(1, 120));

        $this->assertInstanceOf(ReCaptcha::class, $build);

        $array = [
            'hostname' => $hostname,
            'apk_package_name' => $apkPackageName,
            'action' => $action,
            'threshold' => $threshold,
            'challenge_ts' => $challengeTs,
        ];

        $this->assertEquals($array, $recaptcha->getConstraints());
        $this->assertEquals($array, $build->getConstraints());
    }

    public function testFlushesConstraints()
    {
        $recaptcha = ReCaptcha::make('test_secret')
            ->hostname($hostname = 'test_hostname')
            ->apkPackageName($apkPackageName = 'test_apk_package_name')
            ->action($action = 'test_action')
            ->threshold($threshold = 0.7)
            ->challengeTs($challengeTs = rand(1, 120));

        $this->assertEquals(ReCaptcha::CONSTRAINTS_ARRAY, $recaptcha->flushConstraints()->getConstraints());
    }

    public function testSanitizesAction()
    {
        $action = '/unsanitized@action/to-test_here?foo=bar&quz=qux';

        $recaptcha = ReCaptcha::make('test_secret')
            ->saneAction($action);

        $this->assertEquals('/unsanitizedaction/to_test_here', $recaptcha->getConstraints()['action']);
    }

    public function testConstraintsErrors()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'success' => true,
            'error-codes' => [],
            'hostname' => 'test.local.com',
            'challenge_ts' => '1970-01-01T00:00:01Z',
            'apk_package_name' => 'test_apk',
            'score' => 0.5,
            'action' => 'test_action',
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->hostname('invalid')
            ->apkPackageName('invalid')
            ->challengeTs(1)
            ->threshold(0.9)
            ->action('invalid')
            ->verify('test_token', '255.255.255.255');

        $errors = [
            ReCaptchaErrors::E_HOSTNAME_MISMATCH,
            ReCaptchaErrors::E_APK_PACKAGE_NAME_MISMATCH,
            ReCaptchaErrors::E_ACTION_MISMATCH,
            ReCaptchaErrors::E_SCORE_THRESHOLD_NOT_MET,
            ReCaptchaErrors::E_CHALLENGE_TIMEOUT,
        ];

        $this->assertEquals($errors, $response->error_codes);
        $this->assertFalse($response->valid());
    }

    public function testInvalidJsonResponse()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->hostname('invalid')
            ->apkPackageName('invalid')
            ->challengeTs(1)
            ->threshold(0.9)
            ->action('invalid')
            ->verify('test_token', '255.255.255.255');

        $this->assertEquals([ReCaptchaErrors::E_INVALID_JSON], $response->error_codes);
        $this->assertFalse($response->valid());
    }

    public function testUnknownError()
    {
        $client = $this->createMock(ClientInterface::class);

        $array = [
            'invalid' => 'json'
        ];

        $stream = $this->factory->createStream(json_encode($array));

        $stream->rewind();

        $client->method('sendRequest')
            ->with(new IsInstanceOf(RequestInterface::class))
            ->willReturn($this->factory->createResponse()->withBody($stream)->withStatus(200, 'OK'));

        $response = ReCaptcha::make('test_secret')->setClient($client)
            ->hostname('invalid')
            ->apkPackageName('invalid')
            ->challengeTs(1)
            ->threshold(0.9)
            ->action('invalid')
            ->verify('test_token', '255.255.255.255');

        $this->assertEquals([ReCaptchaErrors::E_UNKNOWN_ERROR], $response->error_codes);
        $this->assertFalse($response->valid());
    }
}
