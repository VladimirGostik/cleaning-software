<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const string CONTRACT = 'App\Models\Contract';

    private const string QUOTE = 'App\Models\Quote';

    private const string DOCUMENT = 'App\Models\Document';

    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuidMorphs('documentable');
            $table->foreignUuid('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();

            $table->string('type', 32);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->boolean('show_header')->default(true);
            $table->boolean('show_footer')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
        });

        DB::table('contracts')->orderBy('created_at')->each(function (object $contract): void {
            $body = '<p>{{block.parties}}</p>'.self::wrapPlainText(self::str($contract->body)).'<p>{{block.signatures}}</p>';

            DB::table('documents')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $contract->tenant_id,
                'documentable_type' => self::CONTRACT,
                'documentable_id' => $contract->id,
                'document_template_id' => $contract->document_template_id,
                'type' => $contract->category,
                'title' => $contract->title,
                'body' => Crypt::encryptString($body),
                'created_at' => $contract->created_at,
                'updated_at' => $contract->updated_at,
                'deleted_at' => $contract->deleted_at,
            ]);
        });

        DB::table('quotes')
            ->whereIn('id', DB::table('media')->where('model_type', self::QUOTE)->where('collection_name', 'document')->select('model_id'))
            ->orderBy('created_at')
            ->each(function (object $quote): void {
                $documentId = (string) Str::uuid7();

                DB::table('documents')->insert([
                    'id' => $documentId,
                    'tenant_id' => $quote->tenant_id,
                    'documentable_type' => self::QUOTE,
                    'documentable_id' => $quote->id,
                    'type' => 'quote',
                    'title' => self::str($quote->subject ?? $quote->number ?? 'quote'),
                    'created_at' => $quote->created_at,
                    'updated_at' => $quote->updated_at,
                    'deleted_at' => $quote->deleted_at,
                ]);

                DB::table('media')
                    ->where('model_type', self::QUOTE)
                    ->where('model_id', $quote->id)
                    ->where('collection_name', 'document')
                    ->update(['model_type' => self::DOCUMENT, 'model_id' => $documentId, 'collection_name' => 'file']);
            });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->text('body')->default('');
        });

        DB::table('documents')->where('documentable_type', self::CONTRACT)->orderBy('created_at')->each(function (object $document): void {
            $body = $document->body !== null ? Crypt::decryptString(self::str($document->body)) : '';
            $body = preg_replace('#<p>\{\{block\.(parties|signatures)\}\}</p>#', '', $body) ?? $body;
            $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], "\n", $body))));

            DB::table('contracts')->where('id', $document->documentable_id)->update(['body' => $text]);
        });

        DB::table('documents')->where('documentable_type', self::QUOTE)->orderBy('created_at')->each(function (object $document): void {
            DB::table('media')
                ->where('model_type', self::DOCUMENT)
                ->where('model_id', $document->id)
                ->where('collection_name', 'file')
                ->update(['model_type' => self::QUOTE, 'model_id' => $document->documentable_id, 'collection_name' => 'document']);
        });

        DB::statement('ALTER TABLE contracts ALTER COLUMN body DROP DEFAULT');

        Schema::dropIfExists('documents');
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function wrapPlainText(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];
        $html = '';

        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }

            $html .= '<p>'.implode('<br>', array_map(
                static fn (string $line): string => e($line),
                preg_split('/\R/', $paragraph) ?: [],
            )).'</p>';
        }

        return $html;
    }
};
