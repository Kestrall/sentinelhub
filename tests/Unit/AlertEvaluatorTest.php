<?php

namespace App\Tests\Unit;

use App\Entity\AlertRule;
use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use App\Enum\Metric;
use App\Enum\Operator;
use App\Enum\Severity;
use App\Service\AlertEvaluator;
use PHPUnit\Framework\TestCase;

final class AlertEvaluatorTest extends TestCase
{
    private Device $device;
    private AlertEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->device = (new Device(new Site(new User())))->setName('Rack');
        $this->device->addRule(new AlertRule($this->device, Metric::Temperature, Operator::GreaterThan, 38, Severity::Critical, cooldownMinutes: 10));
        $this->device->addRule(new AlertRule($this->device, Metric::Battery, Operator::LessThan, 20, Severity::Warning));
        $this->evaluator = new AlertEvaluator();
    }

    public function testNoAlertWhenValuesAreWithinThresholds(): void
    {
        $alerts = $this->evaluator->evaluate($this->device, ['temperature' => 30, 'battery' => 80], new \DateTimeImmutable());

        self::assertSame([], $alerts);
    }

    public function testRaisesOneAlertPerBrokenRule(): void
    {
        $alerts = $this->evaluator->evaluate($this->device, ['temperature' => 41.5, 'battery' => 12], new \DateTimeImmutable());

        self::assertCount(2, $alerts);
        self::assertSame(Severity::Critical, $alerts[0]->getSeverity());
        self::assertSame(41.5, $alerts[0]->getValue());
        self::assertStringContainsString('Rack', $alerts[0]->getMessage());
    }

    public function testCooldownPreventsAlertSpam(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');

        self::assertCount(1, $this->evaluator->evaluate($this->device, ['temperature' => 40], $now));
        self::assertCount(0, $this->evaluator->evaluate($this->device, ['temperature' => 40], $now->modify('+5 minutes')));
        self::assertCount(1, $this->evaluator->evaluate($this->device, ['temperature' => 40], $now->modify('+11 minutes')));
    }

    public function testDisabledRuleIsIgnored(): void
    {
        foreach ($this->device->getRules() as $rule) {
            $rule->setEnabled(false);
        }

        self::assertSame([], $this->evaluator->evaluate($this->device, ['temperature' => 99], new \DateTimeImmutable()));
    }

    public function testSanitizeDropsUnknownAndNonNumericMetrics(): void
    {
        self::assertSame(
            ['temperature' => 21.5, 'rssi' => -60.0],
            AlertEvaluator::sanitize(['temperature' => '21.5', 'rssi' => -60, 'foo' => 3, 'humidity' => 'n/a']),
        );
    }
}
