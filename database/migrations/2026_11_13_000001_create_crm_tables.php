<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * CRM chung cho mọi site vệ tinh (13/11/2026): khách hàng doanh nghiệp, người liên hệ, báo giá,
 * đơn hàng (công nợ), hoạt động, việc cần làm, thương hiệu; lead gắn khách hàng/nhân viên phụ trách.
 * Kho/nhập hàng do Sapo, kế toán do MISA (CRM xuất Excel sang MISA).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([Role::SalesManager, Role::Sales] as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_code', 20)->nullable()->unique();
            $table->string('type', 20)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('industrial_zone')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('email_domain')->nullable()->index();
            $table->string('status', 20)->default('prospect')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('first_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->unsignedSmallInteger('reorder_cycle_days')->nullable();
            $table->date('next_reorder_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 20)->nullable();
            $table->string('title')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('phone_digits', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('zalo', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->change();
            $table->string('channel', 20)->default('website')->after('site_id');
            $table->foreignId('customer_id')->nullable()->after('channel')->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('contact_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
        });

        foreach (['quotes', 'orders'] as $document) {
            Schema::create($document, function (Blueprint $table) use ($document) {
                $table->id();
                $table->string('number', 30)->unique();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                if ($document === 'quotes') {
                    $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
                    $table->string('status', 20)->default('draft')->index();
                    $table->date('valid_until')->nullable();
                    $table->text('delivery_terms')->nullable();
                    $table->text('payment_terms')->nullable();
                    $table->string('lost_reason')->nullable();
                    $table->timestamp('sent_at')->nullable();
                    $table->timestamp('decided_at')->nullable();
                } else {
                    $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
                    $table->string('status', 20)->default('new')->index();
                    $table->string('payment_status', 20)->default('unpaid')->index();
                    $table->date('ordered_at');
                    $table->date('delivered_at')->nullable();
                    $table->date('due_date')->nullable()->index();
                    $table->string('invoice_number', 50)->nullable();
                    $table->unsignedBigInteger('paid_amount')->default(0);
                }
                $table->decimal('vat_rate', 5, 2)->default(10);
                $table->unsignedBigInteger('subtotal')->default(0);
                $table->unsignedBigInteger('vat_amount')->default(0);
                $table->unsignedBigInteger('total')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            $item = $document === 'quotes' ? 'quote' : 'order';
            Schema::create("{$item}_items", function (Blueprint $table) use ($document) {
                $table->id();
                $table->foreignId(str($document)->singular().'_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sku', 100)->nullable();
                $table->string('name');
                $table->string('unit', 30)->default('cái');
                $table->decimal('quantity', 12, 2);
                $table->unsignedBigInteger('unit_price');
                $table->unsignedBigInteger('line_total');
                $table->unsignedSmallInteger('sort')->default(0);
            });
        }

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('content');
            $table->timestamp('happened_at')->index();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 20)->default('other');
            $table->timestamp('due_at')->index();
            $table->timestamp('done_at')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 30)->nullable();
        });

        // Cài đặt báo giá dùng chung (CRM → Cài đặt).
        Schema::table('company_profile', function (Blueprint $table) {
            $table->decimal('quote_vat_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('quote_valid_days')->nullable();
            $table->text('quote_delivery_terms')->nullable();
            $table->text('quote_payment_terms')->nullable();
            $table->string('bank_account')->nullable();
            $table->unsignedSmallInteger('default_reorder_cycle_days')->nullable();
            $table->unsignedSmallInteger('payment_due_days')->nullable();
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_profile', fn (Blueprint $table) => $table->dropColumn([
            'quote_vat_rate', 'quote_valid_days', 'quote_delivery_terms', 'quote_payment_terms', 'bank_account', 'default_reorder_cycle_days', 'payment_due_days',
        ]));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('unit');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['channel', 'assigned_at']);
        });
        foreach (['tasks', 'activities', 'order_items', 'orders', 'quote_items', 'quotes', 'contacts', 'customers', 'brands'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
