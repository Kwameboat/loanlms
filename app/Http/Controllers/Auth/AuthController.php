<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) return $this->redirectAfterLogin(Auth::user());
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $this->checkRateLimit($request);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact your administrator.',
            ]);
        }

        // 2FA check
        if ($user->two_factor_enabled) {
            session(['2fa_user_id' => $user->id, '2fa_remember' => $request->boolean('remember')]);
            $this->sendOtp($user);
            return redirect()->route('auth.2fa');
        }

        RateLimiter::clear($this->throttleKey($request));
        Auth::login($user, $request->boolean('remember'));
        $this->recordLogin($user, $request);

        session()->regenerate();

        if ($user->must_change_password) {
            return redirect()->route('auth.change-password');
        }

        activity('auth')->causedBy($user)->log('Logged in');
        return $this->redirectAfterLogin($user);
    }

    public function show2fa()
    {
        if (! session('2fa_user_id')) return redirect()->route('login');
        return view('auth.2fa');
    }

    public function verify2fa(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $userId = session('2fa_user_id');
        if (! $userId) return redirect()->route('login');

        $user = User::findOrFail($userId);

        if (! $user->isOtpValid($request->otp)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.']);
        }

        // Clear OTP
        $user->update(['otp_code' => null, 'otp_expires_at' => null]);
        session()->forget(['2fa_user_id', '2fa_remember']);

        Auth::login($user, session('2fa_remember', false));
        $this->recordLogin($user, $request);
        session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    public function resendOtp(Request $request)
    {
        $userId = session('2fa_user_id');
        if (! $userId) return response()->json(['error' => 'Session expired'], 422);

        $user = User::findOrFail($userId);
        $this->sendOtp($user);

        return response()->json(['message' => 'OTP resent successfully.']);
    }

    public function logout(Request $request)
    {
        activity('auth')->causedBy(Auth::user())->log('Logged out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'You have been logged out successfully.');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always show success to prevent email enumeration
        if ($user) {
            $token = Str::random(64);
            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            try {
                \Mail::to($user->email)->send(new \App\Mail\PasswordResetMail($user, $token));
            } catch (\Exception $e) {
                \Log::error('Password reset email failed', ['email' => $user->email]);
            }
        }

        return back()->with('success', 'If that email is registered, a reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(['email' => 'Reset link has expired. Please request a new one.']);
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        $user->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        activity('auth')->causedBy($user)->log('Password reset');
        return redirect()->route('login')->with('success', 'Password reset successfully. Please log in.');
    }

    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password'      => 'required',
            'password'              => 'required|min:8|confirmed|different:current_password',
            'password_confirmation' => 'required',
        ]);

        if (! Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        Auth::user()->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        activity('auth')->causedBy(Auth::user())->log('Password changed');
        return redirect()->intended(route('dashboard'))->with('success', 'Password changed successfully.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function redirectAfterLogin(User $user): \Illuminate\Http\RedirectResponse
    {
        if ($user->hasRole('borrower')) {
            return redirect()->route('borrower.dashboard');
        }
        return redirect()->route('dashboard');
    }

    protected function sendOtp(User $user): void
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp_code'       => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
        app(NotificationService::class)->sendOtp($user, $otp);
    }

    protected function recordLogin(User $user, Request $request): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
    }

    protected function checkRateLimit(Request $request): void
    {
        $maxAttempts = config('bigcash.login.max_attempts', 5);
        $decayMinutes = config('bigcash.login.decay_minutes', 10);

        if (RateLimiter::tooManyAttempts($this->throttleKey($request), $maxAttempts)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());
    }
}
