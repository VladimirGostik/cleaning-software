<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Contracts\RendersQuotePdf;
use App\Enums\QuoteKindEnum;
use App\Models\Quote;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Support\UriTemplate;

#[Name('quote-pdf')]
#[Description('PDF of one itemized quote: quote-pdf://{company}/{quote}. Uploaded-document quotes have no generated PDF.')]
final class QuotePdfResource extends PdfResource
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('quote-pdf://{company}/{quote}');
    }

    protected function resourceName(): string
    {
        return 'quote_pdf';
    }

    protected function idKey(): string
    {
        return 'quote';
    }

    protected function renderer(User $user): Closure
    {
        return function (string $id) use ($user): string {
            $quote = Quote::query()->findOrFail($id);

            Gate::forUser($user)->authorize('downloadPdf', $quote);

            if ($quote->kind !== QuoteKindEnum::Itemized) {
                throw new ModelNotFoundException;
            }

            return app(RendersQuotePdf::class)->render($quote);
        };
    }
}
