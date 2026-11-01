<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use LogicException;

abstract class TenantTool extends CleanMasterTool
{
    final public function handle(Request $request): Response
    {
        $companyId = $request->get('company_id');
        $companyId = is_string($companyId) ? $companyId : null;

        return $this->guard($request, $companyId, function (User $user) use ($request): Response {
            /** @var array<string, mixed> $arguments */
            $arguments = $request->validate(['company_id' => ['required', 'uuid'], ...$this->rules()]);
            /** @var string $companyId */
            $companyId = $arguments['company_id'];

            $response = $this->context->run(
                $user,
                $companyId,
                fn (): mixed => app()->call(static::class.'@execute', ['request' => $request, 'user' => $user, 'companyId' => $companyId]),
                $this->requestInput($request->all()),
            );

            return $response instanceof Response ? $response : throw new LogicException('Tool execute() must return a Response.');
        });
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function requestInput(array $arguments): array
    {
        return $arguments;
    }

    /** @return array<string, Type> */
    protected function companyProperty(JsonSchema $schema): array
    {
        return [
            'company_id' => $schema->string()->format('uuid')->description('Company id from list_companies. Every call acts inside exactly one company.')->required(),
        ];
    }

    protected function id(Request $request, string $key): string
    {
        return $request->string($key)->toString();
    }

    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $page
     * @return array{items: list<mixed>, total: int, page: int, last_page: int}
     */
    protected function pageOf(LengthAwarePaginator $page): array
    {
        /** @var list<mixed> $items */
        $items = $page->toArray()['data'];

        return ['items' => $items, 'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()];
    }
}
