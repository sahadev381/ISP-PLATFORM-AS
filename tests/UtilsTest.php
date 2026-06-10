<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/utils.php';

class UtilsTest extends TestCase
{
    /**
     * @dataProvider formatBytesProvider
     */
    public function testFormatBytes($bytes, $precision, $expected)
    {
        $this->assertEquals($expected, formatBytes($bytes, $precision));
    }

    public static function formatBytesProvider()
    {
        return [
            'zero' => [0, 2, '0 B'],
            'bytes' => [512, 2, '512 B'],
            'KB' => [1024, 2, '1 KB'],
            'MB' => [1048576, 2, '1 MB'],
            'GB' => [1073741824, 2, '1 GB'],
            'TB' => [1099511627776, 2, '1 TB'],
            'Precision' => [1500, 2, '1.46 KB'],
            'Higher Precision' => [1500, 3, '1.465 KB'],
            'Negative' => [-100, 2, '0 B'],
            'PB' => [pow(1024, 5), 2, '1 PB'],
            'Just below KB' => [1023, 2, '1023 B'],
            'Float input' => [1024.5, 2, '1 KB'],
        ];
    }
}
