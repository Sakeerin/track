<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EtaRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'rule_type',
        'conditions',
        'adjustments',
        'priority',
        'active',
        'description',
    ];

    protected $casts = [
        'conditions' => 'array',
        'adjustments' => 'array',
        'active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Scope to get only active rules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get rules by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    /**
     * Scope to order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if this rule applies to the given context
     */
    public function appliesTo(array $context): bool
    {
        if (!$this->active) {
            return false;
        }

        foreach ($this->conditions as $key => $condition) {
            if (!$this->evaluateCondition($key, $condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against the context
     */
    private function evaluateCondition(string $key, $condition, array $context): bool
    {
        if (!isset($context[$key])) {
            return false;
        }

        $value = $context[$key];

        // Handle different condition types
        if (is_array($condition)) {
            // For array conditions, ALL sub-conditions must pass
            if (isset($condition['in'])) {
                if (!in_array($value, $condition['in'])) {
                    return false;
                }
            }
            if (isset($condition['not_in'])) {
                if (in_array($value, $condition['not_in'])) {
                    return false;
                }
            }
            if (isset($condition['gte'])) {
                if (!($value >= $condition['gte'])) {
                    return false;
                }
            }
            if (isset($condition['lte'])) {
                if (!($value <= $condition['lte'])) {
                    return false;
                }
            }
            if (isset($condition['gt'])) {
                if (!($value > $condition['gt'])) {
                    return false;
                }
            }
            if (isset($condition['lt'])) {
                if (!($value < $condition['lt'])) {
                    return false;
                }
            }
            
            // If we get here, all conditions passed
            return true;
        }

        // Direct equality check
        return $value === $condition;
    }

    /**
     * Apply this rule's adjustments to the given ETA
     */
    public function applyAdjustments(\DateTime $eta, array $context = []): \DateTime
    {
        $adjustedEta = clone $eta;

        if (isset($this->adjustments['hours'])) {
            $adjustedEta->modify(sprintf('%+d hours', $this->adjustments['hours']));
        }

        if (isset($this->adjustments['days'])) {
            $adjustedEta->modify(sprintf('%+d days', $this->adjustments['days']));
        }

        if (isset($this->adjustments['multiplier'])) {
            // Get the pickup time from context if available, otherwise use current time
            $baseTime = isset($context['pickup_time']) ? $context['pickup_time'] : new \DateTime();
            
            // Calculate the difference between base time and current ETA
            $diff = $baseTime->diff($adjustedEta);
            $totalHours = ($diff->days * 24) + $diff->h + ($diff->i / 60);
            
            // Apply multiplier to the total hours
            $newHours = $totalHours * $this->adjustments['multiplier'];
            
            // Set new ETA from base time
            $adjustedEta = clone $baseTime;
            $adjustedEta->modify(sprintf('+%d hours', (int) $newHours));
            
            // Add remaining minutes if any
            $remainingMinutes = ($newHours - (int) $newHours) * 60;
            if ($remainingMinutes > 0) {
                $adjustedEta->modify(sprintf('+%d minutes', (int) $remainingMinutes));
            }
        }

        return $adjustedEta;
    }
}