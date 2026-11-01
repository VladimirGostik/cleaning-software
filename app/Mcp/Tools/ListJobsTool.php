<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\JobService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_jobs')]
#[Description('Pages through the scheduled cleaning jobs of one company that the user may see (own jobs only without the view-all schedule permission).')]
#[IsReadOnly]
final class ListJobsTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'type', 'cleaning_object_id', 'assigned_membership_id', 'scheduled_date'];
    }

    public function execute(User $user, JobService $jobs): Response
    {
        Gate::forUser($user)->authorize('viewAny', ScheduledJob::class);

        return $this->json($this->pageOf($jobs->paginate(request(), $user)));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Job note or object name contains.'),
            'status' => $schema->string()->description('Job status.'),
            'type' => $schema->string()->description('Job type.'),
            'cleaning_object_id' => $schema->string()->format('uuid')->description('Only jobs at this object.'),
            'assigned_membership_id' => $schema->string()->format('uuid')->description('Only jobs of this assignee.'),
            'scheduled_date' => $schema->string()->description('Exact date, YYYY-MM-DD.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
