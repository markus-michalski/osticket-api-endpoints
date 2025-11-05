<?php

use PHPUnit\Framework\TestCase;

/**
 * RED Phase: ParentTicket Parameter Tests (Subtickets)
 */
class ParentTicketParameterTest extends TestCase {

    protected function setUp(): void {
        Ticket::$mockData = [];
    }

    public function testAcceptsValidParentTicketId(): void {
        // Parent ticket (not a child)
        Ticket::$mockData[100] = new Ticket([
            'id' => 100,
            'number' => 'TICKET-100',
            'dept_id' => 2,
            'is_child' => false
        ]);

        $controller = $this->createController();
        $result = $controller->validateParentTicketId(100);

        $this->assertEquals(100, $result);
    }

    public function testRejectsNonExistentParentTicket(): void {
        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $controller->validateParentTicketId(999);
    }

    public function testRejectsParentThatIsChild(): void {
        // Child ticket (already has parent)
        Ticket::$mockData[200] = new Ticket([
            'id' => 200,
            'number' => 'TICKET-200',
            'is_child' => true
        ]);

        $controller = $this->createController();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $controller->validateParentTicketId(200);
    }

    private function createController() {
        require_once __DIR__ . '/../../controllers/ExtendedTicketApiController.php';
        return new ExtendedTicketApiController();
    }
}
