<?php


namespace Palzin\Tests;


use Palzin\Palzin;
use Palzin\Configuration;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
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
        $this->palzin->startTransaction('testcase');
    }

    public function testTransactionData()
    {
        $this->assertSame($this->palzin->transaction()::MODEL_NAME, $this->palzin->transaction()->model);
        $this->assertSame('request', $this->palzin->transaction()->setType('request')->type);
    }

    public function testSegmentData()
    {
        $segment = $this->palzin->startSegment(__FUNCTION__, 'hello segment!');

        $this->assertIsArray($segment->toArray());
        $this->assertSame($segment::MODEL_NAME, $segment->model);
        $this->assertSame(__FUNCTION__, $segment->type);
        $this->assertSame('hello segment!', $segment->label);
        $this->assertSame($this->palzin->transaction()->only(['name', 'hash', 'timestamp']), $segment->transaction);
        $this->assertArrayHasKey('host', $segment);
    }

    public function testErrorData()
    {
        $error = $this->palzin->reportException(new \Exception('test error'));
        $error_arr = $error->toArray();

        $this->assertArrayHasKey('message', $error_arr);
        $this->assertArrayHasKey('stack', $error_arr);
        $this->assertArrayHasKey('file', $error_arr);
        $this->assertArrayHasKey('line', $error_arr);
        $this->assertArrayHasKey('code', $error_arr);
        $this->assertArrayHasKey('class', $error_arr);
        $this->assertArrayHasKey('timestamp', $error_arr);
        $this->assertArrayHasKey('host', $error_arr);

        $this->assertSame($error::MODEL_NAME, $error->model);
        $this->assertSame($this->palzin->transaction()->only(['name', 'hash']), $error->transaction);
    }

    public function testSetContext()
    {
        $this->palzin->transaction()->addContext('test', ['foo' => 'bar']);

        $this->assertEquals(['test' => ['foo' => 'bar']], $this->palzin->transaction()->getContext());
    }


}
