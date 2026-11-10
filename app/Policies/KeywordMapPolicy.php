<?php

namespace App\Policies;

use App\Models\KeywordMap;
use App\Models\User;

/** Bản đồ từ khoá là quyết định SEO: Biên tập và Admin quản lý, người viết không. */
class KeywordMapPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReviewer();
    }

    public function view(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isReviewer();
    }

    public function create(User $user): bool
    {
        return $user->isReviewer();
    }

    public function update(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isReviewer();
    }

    public function delete(User $user, KeywordMap $keywordMap): bool
    {
        return $user->isReviewer();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isReviewer();
    }
}
