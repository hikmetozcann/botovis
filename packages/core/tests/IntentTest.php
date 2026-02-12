<?php

declare(strict_types=1);

namespace Botovis\Core\Tests;

use PHPUnit\Framework\TestCase;
use Botovis\Core\Enums\ActionType;
use Botovis\Core\Enums\IntentType;
use Botovis\Core\Intent\ResolvedIntent;

class IntentTest extends TestCase
{
    public function test_action_intent_is_actionable(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::ACTION,
            action: ActionType::CREATE,
            table: 'products',
            data: ['name' => 'Test'],
        );

        $this->assertTrue($intent->isAction());
        $this->assertTrue($intent->requiresConfirmation());
    }

    public function test_read_action_does_not_require_confirmation(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::ACTION,
            action: ActionType::READ,
            table: 'products',
        );

        $this->assertTrue($intent->isAction());
        $this->assertFalse($intent->requiresConfirmation());
    }

    public function test_question_intent_is_not_actionable(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::QUESTION,
            message: 'What tables do I have?',
        );

        $this->assertFalse($intent->isAction());
        $this->assertFalse($intent->requiresConfirmation());
    }

    public function test_confirmation_message_for_create(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::ACTION,
            action: ActionType::CREATE,
            table: 'employees',
            data: ['first_name' => 'Ahmet', 'base_salary' => 15000],
        );

        $msg = $intent->toConfirmationMessage();
        $this->assertStringContainsString('Yeni kayıt oluştur', $msg);
        $this->assertStringContainsString('employees', $msg);
        $this->assertStringContainsString('Ahmet', $msg);
    }

    public function test_confirmation_message_for_update_with_where(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::ACTION,
            action: ActionType::UPDATE,
            table: 'employees',
            data: ['base_salary' => 20000],
            where: ['first_name' => 'Ahmet'],
        );

        $msg = $intent->toConfirmationMessage();
        $this->assertStringContainsString('Kayıt güncelle', $msg);
        $this->assertStringContainsString('first_name = Ahmet', $msg);
    }

    public function test_to_array(): void
    {
        $intent = new ResolvedIntent(
            type: IntentType::ACTION,
            action: ActionType::READ,
            table: 'employees',
            where: ['is_active' => true],
            confidence: 0.95,
        );

        $arr = $intent->toArray();
        $this->assertEquals('action', $arr['type']);
        $this->assertEquals('read', $arr['action']);
        $this->assertEquals('employees', $arr['table']);
        $this->assertEquals(0.95, $arr['confidence']);
    }
}
