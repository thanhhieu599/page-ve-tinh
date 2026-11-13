<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\View\View;

/** Báo giá dạng in A4 (trình duyệt: In → Lưu thành PDF). Chỉ người được xem báo giá trong CRM. */
class QuotePrintController extends Controller
{
    public function __invoke(Quote $quote): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->canAccessCrm(), 403);
        abort_unless(Quote::query()->visibleTo($user)->whereKey($quote->id)->exists(), 403);

        return view('crm.quote-print', [
            'quote' => $quote->load(['items', 'customer', 'contact', 'owner']),
            'company' => CompanyProfile::current(),
        ]);
    }
}
