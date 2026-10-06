<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The "Send us a message" form on the public home page. The message is delivered to every active MAO
 * Personnel account as a notification (the bell in the header), so it needs no email server.
 */
class ContactMessageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'in:Rice Farmer,MAO Personnel,IT Personnel,Other'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            // Hidden from people; a filled value means an automated form filler.
            'website' => ['nullable', 'string', 'max:0'],
        ], [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address so the office can reply.',
            'email.email' => 'Please enter a valid email address.',
            'message.required' => 'Please write your message.',
            'message.min' => 'Please write a little more so the office can help you.',
        ]);

        $recipients = User::query()->where('role', User::ROLE_MAO)->where('status', User::STATUS_ACTIVE)->pluck('id');

        if ($recipients->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Your message could not be delivered right now. Please call or email the office instead.'], 503);
        }

        $now = now();
        $body = "From: {$data['name']} ({$data['email']})".(filled($data['role'] ?? null) ? "\nRole: {$data['role']}" : '')."\n\n".$data['message'];

        Notification::query()->insert($recipients->map(fn (int $userId): array => [
            'user_id' => $userId,
            'title' => 'Website message from '.$data['name'],
            'message' => $body,
            'type' => 'Announcement',
            'is_read' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $recipients->each(fn (int $userId) => Cache::forget('sidebar:unread-notifications:'.$userId));
        SystemAuditLogger::record('Website Contact Message', $request, ['recipient_count' => $recipients->count()]);

        return response()->json(['ok' => true, 'message' => 'Thank you. Your message was sent to the Municipal Agriculture Office.']);
    }
}
