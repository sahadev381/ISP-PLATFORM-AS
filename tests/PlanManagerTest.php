<?php
use PHPUnit\Framework\TestCase;

require_once 'hotspot/includes/plan_manager.php';

class PlanManagerTest extends TestCase
{
    private $db;
    private $planManager;

    protected function setUp(): void
    {
        $this->db = $this->createMock(mysqli::class);
        $this->planManager = new PlanManager($this->db);
    }

    public function testGetAllPlansDefault()
    {
        $result = $this->createMock(mysqli_result::class);

        $plans = [
            ['id' => 1, 'name' => 'Plan 1', 'type' => 'prepaid', 'price' => 100, 'status' => 'active'],
            ['id' => 2, 'name' => 'Plan 2', 'type' => 'postpaid', 'price' => 200, 'status' => 'active']
        ];

        $result->expects($this->exactly(3))
            ->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($plans[0], $plans[1], null);

        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("WHERE 1=1 AND status = 'active' ORDER BY type, price"))
            ->willReturn($result);

        $actualPlans = $this->planManager->getAllPlans();

        $this->assertCount(2, $actualPlans);
        $this->assertEquals($plans, $actualPlans);
    }

    public function testGetAllPlansWithType()
    {
        $result = $this->createMock(mysqli_result::class);

        $plans = [
            ['id' => 1, 'name' => 'Prepaid Plan', 'type' => 'prepaid', 'price' => 100, 'status' => 'active']
        ];

        $result->expects($this->exactly(2))
            ->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($plans[0], null);

        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("AND type = 'prepaid' AND status = 'active'"))
            ->willReturn($result);

        $actualPlans = $this->planManager->getAllPlans('prepaid');

        $this->assertCount(1, $actualPlans);
        $this->assertEquals('prepaid', $actualPlans[0]['type']);
    }

    public function testGetAllPlansWithStatus()
    {
        $result = $this->createMock(mysqli_result::class);

        $plans = [
            ['id' => 3, 'name' => 'Inactive Plan', 'type' => 'prepaid', 'price' => 50, 'status' => 'inactive']
        ];

        $result->expects($this->exactly(2))
            ->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($plans[0], null);

        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("AND status = 'inactive'"))
            ->willReturn($result);

        $actualPlans = $this->planManager->getAllPlans(null, 'inactive');

        $this->assertCount(1, $actualPlans);
        $this->assertEquals('inactive', $actualPlans[0]['status']);
    }

    public function testGetAllPlansWithTypeAndStatus()
    {
        $result = $this->createMock(mysqli_result::class);

        $plans = [
            ['id' => 4, 'name' => 'Postpaid Inactive', 'type' => 'postpaid', 'price' => 150, 'status' => 'inactive']
        ];

        $result->expects($this->exactly(2))
            ->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($plans[0], null);

        $this->db->expects($this->once())
            ->method('query')
            ->with($this->stringContains("AND type = 'postpaid' AND status = 'inactive'"))
            ->willReturn($result);

        $actualPlans = $this->planManager->getAllPlans('postpaid', 'inactive');

        $this->assertCount(1, $actualPlans);
        $this->assertEquals('postpaid', $actualPlans[0]['type']);
        $this->assertEquals('inactive', $actualPlans[0]['status']);
    }

    public function testGetAllPlansEmpty()
    {
        $result = $this->createMock(mysqli_result::class);

        $result->expects($this->once())
            ->method('fetch_assoc')
            ->willReturn(null);

        $this->db->expects($this->once())
            ->method('query')
            ->willReturn($result);

        $actualPlans = $this->planManager->getAllPlans('nonexistent');

        $this->assertEmpty($actualPlans);
    }
}
