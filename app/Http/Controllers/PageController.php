<?php

namespace App\Http\Controllers;

use App\Enums\PageKind;
use App\Models\Page;
use Illuminate\Contracts\View\View;

/** Trang bắt buộc của site (giới thiệu, chính sách quyền riêng tư, liên hệ). Nháp thì 404. */
class PageController extends Controller
{
    public function show(PageKind $kind): View
    {
        $page = Page::query()->where('kind', $kind)->published()->firstOrFail();

        return view('theme::pages.show', ['page' => $page]);
    }
}
