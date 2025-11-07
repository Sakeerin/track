<?php

namespace Tests\Unit;

use App\Models\EtaRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtaRuleModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_eta_rule()
    {
        // Arrange & Act
        $rule = EtaRule::factory()->create([
            'name' => 'Express Service Modifier',
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'express'],
            'adjustments' => ['multiplier' => 0.5],
            'priority' => 100,
            'active' => true,
        ]);

        // Assert
        $this->assertDatabaseHas('eta_rules', [
            'name' => 'Express Service Modifier',
            'rule_type' => 'service_modifier',
            'priority' => 100,
            'active' => true,
        ]);

        $this->assertEquals(['service_type' => 'express'], $rule->conditions);
        $this->assertEquals(['multiplier' => 0.5], $rule->adjustments);
    }

    /** @test */
    public function it_scopes_active_rules()
    {
        // Arrange
        EtaRule::factory()->create(['active' => true]);
        EtaRule::factory()->create(['active' => true]);
        EtaRule::factory()->create(['active' => false]);

        // Act
        $activeRules = EtaRule::active()->get();

        // Assert
        $this->assertCount(2, $activeRules);
        $this->assertTrue($activeRules->every(fn($rule) => $rule->active));
    }

    /** @test */
    public function it_scopes_rules_by_type()
    {
        // Arrange
        EtaRule::factory()->create(['rule_type' => 'service_modifier']);
        EtaRule::factory()->create(['rule_type' => 'service_modifier']);
        EtaRule::factory()->create(['rule_type' => 'holiday_adjustment']);

        // Act
        $serviceRules = EtaRule::ofType('service_modifier')->get();

        // Assert
        $this->assertCount(2, $serviceRules);
        $this->assertTrue($serviceRules->every(fn($rule) => $rule->rule_type === 'service_modifier'));
    }

    /** @test */
    public function it_orders_rules_by_priority()
    {
        // Arrange
        $lowPriority = EtaRule::factory()->create(['priority' => 10]);
        $highPriority = EtaRule::factory()->create(['priority' => 100]);
        $mediumPriority = EtaRule::factory()->create(['priority' => 50]);

        // Act
        $orderedRules = EtaRule::byPriority()->get();

        // Assert
        $this->assertEquals($highPriority->id, $orderedRules->first()->id);
        $this->assertEquals($lowPriority->id, $orderedRules->last()->id);
    }

    /** @test */
    public function it_evaluates_simple_equality_conditions()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => ['service_type' => 'express'],
            'active' => true,
        ]);

        $matchingContext = ['service_type' => 'express'];
        $nonMatchingContext = ['service_type' => 'standard'];

        // Act & Assert
        $this->assertTrue($rule->appliesTo($matchingContext));
        $this->assertFalse($rule->appliesTo($nonMatchingContext));
    }

    /** @test */
    public function it_evaluates_array_conditions()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => [
                'service_type' => ['in' => ['express', 'premium']],
                'pickup_hour' => ['gte' => 18],
            ],
            'active' => true,
        ]);

        $matchingContext = [
            'service_type' => 'express',
            'pickup_hour' => 19,
        ];

        $nonMatchingContext = [
            'service_type' => 'standard',
            'pickup_hour' => 19,
        ];

        // Act & Assert
        $this->assertTrue($rule->appliesTo($matchingContext));
        $this->assertFalse($rule->appliesTo($nonMatchingContext));
    }

    /** @test */
    public function it_evaluates_comparison_conditions()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => [
                'pickup_hour' => ['gte' => 18, 'lt' => 22],
            ],
            'active' => true,
        ]);

        $validContext = ['pickup_hour' => 19];
        $tooEarlyContext = ['pickup_hour' => 17];
        $tooLateContext = ['pickup_hour' => 22];

        // Act & Assert
        $this->assertTrue($rule->appliesTo($validContext));
        $this->assertFalse($rule->appliesTo($tooEarlyContext));
        $this->assertFalse($rule->appliesTo($tooLateContext));
    }

    /** @test */
    public function it_applies_hour_adjustments()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'adjustments' => ['hours' => 12],
        ]);

        $originalEta = Carbon::parse('2024-01-01 12:00:00');

        // Act
        $adjustedEta = $rule->applyAdjustments($originalEta);

        // Assert
        $expectedEta = Carbon::parse('2024-01-02 00:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $adjustedEta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_applies_day_adjustments()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'adjustments' => ['days' => 2],
        ]);

        $originalEta = Carbon::parse('2024-01-01 12:00:00');

        // Act
        $adjustedEta = $rule->applyAdjustments($originalEta);

        // Assert
        $expectedEta = Carbon::parse('2024-01-03 12:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $adjustedEta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_applies_multiplier_adjustments()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'adjustments' => ['multiplier' => 1.5],
        ]);

        // Set a fixed "now" time for consistent testing
        Carbon::setTestNow(Carbon::parse('2024-01-01 00:00:00'));
        $originalEta = Carbon::parse('2024-01-01 24:00:00'); // 24 hours from now

        // Act
        $adjustedEta = $rule->applyAdjustments($originalEta);

        // Assert
        // 24 hours * 1.5 = 36 hours from "now"
        $expectedEta = Carbon::parse('2024-01-02 12:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $adjustedEta->format('Y-m-d H:i:s'));

        // Clean up
        Carbon::setTestNow();
    }

    /** @test */
    public function it_ignores_inactive_rules()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => ['service_type' => 'express'],
            'active' => false,
        ]);

        $context = ['service_type' => 'express'];

        // Act & Assert
        $this->assertFalse($rule->appliesTo($context));
    }

    /** @test */
    public function it_handles_missing_context_values()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => ['service_type' => 'express', 'pickup_hour' => 18],
            'active' => true,
        ]);

        $incompleteContext = ['service_type' => 'express']; // Missing pickup_hour

        // Act & Assert
        $this->assertFalse($rule->appliesTo($incompleteContext));
    }

    /** @test */
    public function it_evaluates_not_in_conditions()
    {
        // Arrange
        $rule = EtaRule::factory()->create([
            'conditions' => [
                'service_type' => ['not_in' => ['economy', 'bulk']],
            ],
            'active' => true,
        ]);

        $validContext = ['service_type' => 'express'];
        $invalidContext = ['service_type' => 'economy'];

        // Act & Assert
        $this->assertTrue($rule->appliesTo($validContext));
        $this->assertFalse($rule->appliesTo($invalidContext));
    }
}