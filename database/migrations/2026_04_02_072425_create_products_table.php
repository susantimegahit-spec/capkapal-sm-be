<?php
// create_products_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('sku', 50)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('base_price', 15, 2);
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('total_sold')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('review_count')->default(0);
            $table->jsonb('meta')->nullable();        // SEO: title, description, keywords
            $table->jsonb('attributes')->nullable();  // Custom attributes bebas
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index('total_sold');
        });

        // PostgreSQL full-text search index
        DB::statement("
            CREATE INDEX products_fulltext_idx
            ON products
            USING gin(to_tsvector('indonesian', name || ' ' || COALESCE(short_description, '')))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
