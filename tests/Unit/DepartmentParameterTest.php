<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: Department Parameter Tests
 */
class DepartmentParameterTest extends TestCase {

    protected function setUp(): void {
        // Reset mock data
        Dept::$mockData = [];
    }

    public function testAcceptsValidDepartmentId(): void {
        // Setup mock department
        Dept::$mockData[5] = new Dept(['id' => 5, 'name' => 'Support', 'active' => true]);

        $controller = $this->createController();
        $result = $controller->validateDepartmentId(5);

        $this->assertEquals(5, $result);
    }

    public function testRejectsInvalidDepartmentId(): void {
        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $controller->validateDepartmentId(999);
    }

    public function testRejectsInactiveDepartment(): void {
        // Setup inactive department
        Dept::$mockData[10] = new Dept(['id' => 10, 'name' => 'Closed', 'active' => false]);

        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $controller->validateDepartmentId(10);
    }

    private function createController() {
        require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';
        return new ExtendedTicketApiController();
    }
}
