<?php

use PHPUnit\Framework\TestCase;

require_once 'hotspot/includes/plan_manager.php';

class PlanManagerTest extends TestCase
{
    private $mysqli;
    private $planManager;

    protected function setUp(): void
    {
        $this->mysqli = $this->createMock(mysqli::class);
        $this->planManager = new PlanManager($this->mysqli);
    }

    public function testRechargeUserNotFound()
    {
        $userId = 123;
        $amount = 50.0;

        $resultMock = $this->createStub(mysqli_result::class);
        $resultMock->method('fetch_assoc')->willReturn(null);

        $this->mysqli->expects($this->once())
            ->method('query')
            ->with($this->stringContains("SELECT * FROM hotspot_users WHERE id = $userId"))
            ->willReturn($resultMock);

        $response = $this->planManager->recharge($userId, $amount);

        $this->assertEquals('error', $response['status']);
        $this->assertEquals('User not found', $response['message']);
    }

    public function testRechargeSuccess()
    {
        $userId = 123;
        $amount = 100.0;
        $initialBalance = 50.0;
        $expectedBalance = 150.0;

        $userResultMock = $this->createStub(mysqli_result::class);
        $userResultMock->method('fetch_assoc')->willReturn([
            'id' => $userId,
            'current_balance' => $initialBalance
        ]);

        // Using a callback to handle multiple queries with different expectations
        $this->mysqli->expects($this->exactly(3))
            ->method('query')
            ->willReturnCallback(function($query) use ($userId, $userResultMock, $expectedBalance, $amount) {
                if (strpos($query, "SELECT * FROM hotspot_users") !== false) {
                    return $userResultMock;
                }
                if (strpos($query, "UPDATE hotspot_users") !== false) {
                    $this->assertStringContainsString("current_balance = $expectedBalance", $query);
                    $this->assertStringContainsString("id = $userId", $query);
                    return true;
                }
                if (strpos($query, "INSERT INTO hotspot_invoices") !== false) {
                    $this->assertStringContainsString("Account Recharge", $query);
                    $this->assertStringContainsString((string)$amount, $query);
                    return true;
                }
                return false;
            });

        $response = $this->planManager->recharge($userId, $amount);

        $this->assertEquals('success', $response['status']);
        $this->assertEquals($expectedBalance, $response['new_balance']);
        $this->assertStringContainsString("Added Rs.{$amount}", $response['message']);
    }
}
