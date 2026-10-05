<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class PushService
{
    /**
     * ส่ง Push Notification และบันทึกลง AppNotification สำหรับผู้ใช้
     */
    public function sendToUser(User $user, string $type, string $title, string $body, array $data = []): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * ส่งแจ้งเตือนถึง Admin ทุกคน
     */
    public function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        $admins = User::where('role', \App\Enums\Role::ADMIN)->get();

        foreach ($admins as $admin) {
            $this->sendToUser($admin, $type, $title, $body, $data);
        }
    }
}
