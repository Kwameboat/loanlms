<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Big Cash LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
        }
        .login-header {
            background: linear-gradient(135deg, #052e16, #16a34a);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        .login-header .brand-icon {
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
        }
        .login-header h1 { color: #fff; font-size: 1.4rem; font-weight: 700; margin: 0; }
        .login-header p  { color: rgba(255,255,255,0.65); font-size: 0.85rem; margin-top: 0.3rem; }
        .login-body { padding: 2rem; }
        .form-control {
            border-radius: 10px;
            padding: 0.7rem 1rem;
            border: 1.5px solid #e2e8f0;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .input-group-text { border-radius: 0 10px 10px 0; background: #f8fafc; border: 1.5px solid #e2e8f0; border-left: 0; }
        .btn-login {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff; border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(37,99,235,0.35); color: #fff; }
        .divider { text-align: center; color: #94a3b8; font-size: 0.78rem; margin: 1rem 0; position: relative; }
        .divider::before { content: ''; position: absolute; left: 0; top: 50%; width: 42%; height: 1px; background: #e2e8f0; }
        .divider::after { content: ''; position: absolute; right: 0; top: 50%; width: 42%; height: 1px; background: #e2e8f0; }
        .footer-note { text-align: center; font-size: 0.72rem; color: #94a3b8; margin-top: 1.5rem; }
        .password-toggle { cursor: pointer; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="brand-icon">
            @php $logo = \App\Models\Setting::get('company_logo') @endphp
            @if($logo)
                <img src="{{ asset('storage/'.$logo) }}" style="height:40px;width:40px;object-fit:contain">
            @else
                <i class="bi bi-bank2" style="color:#fff"></i>
            @endif
        </div>
        <h1>{{ \App\Models\Setting::get('company_name', 'Big Cash Finance') }}</h1>
        <p>Loan Management System</p>
    </div>

    <div class="login-body">
        @if($errors->any())
            <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                <i class="bi bi-exclamation-circle-fill mt-1"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success d-flex gap-2 align-items-center">
                <i class="bi bi-check-circle-fill"></i>{{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text" style="border-radius:10px 0 0 10px;border-right:0;background:#f8fafc;border:1.5px solid #e2e8f0">
                        <i class="bi bi-envelope" style="color:#64748b"></i>
                    </span>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="you@example.com"
                           autocomplete="email" required
                           style="border-radius:0 10px 10px 0;border-left:0">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0" style="font-size:0.85rem">Password</label>
                    <a href="{{ route('password.request') }}" style="font-size:0.78rem;color:#16a34a;text-decoration:none">Forgot password?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text" style="border-radius:10px 0 0 10px;border-right:0;background:#f8fafc;border:1.5px solid #e2e8f0">
                        <i class="bi bi-lock" style="color:#64748b"></i>
                    </span>
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                           placeholder="••••••••" autocomplete="current-password" required
                           style="border-radius:0 0 0 0;border-left:0;border-right:0">
                    <span class="input-group-text password-toggle" onclick="togglePassword()" title="Show/hide password">
                        <i class="bi bi-eye" id="pwd-icon" style="color:#64748b"></i>
                    </span>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember" style="font-size:0.83rem;color:#64748b">Remember me</label>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="footer-note">
            &copy; {{ date('Y') }} {{ \App\Models\Setting::get('company_name', 'Big Cash Finance') }}.
            Secured by Big Cash LMS.
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const p = document.getElementById('password');
    const i = document.getElementById('pwd-icon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
