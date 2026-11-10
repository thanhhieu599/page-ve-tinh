<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Enums\Role;
use App\Models\Post;
use App\Models\User;

/**
 * Quyền trên bài viết. Việc giới hạn theo site do tenant của trang quản trị và
 * global scope đảm nhận; policy quyết định theo vai trò và trạng thái bài.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Post $post): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    /**
     * Người viết chỉ sửa bài nháp; bài chờ duyệt hoặc đã đăng chỉ Biên tập/Admin sửa,
     * để nội dung đang hiện trên site không đổi khi chưa qua duyệt.
     */
    public function update(User $user, Post $post): bool
    {
        if ($this->isReviewer($user)) {
            return $this->mayChangeLiveContent($user, $post);
        }

        return $user->canAccessAdmin() && $post->status === PostStatus::Draft;
    }

    public function submitForReview(User $user, Post $post): bool
    {
        return $user->canAccessAdmin() && $post->status === PostStatus::Draft;
    }

    /**
     * Chỉ Biên tập và Admin được đăng bài. Site yêu cầu duyệt chuyên môn (PPE)
     * thì người đăng phải có thêm quyền duyệt chuyên môn.
     */
    public function publish(User $user, Post $post): bool
    {
        if (! $this->isReviewer($user) || $post->status === PostStatus::Published) {
            return false;
        }

        return ! $post->requiresExpertReview() || $user->isExpertReviewer();
    }

    /** Trả bài chờ duyệt về nháp, hoặc gỡ bài đã đăng. */
    public function returnToDraft(User $user, Post $post): bool
    {
        return $this->isReviewer($user) && $post->status !== PostStatus::Draft;
    }

    /** Xoá (mềm) cũng là gỡ bài khỏi site, nên Người viết chỉ xoá được bài nháp. */
    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    /** Khôi phục bài đã đăng là đưa nó lên site lại. */
    public function restore(User $user, Post $post): bool
    {
        return $this->isReviewer($user) && $this->mayChangeLiveContent($user, $post);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isReviewer($user);
    }

    /** Xoá vĩnh viễn không khôi phục được, chỉ Admin. */
    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Nội dung đang (hoặc sẽ) hiện trên site yêu cầu duyệt chuyên môn chỉ được thay đổi
     * bởi người có quyền duyệt chuyên môn, giống như khi đăng.
     */
    private function mayChangeLiveContent(User $user, Post $post): bool
    {
        return $post->status !== PostStatus::Published || ! $post->requiresExpertReview() || $user->isExpertReviewer();
    }

    private function isReviewer(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::Editor->value]);
    }
}
