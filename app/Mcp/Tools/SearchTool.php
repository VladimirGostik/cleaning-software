<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Services\ClientService;
use App\Services\InvoiceService;
use App\Services\ObjectService;
use App\Services\QuoteService;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search')]
#[Description('Finds clients, invoices, quotes and objects of one company by name or number. Returns at most 5 hits per kind, only of kinds the user may view.')]
#[IsReadOnly]
final class SearchTool extends TenantTool
{
    private const int LIMIT = 5;

    protected function rules(): array
    {
        return ['query' => ['required', 'string', 'min:2', 'max:100']];
    }

    public function execute(
        Request $request,
        User $user,
        ClientService $clients,
        InvoiceService $invoices,
        QuoteService $quotes,
        ObjectService $objects,
    ): Response {
        $query = $this->id($request, 'query');

        return $this->json([
            'clients' => $this->hits($user, Client::class, $query, fn () => $clients->paginate(request())),
            'invoices' => $this->hits($user, Invoice::class, $query, fn () => $invoices->paginate(request())),
            'quotes' => $this->hits($user, Quote::class, $query, fn () => $quotes->paginate(request())),
            'objects' => $this->hits($user, CleaningObject::class, $query, fn () => $objects->paginate(request(), $user)),
        ]);
    }

    /**
     * @param  class-string  $model
     * @param  Closure(): LengthAwarePaginator<int, *>  $fetch
     * @return list<mixed>
     */
    private function hits(User $user, string $model, string $query, Closure $fetch): array
    {
        if (! Gate::forUser($user)->allows('viewAny', $model)) {
            return [];
        }

        $page = $this->context->withRequest($user, ['filter' => ['search' => $query], 'per_page' => self::LIMIT], [], $fetch);

        /** @var list<mixed> $items */
        $items = $page->toArray()['data'];

        return $items;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'query' => $schema->string()->min(2)->max(100)->description('Name, number or city fragment to look for.')->required(),
        ];
    }
}
