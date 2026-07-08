<?php
use PHPUnit\Framework\TestCase;

require_once 'vendor/autoload.php';
require_once 'includes/bdcom_olt.php';

class BDCOMOLTTest extends TestCase
{
    protected function setUp(): void
    {
        if (!file_exists('config.php')) {
            file_put_contents('config.php', '<?php $conn = new stdClass();');
        }
    }

    protected function tearDown(): void
    {
        if (file_exists('config.php')) {
            unlink('config.php');
        }
    }

    public function testConnectTelnetFailure()
    {
        $olt_data = [
            'ip_address' => '127.0.0.1',
            'nasname' => 'test_olt'
        ];

        $olt = new BDCOM_OLT($olt_data);

        // Mock BDCOM_Telnet
        $telnetMock = $this->createMock(BDCOM_Telnet::class);
        $telnetMock->method('connect')
            ->willThrowException(new Exception("Connection refused"));

        // Use reflection to inject the mock and call the private method
        $reflection = new ReflectionClass($olt);

        $telnetProperty = $reflection->getProperty('telnet');
        $telnetProperty->setAccessible(true);
        $telnetProperty->setValue($olt, $telnetMock);

        $method = $reflection->getMethod('connectTelnet');
        $method->setAccessible(true);

        // Execute
        $result = $method->invoke($olt);

        // Verify
        $this->assertFalse($result, "connectTelnet should return false when an exception is thrown");
    }
}
