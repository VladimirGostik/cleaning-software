<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Services\Push\FirebasePushSender;
use App\Services\Push\PushMessage;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery\Expectation;
use Tests\TestCase;
use Throwable;

final class FirebasePushSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['firebase.projects.app.credentials' => '/tmp/fake-credentials.json']);
    }

    private function message(): PushMessage
    {
        return new PushMessage('t', 'b', '/notifications', 'tid', 'nid', 1);
    }

    /** @return Expectation */
    private function expectMulticast(): object
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(Messaging::class)->shouldReceive('sendMulticast');

        return $expectation;
    }

    /** @param list<string> $failing */
    private function report(mixed $token, array $failing): SendReport
    {
        $token = is_string($token) && $token !== '' ? $token : 'x';

        return in_array($token, $failing, true) ? $this->failed($token, NotFound::becauseTokenNotFound($token)) : $this->ok($token);
    }

    private function ok(string $token): SendReport
    {
        return SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $token ?: 'x'), ['name' => 'projects/x/messages/1']);
    }

    private function failed(string $token, Throwable $error): SendReport
    {
        /** @phpstan-ignore argument.type */
        return SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, $token ?: 'x'), $error);
    }

    public function test_tokens_are_chunked_at_500(): void
    {
        $tokens = array_map(static fn (int $i): string => "tok{$i}", range(1, 1001));
        $sizes = [];

        $this->expectMulticast()->times(3)->andReturnUsing(function (array $payload, array $chunk) use (&$sizes): MulticastSendReport {
            $sizes[] = count($chunk);

            return MulticastSendReport::withItems(array_map(fn (mixed $t): SendReport => $this->report($t, []), $chunk));
        });

        $this->assertSame([], $this->app->make(FirebasePushSender::class)->send($this->message(), $tokens));
        $this->assertSame([500, 500, 1], $sizes);
    }

    public function test_only_not_found_and_invalid_token_failures_are_reported_unusable(): void
    {
        $this->expectMulticast()->once()->andReturn(MulticastSendReport::withItems([
            $this->ok('good'),
            $this->failed('gone', NotFound::becauseTokenNotFound('gone')),
            $this->failed('bad', new InvalidArgument('The registration token is not a valid FCM registration token')),
            $this->failed('other-project', new SenderIdMismatch('SenderId mismatch')),
            $this->failed('bad-payload', new InvalidArgument('Invalid message payload')),
        ]));

        $unusable = $this->app->make(FirebasePushSender::class)->send($this->message(), ['good', 'gone', 'bad', 'other-project', 'bad-payload']);

        $this->assertEqualsCanonicalizing(['gone', 'bad'], $unusable);
    }

    public function test_unusable_tokens_are_merged_across_chunks(): void
    {
        $tokens = array_map(static fn (int $i): string => "tok{$i}", range(1, 501));

        $this->expectMulticast()->twice()->andReturnUsing(fn (array $payload, array $chunk): MulticastSendReport => MulticastSendReport::withItems(array_map(
            fn (mixed $t): SendReport => $this->report($t, ['tok1', 'tok501']),
            $chunk,
        )));

        $this->assertEqualsCanonicalizing(['tok1', 'tok501'], $this->app->make(FirebasePushSender::class)->send($this->message(), $tokens));
    }

    public function test_no_tokens_means_no_call(): void
    {
        $this->expectMulticast()->never();

        $this->assertSame([], $this->app->make(FirebasePushSender::class)->send($this->message(), []));
    }
}
