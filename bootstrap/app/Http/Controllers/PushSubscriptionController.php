<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a push subscription from the PWA client.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'subscription'                  => 'required|array',
            'subscription.endpoint'         => 'required|url',
            'subscription.keys'             => 'required|array',
            'subscription.keys.p256dh'      => 'required|string',
            'subscription.keys.auth'        => 'required|string',
        ]);

        $user = Auth::user();
        $sub  = $request->subscription;

        // Upsert push subscription for this user
        DB::table('push_subscriptions')->updateOrInsert(
            [
                'user_id'  => $user->id,
                'endpoint' => $sub['endpoint'],
            ],
            [
                'user_id'    => $user->id,
                'endpoint'   => $sub['endpoint'],
                'p256dh_key' => $sub['keys']['p256dh'],
                'auth_token' => $sub['keys']['auth'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Log::info('Push subscription saved', ['user' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Subscription saved.']);
    }

    /**
     * Remove a push subscription.
     */
    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|url']);

        DB::table('push_subscriptions')
            ->where('user_id', Auth::id())
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Send a test push notification to the current user.
     */
    public function sendTest(Request $request)
    {
        $user = Auth::user();

        $subscriptions = DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return response()->json(['error' => 'No push subscriptions found for your account.'], 404);
        }

        $payload = json_encode([
            'title'          => 'Big Cash Test Notification',
            'body'           => 'Push notifications are working! You will receive loan alerts here.',
            'icon'           => '/icons/icon-192x192.png',
            'badge'          => '/icons/icon-72x72.png',
            'tag'            => 'test-notification',
            'type'           => 'test',
            'action_url'     => '/portal/dashboard',
        ]);

        $sent  = 0;
        $vapid = [
            'subject'    => 'mailto:' . config('bigcash.company.email', 'noreply@bigcash.com'),
            'publicKey'  => config('webpush.vapid.public_key'),
            'privateKey' => config('webpush.vapid.private_key'),
        ];

        foreach ($subscriptions as $sub) {
            try {
                // Using minishlink/web-push if available
                if (class_exists('\Minishlink\WebPush\WebPush')) {
                    $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => $vapid]);
                    $webPush->sendOneNotification(
                        \Minishlink\WebPush\Subscription::create([
                            'endpoint'        => $sub->endpoint,
                            'publicKey'       => $sub->p256dh_key,
                            'authToken'       => $sub->auth_token,
                            'contentEncoding' => 'aesgcm',
                        ]),
                        $payload
                    );
                    $sent++;
                }
            } catch (\Exception $e) {
                Log::error('Push send failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'sent'    => $sent,
            'message' => "Test notification sent to {$sent} device(s).",
        ]);
    }

    /**
     * Handle PWA share target POST requests.
     */
    public function shareTarget(Request $request)
    {
        // File shared to the app (e.g. sharing a document to upload)
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('shared_uploads', 'public');
            return redirect()->route('borrower.dashboard')
                ->with('shared_file', $path)
                ->with('success', 'File received. You can now attach it to a loan application.');
        }

        return redirect()->route('borrower.dashboard');
    }
}
