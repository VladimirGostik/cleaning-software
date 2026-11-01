<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->uuid('parent_id')->nullable();

            $table->string('type', 32);
            $table->string('employment_type', 32)->nullable();
            $table->string('name', 255);
            $table->text('body');
            $table->text('base_body')->nullable();
            $table->string('base_hash', 64)->nullable();
            $table->boolean('show_header')->default(true);
            $table->boolean('show_footer')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('document_templates')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX document_templates_tenant_parent_unique ON document_templates (tenant_id, parent_id) WHERE parent_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement('ALTER TABLE document_templates ADD CONSTRAINT document_templates_parent_requires_tenant CHECK (parent_id IS NULL OR tenant_id IS NOT NULL)');

        DB::table('contract_templates')->orderBy('created_at')->each(function (object $row): void {
            DB::table('document_templates')->insert([
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'type' => $row->category,
                'name' => $row->name,
                'body' => self::wrapPlainText(str_replace('{{quote.items}}', '{{block.quote_items}}', self::str($row->body))),
                'is_active' => $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at,
            ]);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['contract_template_id']);
            $table->dropIndex(['contract_template_id']);
            $table->renameColumn('contract_template_id', 'document_template_id');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreign('document_template_id')->references('id')->on('document_templates')->nullOnDelete();
            $table->index('document_template_id');
        });

        Schema::drop('contract_templates');
    }

    public function down(): void
    {
        Schema::create('contract_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('category', 32);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_active']);
        });

        DB::table('document_templates')
            ->whereNotNull('tenant_id')
            ->whereNull('parent_id')
            ->whereIn('type', ['service_agreement', 'employment', 'nda', 'gdpr', 'other'])
            ->orderBy('created_at')
            ->each(function (object $row): void {
                DB::table('contract_templates')->insert([
                    'id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'name' => $row->name,
                    'category' => $row->type,
                    'body' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], "\n", self::str($row->body))))),
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'deleted_at' => $row->deleted_at,
                ]);
            });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['document_template_id']);
            $table->dropIndex(['document_template_id']);
        });

        DB::table('contracts')
            ->whereNotNull('document_template_id')
            ->whereNotIn('document_template_id', DB::table('contract_templates')->select('id'))
            ->update(['document_template_id' => null]);

        Schema::table('contracts', function (Blueprint $table): void {
            $table->renameColumn('document_template_id', 'contract_template_id');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreign('contract_template_id')->references('id')->on('contract_templates')->nullOnDelete();
            $table->index('contract_template_id');
        });

        Schema::dropIfExists('document_templates');
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
