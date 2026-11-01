<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tasks\TaskDraftData;
use App\Data\Tasks\TaskParseData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Ai\TaskParseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Tasks', 'Firm and global tasks')]
#[Authenticated]
final class TaskParseController extends Controller
{
    public function __construct(private readonly TaskParseService $parser) {}

    #[Endpoint('Parse a task from text', 'Turns a spoken or typed note into a prefill; nothing is saved. company_id null needs create tasks in at least one company, otherwise in that company. 422 with errors.ai when the AI provider is unavailable.')]
    #[ResponseFromSpatieData(TaskDraftData::class, User::class)]
    public function __invoke(TaskParseData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->parser->parse($data, $actor));
    }
}
