<?php
use PHPUnit\Framework\TestCase;

require_once 'hotspot/includes/plan_manager.php';

// Stub classes to help with mocking since internal mysqli classes can be tricky
class MockMysqliResult {
    public $num_rows = 0;
    public function fetch_assoc() { return null; }
}

class PlanManagerTest extends TestCase
{
    private $conn;
    private $planManager;

    protected function setUp(): void
    {
        $this->conn = $this->getMockBuilder(stdClass::class)
            ->addMethods(['query'])
            ->getMock();
        $this->planManager = new PlanManager($this->conn);
    }

    public function testGenerateVouchersReturnsErrorIfTypeNotFound()
    {
        $this->conn->method('query')->willReturn(null);

        $result = $this->planManager->generateVouchers(1);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Voucher type not found', $result['message']);
    }

    public function testGenerateVouchersSuccessWithProfileId()
    {
        $voucherType = [
            'id' => 1,
            'validity_days' => 30,
            'type' => 'data_topup'
        ];

        $resultMock = $this->getMockBuilder(MockMysqliResult::class)
            ->getMock();
        $resultMock->method('fetch_assoc')->willReturn($voucherType);

        $this->conn->method('query')->willReturn($resultMock);

        $result = $this->planManager->generateVouchers(1, 1, 100);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['count']);
        $this->assertCount(1, $result['vouchers']);
    }

    public function testGenerateVouchersSuccessWithoutProfileIdDataTopup()
    {
        $voucherType = [
            'id' => 1,
            'validity_days' => 30,
            'type' => 'data_topup'
        ];

        $profile = ['id' => 50];

        $resultMockType = $this->getMockBuilder(MockMysqliResult::class)->getMock();
        $resultMockType->method('fetch_assoc')->willReturn($voucherType);

        $resultMockProfile = $this->getMockBuilder(MockMysqliResult::class)->getMock();
        $resultMockProfile->method('fetch_assoc')->willReturn($profile);
        $resultMockProfile->num_rows = 1;

        $this->conn->method('query')
            ->willReturnOnConsecutiveCalls($resultMockType, $resultMockProfile, true);

        $result = $this->planManager->generateVouchers(1, 1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['count']);
    }

    public function testGenerateVouchersSuccessWithoutProfileIdTimeType()
    {
        $voucherType = [
            'id' => 1,
            'validity_days' => 7,
            'type' => 'time_extend'
        ];

        $profile = ['id' => 60];

        $resultMockType = $this->getMockBuilder(MockMysqliResult::class)->getMock();
        $resultMockType->method('fetch_assoc')->willReturn($voucherType);

        $resultMockProfile = $this->getMockBuilder(MockMysqliResult::class)->getMock();
        $resultMockProfile->method('fetch_assoc')->willReturn($profile);
        $resultMockProfile->num_rows = 1;

        $this->conn->method('query')
            ->willReturnOnConsecutiveCalls($resultMockType, $resultMockProfile, true);

        $result = $this->planManager->generateVouchers(1, 1);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['count']);
    }
}
