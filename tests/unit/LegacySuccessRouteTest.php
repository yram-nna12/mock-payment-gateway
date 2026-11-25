<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class LegacySuccessRouteTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testLegacySuccessEndpointIsNotRouted(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->post('/checkout/synthetic-reference/synthetic-token/success');
    }
}
