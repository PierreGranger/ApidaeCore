<?php

namespace PierreGranger\Tests;

use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeException;

class ApidaeExceptionTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame(0, ApidaeException::UNIDENTIFIED_ERROR);
        $this->assertSame(9, ApidaeException::MISSING_PARAMETER);
        $this->assertSame(12, ApidaeException::INVALID_TOKEN);
    }

    public function testGetDetails(): void
    {
        $details = ['debug' => true, 'result' => ['code' => 401]];
        $exception = new ApidaeException('erreur test', ApidaeException::INVALID_TOKEN, $details);

        $this->assertSame($details, $exception->getDetails());
        $this->assertSame('erreur test', $exception->getMessage());
        $this->assertSame(ApidaeException::INVALID_TOKEN, $exception->getCode());
    }

    public function testShowExceptionReturnsFormattedString(): void
    {
        $exception = new ApidaeException('no clientId', ApidaeException::MISSING_PARAMETER);

        $output = ApidaeException::showException($exception, false);

        $this->assertStringContainsString('ApidaeException', $output);
        $this->assertStringContainsString('MISSING_PARAMETER', $output);
        $this->assertStringContainsString('no clientId', $output);
    }

    public function testShowExceptionWithDetails(): void
    {
        $exception = new ApidaeException('invalid token', ApidaeException::INVALID_TOKEN, [
            'debug' => true,
            'result' => ['code' => 401],
        ]);

        $output = ApidaeException::showException($exception, false);

        $this->assertStringContainsString('INVALID_TOKEN', $output);
        $this->assertStringContainsString('"code": 401', $output);
    }

    public function testToString(): void
    {
        $exception = new ApidaeException('test message', ApidaeException::NO_JSON);

        $this->assertStringContainsString('NO_JSON', (string) $exception);
        $this->assertStringContainsString('test message', (string) $exception);
    }
}
