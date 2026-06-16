<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../includes/radius_utils.php';

class RadiusUtilsTest extends TestCase
{
    #[DataProvider('radiusReasonDataProvider')]
    public function testRadiusReason($reply, $pass, $expectedReason)
    {
        $this->assertEquals($expectedReason, radius_reason($reply, $pass));
    }

    public static function radiusReasonDataProvider()
    {
        return [
            'Access-Accept' => [
                'Access-Accept',
                'some_pass',
                'Login successful'
            ],
            'Access-Reject with password' => [
                'Access-Reject',
                'wrong_pass',
                'Authentication rejected (wrong password / MAC lock )'
            ],
            'Access-Reject with empty password' => [
                'Access-Reject',
                '',
                'Password not provided'
            ],
            'Access-Reject with null password' => [
                'Access-Reject',
                null,
                'Password not provided'
            ],
            'Unknown result' => [
                'Some-Other-Status',
                'any_pass',
                'Unknown result'
            ],
        ];
    }
}
