<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\FakeSchedulePlannerAgent;
use App\Ai\Agents\SchedulePlannerAgent;
use App\Models\Schedule;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Orchestrates schedule intent generation. Uses laravel/ai when a provider is
 * configured; otherwise returns a deterministic FakeSchedulePlannerAgent.
 */
class ScheduleAiService
{
    /**
     * Check whether any AI provider key is configured in the environment.
     */
    public static function hasProvider(): bool
    {
        $keys = [
            (string) (\env('OPENAI_API_KEY') ?? ''),
            (string) (\env('ANTHROPIC_API_KEY') ?? ''),
            (string) (\env('GEMINI_API_KEY') ?? ''),
            (string) (\env('GROQ_API_KEY') ?? ''),
            (string) (\env('MISTRAL_API_KEY') ?? ''),
            (string) (\env('DEEPSEEK_API_KEY') ?? ''),
            (string) (\env('XAI_API_KEY') ?? ''),
            (string) (\env('OPENROUTER_API_KEY') ?? ''),
            (string) (\env('OLLAMA_API_KEY') ?? ''),
        ];

        foreach ($keys as $k) {
            if ($k !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the structured schedule intent for the given prompt.
     *
     * @return array{understanding: string, warnings: array<int, string>, shift_requirements: array<int, array<string, mixed>>, intent: string}
     */
    public function generate(
        Store $store,
        Carbon $periodStart,
        Carbon $periodEnd,
        EloquentCollection $employees,
        string $prompt,
    ): array {
        $agent = $this->resolveAgent($store, $periodStart, $periodEnd);

        $response = $agent->prompt($prompt);
        $structured = $this->extractStructured($response);

        $warnings = $structured['warnings'] ?? [];
        $existingNames = $employees->map(static fn($e): string => $e->getName())->all();
        $mentioned = $this->findMentionedUnknownNames($prompt, $existingNames);
        foreach ($mentioned as $unknown) {
            $warnings[] = "{$unknown} was mentioned, but no employee named {$unknown} exists.";
        }

        return [
            'intent' => (string) ($structured['intent'] ?? 'create_or_update_schedule'),
            'understanding' => (string) ($structured['understanding'] ?? ''),
            'warnings' => \array_values(\array_unique($warnings)),
            'shift_requirements' => $structured['shift_requirements'] ?? [],
        ];
    }

    /**
     * Build the right agent. Real if the SDK has a provider, otherwise fake.
     */
    private function resolveAgent(Store $store, Carbon $periodStart, Carbon $periodEnd): object
    {
        if (self::hasProvider()) {
            return new SchedulePlannerAgent(
                $store->getName(),
                $periodStart,
                $periodEnd,
                \App\Models\EmployeeProfile::query()->getQuery()->getQuery()->whereIn('id', $store->employees()->pluck('employee_profiles.id')->all() ?: [0])->orderBy('name')->get(),
            );
        }

        return new FakeSchedulePlannerAgent(
            $store->getName(),
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
        );
    }

    /**
     * Extract the structured payload from an AgentResponse.
     */
    private function extractStructured(AgentResponse $response): array
    {
        $text = (string) $response->text;
        $decoded = \json_decode($text, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Look for unknown names in the prompt by matching capitalized words.
     *
     * @param array<int, string> $knownNames
     *
     * @return array<int, string>
     */
    private function findMentionedUnknownNames(string $prompt, array $knownNames): array
    {
        $unknown = [];
        if (\preg_match_all('/\\b([A-Z][a-zA-Z]+)\\b/', $prompt, $matches)) {
            $knownFull = \array_map(static fn(string $n): string => \mb_strtolower($n), $knownNames);
            $knownFirst = \array_map(static fn(string $n): string => \mb_strtolower(\explode(' ', $n)[0]), $knownNames);
            $known = \array_unique(\array_merge($knownFull, $knownFirst));
            foreach ($matches[1] as $name) {
                if (\in_array(\mb_strtolower($name), $known, true)) {
                    continue;
                }
                if (\in_array($name, $unknown, true)) {
                    continue;
                }
                $unknown[] = $name;
            }
        }

        return $unknown;
    }
}
