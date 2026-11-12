<?php

namespace PierreGranger\Tests;

use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeCore;
use PierreGranger\ApidaeException;

class ApidaeCoreSecurityTest extends TestCase
{
    public function testTokenIsCachedUntilExpiry(): void
    {
        $core = new ApidaeCoreTestable();
        $core->mockRequestResult = ['code' => 200, 'access_token' => 'tok', 'expires_in' => 3600];

        $core->gimme_token('client', 'secret');
        $core->gimme_token('client', 'secret');

        $this->assertSame(1, $core->requestCallCount);
    }

    public function testExpiredTokenIsRenewed(): void
    {
        $core = new ApidaeCoreTestable();
        // expires_in inférieur à la marge de sécurité : le token est considéré comme déjà expiré
        $core->mockRequestResult = ['code' => 200, 'access_token' => 'tok', 'expires_in' => 30];

        $core->gimme_token('client', 'secret');
        $core->gimme_token('client', 'secret');

        $this->assertSame(2, $core->requestCallCount);
    }

    public function testForgetTokenForcesRenewal(): void
    {
        $core = new ApidaeCoreTestable();
        $core->mockRequestResult = ['code' => 200, 'access_token' => 'tok'];

        $core->gimme_token('client', 'secret');
        $core->forgetToken('client');
        $core->gimme_token('client', 'secret');
        $core->forgetToken();
        $core->gimme_token('client', 'secret');

        $this->assertSame(3, $core->requestCallCount);
    }

    public function testSanitizeCurlOptsMasksSecrets(): void
    {
        $opts = ApidaeCore::sanitizeCurlOpts([
            CURLOPT_URL => 'https://api.apidae-tourisme.com/oauth/token',
            CURLOPT_USERPWD => 'client:super-secret',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer abc.def'],
            CURLOPT_POSTFIELDS => ['mode' => 'MODIFICATION', 'tokenSSO' => 'sso-token'],
        ]);

        $dump = json_encode($opts);
        $this->assertStringNotContainsString('super-secret', $dump);
        $this->assertStringNotContainsString('abc.def', $dump);
        $this->assertStringNotContainsString('sso-token', $dump);
        $this->assertSame('MODIFICATION', $opts[CURLOPT_POSTFIELDS]['mode']);
        $this->assertSame('https://api.apidae-tourisme.com/oauth/token', $opts[CURLOPT_URL]);
    }

    public function testSanitizePostfieldsHandlesQueryStrings(): void
    {
        $sanitized = ApidaeCore::sanitizePostfields('grant_type=authorization_code&code=xyz&redirect_uri=https%3A%2F%2Fexample.org');

        $this->assertStringNotContainsString('xyz', $sanitized);
        $this->assertStringContainsString('grant_type=authorization_code', $sanitized);
    }

    public function testNetworkErrorDoesNotLeakSecretsInException(): void
    {
        // Port fermé : curl échoue immédiatement, ce qui déclenche l'exception NO_RESPONSE
        $core = new ApidaeCoreTestable(['url_api' => 'http://127.0.0.1:1/']);

        try {
            $core->gimme_token('client', 'super-secret');
            $this->fail('Une exception était attendue');
        } catch (ApidaeException $e) {
            $this->assertSame(ApidaeException::NO_RESPONSE, $e->getCode());
            $this->assertStringNotContainsString('super-secret', json_encode($e->getDetails()));
            $this->assertStringNotContainsString('super-secret', (string) $e);
        }
    }
}
