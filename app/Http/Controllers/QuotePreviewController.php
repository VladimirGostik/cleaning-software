<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteKindEnum;
use App\Models\Quote;
use App\Services\Documents\QuoteTextService;
use App\Services\Pdf\QuotePdfService;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class QuotePreviewController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteTextService $text,
        private readonly QuotePdfService $pdf,
    ) {}

    #[Authorize('create', Quote::class)]
    public function pdf(QuoteUpsertData $data): Response
    {
        return $this->pdfResponse($data, null);
    }

    #[Authorize('update', 'quote')]
    public function pdfFor(QuoteUpsertData $data, Quote $quote): Response
    {
        return $this->pdfResponse($data, $quote);
    }

    #[Authorize('create', Quote::class)]
    public function text(QuoteUpsertData $data): JsonResponse
    {
        return $this->textResponse($data, null);
    }

    #[Authorize('update', 'quote')]
    public function textFor(QuoteUpsertData $data, Quote $quote): JsonResponse
    {
        return $this->textResponse($data, $quote);
    }

    private function pdfResponse(QuoteUpsertData $data, ?Quote $existing): Response
    {
        $model = $this->model($data, $existing);

        $content = $this->pdf->renderPreview(
            $model,
            $this->text->bodyFor($data, $existing, $model->tenant_id),
            $data->document_show_header,
            $data->document_show_footer,
        );

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function textResponse(QuoteUpsertData $data, ?Quote $existing): JsonResponse
    {
        $model = $this->model($data, $existing);

        return response()->json($this->text->editorPreview($model, $this->text->bodyFor($data, $existing, $model->tenant_id))->toArray());
    }

    private function model(QuoteUpsertData $data, ?Quote $existing): Quote
    {
        if ($data->kind === QuoteKindEnum::Document) {
            throw ValidationException::withMessages(['kind' => [__('app.quote_preview_not_for_document')]]);
        }

        return $this->quotes->previewModel($data, $existing);
    }
}
