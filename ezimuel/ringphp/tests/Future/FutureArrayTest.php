<?php
namespace GuzzleHttp\Tests\Ring\Future;

use GuzzleHttp\Ring\Future\FutureArray;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;

class FutureArrayTest extends TestCase
{
    public function testLazilyCallsDeref()
    {
        $c = false;
        $deferred = new Deferred();
        $f = new FutureArray(
            $deferred->promise(),
            function () use (&$c, $deferred) {
                $c = true;
                $deferred->resolve(['status' => 200]);
            }
        );
        $this->assertFalse($c);
        $this->assertEquals(200, $f['status']);
        $this->assertTrue($c);
    }

    public function testActsLikeArray()
    {
        $deferred = new Deferred();
        $f = new FutureArray(
            $deferred->promise(),
            function () use (&$c, $deferred) {
                $deferred->resolve(['status' => 200]);
            }
        );

        $this->assertTrue(isset($f['status']));
        $this->assertEquals(200, $f['status']);
        $this->assertEquals(['status' => 200], $f->wait());
        $this->assertEquals(1, count($f));
        $f['baz'] = 10;
        $this->assertEquals(10, $f['baz']);
        unset($f['baz']);
        $this->assertFalse(isset($f['baz']));
        $this->assertEquals(['status' => 200], iterator_to_array($f));
    }

    public function testThrowsWhenAccessingInvalidProperty()
    {
        $this->expectException(\RuntimeException::class);

        $deferred = new Deferred();
        $f = new FutureArray($deferred->promise(), function () {});
        $f->foo;
    }
}
