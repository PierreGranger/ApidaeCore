<?php

namespace PierreGranger\Tests;

use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeTimer;

class ApidaeTimerTest extends TestCase
{
    public function testStartStopRecordsElapsedTime(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->start('main');
        $timer->pause(0.05);
        $timer->stop('main');

        $timelog = $this->getProtectedProperty($timer, 'timelog');

        $this->assertArrayHasKey('sub', $timelog);
        $this->assertArrayHasKey('main', $timelog['sub']);
        $this->assertArrayHasKey('time', $timelog['sub']['main']);
        $this->assertGreaterThanOrEqual(0.04, $timelog['sub']['main']['time']);
    }

    public function testNestedStartStop(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->start('parent');
        $timer->start('child');
        $timer->stop('child');
        $timer->stop('parent');

        $timelog = $this->getProtectedProperty($timer, 'timelog');

        $this->assertArrayHasKey('child', $timelog['sub']['parent']['sub']);
        $this->assertArrayHasKey('time', $timelog['sub']['parent']['sub']['child']);
    }

    public function testStopWithoutStartDoesNotFail(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->stop('orphan');

        $this->assertTrue(true);
    }

    public function testEndIsAliasOfStop(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->start('task');
        $timer->pause(0.01);
        $result = $timer->end('task');

        $this->assertNotFalse($result);

        $timelog = $this->getProtectedProperty($timer, 'timelog');
        $this->assertArrayHasKey('time', $timelog['sub']['task']);
    }

    public function testDisabledTimerDoesNothing(): void
    {
        $timer = new ApidaeTimer(false, false);

        $this->assertFalse($timer->start('main'));
        $this->assertFalse($timer->stop('main'));
        $this->assertFalse($timer->display());

        $timelog = $this->getProtectedProperty($timer, 'timelog');
        $this->assertEmpty($timelog);
    }

    public function testPoolRecordsOperations(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->start('op');
        $timer->stop('op');

        $pool = $this->getProtectedProperty($timer, 'pool');

        $this->assertSame(['start(op)', 'stop(op)'], $pool);
    }

    public function testStartStripsNewlinesFromTitle(): void
    {
        $timer = new ApidaeTimer(true, false);

        $timer->start("title\nwith\nnewlines");
        $timer->stop("title\nwith\nnewlines");

        $timelog = $this->getProtectedProperty($timer, 'timelog');

        $this->assertArrayHasKey('titlewithnewlines', $timelog['sub']);
    }

    public function testApidaeCoreTimerIntegration(): void
    {
        $core = new ApidaeCoreTestable(['timer' => true]);

        $core->start('test_method');
        $core->stop('test_method');

        ob_start();
        $core->timer();
        $output = ob_get_clean();

        $this->assertStringContainsString('console.log', $output);
        $this->assertStringContainsString('test_method', $output);
    }

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
