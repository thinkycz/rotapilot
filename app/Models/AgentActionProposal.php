<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Thinkycz\LaravelCore\Models\BaseModel;

class AgentActionProposal extends BaseModel
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_APPLIED = 'applied';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_FAILED = 'failed';

    /**
     * Base select query.
     *
     * @param Builder<static> $builder
     */
    public static function querySelect(Builder $builder): void
    {
        $builder->getQuery()->select($builder->qualifyColumn('*'));
    }

    /**
     * Search scope.
     *
     * @param Builder<static> $builder
     */
    public static function scopeSearch(Builder $builder, string $search): void
    {
        $builder->getQuery()->where($builder->qualifyColumn('summary'), 'LIKE', "%{$search}%");
    }

    /**
     * Conversation id getter.
     */
    public function getConversationId(): string
    {
        return $this->assertString('conversation_id');
    }

    /**
     * User id getter.
     */
    public function getUserId(): int
    {
        return $this->assertInt('user_id');
    }

    /**
     * Assistant message id getter.
     */
    public function getMessageId(): string|null
    {
        if (!$this->attributeLoaded('message_id')) {
            return null;
        }

        return $this->assertNullableString('message_id');
    }

    /**
     * Status getter.
     */
    public function getStatus(): string
    {
        return $this->assertString('status');
    }

    /**
     * Summary getter.
     */
    public function getSummary(): string
    {
        return $this->assertString('summary');
    }

    /**
     * Actions getter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActions(): array
    {
        $actions = $this->mixed('actions');
        if (!\is_array($actions)) {
            return [];
        }

        $normalized = [];
        foreach ($actions as $action) {
            if (\is_array($action)) {
                $row = [];
                foreach ($action as $key => $value) {
                    if (\is_string($key)) {
                        $row[$key] = $value;
                    }
                }
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * Result getter.
     *
     * @return array<string, mixed>|null
     */
    public function getResult(): array|null
    {
        $result = $this->mixed('result');
        if (!\is_array($result)) {
            return null;
        }

        $normalized = [];
        foreach ($result as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Whether the proposal can still be applied or rejected.
     */
    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'result' => 'array',
        ];
    }
}
