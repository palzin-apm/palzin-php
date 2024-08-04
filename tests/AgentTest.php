<?php

namespace Palzin\Tests;


use Palzin\Palzin;
use Palzin\Configuration;
use Palzin\Models\Segment;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    /**
     * @var Palzin
     */
    public $palzin;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @throws \Exception
     */
    public function setUp(): void
    {
        $configuration = new Configuration('example-api-key');
        $configuration->setEnabled(false);

        $this->palzin = new Palzin($configuration);
        $this->palzin->startTransaction('transaction-test');
    }

    /**
     * @throws \Palzin\Exceptions\PalzinException
     */
    public function testPalzinInstance()
    {
        $this->assertInstanceOf(Palzin::class, $this->palzin);
    }

    public function testAddEntry()
    {
        $this->assertInstanceOf(
            Palzin::class,
            $this->palzin->addEntries($this->palzin->startSegment('segment-test'))
        );

        $this->assertInstanceOf(
            Palzin::class,
            $this->palzin->addEntries([$this->palzin->startSegment('segment-test')])
        );
    }

    public function testCallbackThrow()
    {
        $this->expectException(\Exception::class);

        $this->palzin->addSegment(function () {
            throw new \Exception('Error in segment');
        }, 'callback', 'test exception throw', true);
    }

    public function testCallbackReturn()
    {
        $return = $this->palzin->addSegment(function () {
            return 'Hello!';
        }, 'callback', 'test callback');

        $this->assertSame('Hello!', $return);
    }

    public function testAddSegmentWithInput()
    {
        $this->palzin->addSegment(function ($segment) {
            $this->assertInstanceOf(Segment::class, $segment);
        }, 'callback', 'test callback', true);
    }

    public function testAddSegmentWithInputContext()
    {
        $segment = $this->palzin->addSegment(function ($segment) {
            return $segment->setContext(['foo' => 'bar']);
        }, 'callback', 'test callback', true);

        $this->assertEquals(['foo' => 'bar'], $segment->getContext());
    }


    public function testStatusChecks()
    {
        $this->assertFalse($this->palzin->isRecording());
        $this->assertFalse($this->palzin->needTransaction());
        $this->assertFalse($this->palzin->canAddSegments());

        $this->assertInstanceOf(Palzin::class, $this->palzin->startRecording());
        $this->assertTrue($this->palzin->isRecording());
        $this->assertTrue($this->palzin->canAddSegments());
    }
}
