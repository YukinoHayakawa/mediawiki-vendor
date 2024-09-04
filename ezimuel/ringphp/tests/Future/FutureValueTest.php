<?php
namespace GuzzleHttp\Tests\Ring\Future;

use GuzzleHttp\Ring\Exception\CancelledFutureAccessException;
use GuzzleHttp\Ring\Future\FutureValue;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;

class FutureValueTest extends TestCase
{
    public function testDerefReturnsValue()
    {
        $called = 0;
        $deferred = new Deferred();

        $f = new FutureValue(
            $deferred->promise(),
            function () use ($deferred, &$called) {
                $called++;
                $deferred->resolve('foo');
            }
        );

        $this->assertEquals('foo', $f->wait());
        $this->assertEquals(1, $called);
        $this->assertEquals('foo', $f->wait());
        $this->assertEquals(1, $called);
        $f->cancel();
    }

    public function testThrowsWhenAccessingCancelled()
    {
        $this->expectException(\GuzzleHttp\Ring\Exception\CancelledFutureAccessException::class);

        $f = new FutureValue(
            (new Deferred())->promise(),
            function () {},
            function () { return true; }
        );
        $f->cancel();
        $f->wait();
    }

    public function testThrowsWhenDerefFailure()
    {
        $this->expectException(\OutOfBoundsException::class);

        $called = false;
        $deferred = new Deferred();
        $f = new FutureValue(
            $deferred->promise(),
            function () use(&$called) {
                $called = true;
            }
        );
        $deferred->reject(new \OutOfBoundsException());
        $f->wait();
        $this->assertFalse($called);
    }

    public function testThrowsWhenDerefDoesNotResolve()
    {
        $this->expectException(\GuzzleHttp\Ring\Exception\RingException::class);
        $this->expectExceptionMessage('Waiting did not resolve future');

        $deferred = new Deferred();
        $f = new FutureValue(
            $deferred->promise(),
            function () use(&$called) {
                $called = true;
            }
        );
        $f->wait();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testThrowingCancelledFutureAccessExceptionCancels()
    {
        $deferred = new Deferred();
        $f = new FutureValue(
            $deferred->promise(),
            function () use ($deferred) {
                throw new CancelledFutureAccessException();
            }
        );
        try {
            $f->wait();
            $this->fail('did not throw');
        } catch (CancelledFutureAccessException $e) {}
    }

    public function testThrowingExceptionInDerefMarksAsFailed()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        $deferred = new Deferred();
        $f = new FutureValue(
            $deferred->promise(),
            function () {
                throw new \Exception('foo');
            }
        );
        $f->wait();
    }
}
