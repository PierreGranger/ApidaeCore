<?php

namespace PierreGranger\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeCore;
use PierreGranger\ApidaeException;

class ApidaeCoreTest extends TestCase
{
    public function testConstructDefaultEnvIsProd(): void
    {
        $core = new ApidaeCore();

        $this->assertSame('prod', $core->getEnv());
        $this->assertSame('https://api.apidae-tourisme.com/', $core->url_api());
        $this->assertSame('https://base.apidae-tourisme.com/', $core->url_base());
    }

    #[DataProvider('validEnvProvider')]
    public function testConstructWithValidEnv(string $env, string $expectedApi, string $expectedBase): void
    {
        $core = new ApidaeCore(['env' => $env]);

        $this->assertSame($env, $core->getEnv());
        $this->assertSame($expectedApi, $core->url_api());
        $this->assertSame($expectedBase, $core->url_base());
    }

    public static function validEnvProvider(): array
    {
        return [
            'local' => ['local', 'http://localhost:8080/', 'http://localhost:8080/'],
            'prod' => ['prod', 'https://api.apidae-tourisme.com/', 'https://base.apidae-tourisme.com/'],
            'dev' => ['dev', 'https://api.apidae-tourisme.dev/', 'https://base.apidae-tourisme.dev/'],
            'cooking' => ['cooking', 'https://api.apidae-tourisme.cooking/', 'https://base.apidae-tourisme.cooking/'],
        ];
    }

    public function testConstructWithInvalidEnvThrowsException(): void
    {
        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::NO_PROD);

        new ApidaeCore(['env' => 'invalid']);
    }

    public function testConstructWithTypeProdAlias(): void
    {
        $core = new ApidaeCore(['type_prod' => 'dev']);

        $this->assertSame('dev', $core->getEnv());
    }

    public function testConstructWithCustomUrls(): void
    {
        $core = new ApidaeCore([
            'url_api' => 'https://custom-api.example/',
            'url_base' => 'https://custom-base.example/',
        ]);

        $this->assertSame('https://custom-api.example/', $core->url_api());
        $this->assertSame('https://custom-base.example/', $core->url_base());
    }

    public function testSetTimeout(): void
    {
        $core = new ApidaeCore();

        $core->setTimeout(30);
        $this->assertSame(30, $this->getProtectedProperty($core, 'timeout'));

        $core->setTimeout(5);
        $this->assertSame(30, $this->getProtectedProperty($core, 'timeout'));

        $core->setTimeout(600);
        $this->assertSame(30, $this->getProtectedProperty($core, 'timeout'));

        $core->setTimeout(120);
        $this->assertSame(120, $this->getProtectedProperty($core, 'timeout'));
    }

    public function testGimmeTokenThrowsWhenMissingCredentials(): void
    {
        $core = new ApidaeCoreTestable();

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::MISSING_PARAMETER);

        $core->gimme_token('', '');
    }

    public function testGimmeTokenReturnsAccessTokenAndCachesIt(): void
    {
        $core = new ApidaeCoreTestable(['timer' => false]);
        $core->projet_ecriture_clientId = 'client-id';
        $this->setProtectedProperty($core, 'projet_ecriture_secret', 'secret');
        $core->mockRequestResult = [
            'code' => 200,
            'access_token' => 'token-abc',
            'body' => '{"access_token":"token-abc"}',
        ];

        $token = $core->gimme_token();
        $this->assertSame('token-abc', $token);
        $this->assertSame(1, $core->requestCallCount);
        $this->assertSame('/oauth/token', $core->lastRequestPath);

        $cachedToken = $core->gimme_token();
        $this->assertSame('token-abc', $cachedToken);
        $this->assertSame(1, $core->requestCallCount);
    }

    public function testGimmeTokenThrowsOnInvalidHttpCode(): void
    {
        $core = new ApidaeCoreTestable();
        $core->mockRequestResult = [
            'code' => 401,
            'body' => '{"error":"invalid_client"}',
        ];

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_TOKEN);

        $core->gimme_token('client', 'secret');
    }

    public function testRequestThrowsOnInvalidPath(): void
    {
        $core = new ApidaeCoreTestable();

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_PARAMETER);

        $core->callRequest('not-a-valid-path');
    }

    #[DataProvider('validRequestPathProvider')]
    public function testRequestAcceptsValidPaths(string $path): void
    {
        $core = new ApidaeCoreTestable();
        $core->mockRequestResult = ['code' => 200, 'body' => '{}'];

        $result = $core->callRequest($path);

        $this->assertSame(200, $result['code']);
    }

    public static function validRequestPathProvider(): array
    {
        return [
            'oauth' => ['/oauth/token'],
            'api v002' => ['/api/v002/objets/touristiques/123'],
            'simple path' => ['/api/v002/utilisateurs'],
        ];
    }

    #[DataProvider('isJsonProvider')]
    public function testIsJson(mixed $value, bool $expected): void
    {
        $core = new ApidaeCoreTestable();

        $this->assertSame($expected, $core->callIsJson($value));
    }

    public static function isJsonProvider(): array
    {
        return [
            'valid object' => ['{"foo":"bar"}', true],
            'valid array' => ['[1,2,3]', true],
            'invalid' => ['not json', false],
            'empty string' => ['', false],
        ];
    }

    public function testAlerteThrowsOnInvalidAdminEmail(): void
    {
        $core = new ApidaeCore(['mail_admin' => 'not-an-email']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('mail admin incorrect');

        $core->alerte('Sujet', 'Message');
    }

    public function testAlerteThrowsOnInvalidMailto(): void
    {
        $core = new ApidaeCore(['mail_admin' => 'admin@example.com']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('mail to incorrect');

        $core->alerte('Sujet', 'Message', 'invalid-email');
    }

    public function testLastResultAndLastPostfields(): void
    {
        $core = new ApidaeCoreTestable();
        $core->mockRequestResult = [
            'code' => 200,
            'body' => '{"status":"ok"}',
            'status' => 'ok',
        ];

        $core->callRequest('/oauth/token', ['POSTFIELDS' => 'grant_type=client_credentials']);

        $this->assertSame('ok', $core->lastResult()['status']);
        $this->assertSame('grant_type=client_credentials', $core->lastPostfields());
        $this->assertSame('grant_type=client_credentials', $core->lastRequest());
    }

    public function testIdApidaeContainsExpectedMembers(): void
    {
        $this->assertSame([1, 1157], ApidaeCore::$idApidae);
    }

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
