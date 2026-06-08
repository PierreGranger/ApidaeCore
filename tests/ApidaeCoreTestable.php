<?php

namespace PierreGranger\Tests;

use PierreGranger\ApidaeCore;

/**
 * Sous-classe de test exposant les méthodes protégées et permettant de mocker request().
 */
class ApidaeCoreTestable extends ApidaeCore
{
    public ?array $mockRequestResult = null;

    public int $requestCallCount = 0;

    public ?string $lastRequestPath = null;

    public ?array $lastRequestParams = null;

    protected function request(string $path, $params = null)
    {
        if ($this->mockRequestResult !== null) {
            $this->requestCallCount++;
            $this->lastRequestPath = $path;
            $this->lastRequestParams = $params;
            if (isset($params['POSTFIELDS'])) {
                $this->lastPostfields = $params['POSTFIELDS'];
            }
            $this->lastResult = $this->mockRequestResult;

            return $this->mockRequestResult;
        }

        return parent::request($path, $params);
    }

    public function callRequest(string $path, ?array $params = null): array
    {
        return $this->request($path, $params);
    }

    public function callIsJson(mixed $string): bool
    {
        return $this->isJson($string);
    }
}
