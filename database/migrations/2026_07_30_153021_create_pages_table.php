<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            // Pages nest exactly one level: a hub page and the pages grouped under it.
            // Deleting a hub promotes its children to the root rather than taking them
            // down with it, so a mis-click can never destroy the legal pages.
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title');
            // Globally unique rather than unique per parent, so a page keeps its slug
            // when it moves between hubs and a child can never collide with a root page.
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->string('featured_image_caption', 500)->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            // Manual ordering of the children listed on a hub page; legal pages have a
            // conventional order that is neither alphabetical nor chronological.
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
