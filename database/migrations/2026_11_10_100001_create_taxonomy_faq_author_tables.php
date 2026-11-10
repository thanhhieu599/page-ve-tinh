<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug');
            $table->string('locale', 10)->default('vi');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            // Bài thuộc danh mục này (vd. PPE cho nhà máy thép) cần người duyệt chuyên môn.
            $table->boolean('requires_expert_review')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('post_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->unique(['post_id', 'category_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10)->default('vi');
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'sort']);
        });

        // Tác giả dùng chung mọi site: một chuyên gia có một hồ sơ thống nhất (E-E-A-T).
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('job_title')->nullable();
            $table->text('bio')->nullable();
            $table->text('expertise')->nullable();
            $table->string('avatar')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->timestamps();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('og_image')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
        Schema::dropIfExists('authors');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('post_category');
        Schema::dropIfExists('categories');
    }
};
