<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.pwa-head')
    <title>@yield('title', 'Dashboard') — {{ \App\Models\Setting::get('company_name', config('bigcash.company.name')) }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- Flatpickr Date Picker -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1a2332;
            --sidebar-text: #a8b4c1;
            --sidebar-active: #4f9ef8;
            --sidebar-hover-bg: rgba(255,255,255,0.07);
            --header-height: 60px;
            --kobo-primary: #2563eb;
            --kobo-success: #16a34a;
            --kobo-danger: #dc2626;
            --kobo-warning: #d97706;
        }

        body { background: #f1f5f9; font-family: 'Inter', system-ui, -apple-system, sans-serif; }

        /* ── Sidebar ── */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        #sidebar .sidebar-brand {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }
        #sidebar .sidebar-brand img { height: 32px; width: 32px; object-fit: contain; }
        #sidebar .nav-section-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(168,180,193,0.5);
            padding: 1rem 1.5rem 0.3rem;
        }
        #sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 0.55rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            border-radius: 0;
            font-size: 0.875rem;
            transition: background 0.15s, color 0.15s;
            text-decoration: none;
        }
        #sidebar .nav-link i { font-size: 1rem; width: 1.2rem; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            background: var(--sidebar-hover-bg);
            color: #fff;
        }
        #sidebar .nav-link.active { border-left: 3px solid var(--sidebar-active); color: var(--sidebar-active); }
        #sidebar .nav-link .badge { font-size: 0.65rem; }

        /* ── Main content ── */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin 0.3s ease;
        }

        /* ── Topbar ── */
        #topbar {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 999;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 1rem;
        }
        #topbar .page-title { font-weight: 600; font-size: 1rem; color: #1e293b; margin: 0; }

        /* ── Cards ── */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            transition: box-shadow 0.2s;
        }
        .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .stat-card .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 700; color: #1e293b; line-height: 1; }
        .stat-card .stat-label { font-size: 0.78rem; color: #64748b; margin-top: 0.2rem; }

        /* ── Tables ── */
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .table-card .table-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .table > thead > tr > th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600; background: #f8fafc; }
        .table > tbody > tr:hover { background: #f8fafc; }

        /* ── Responsive ── */
        @media (max-width: 991px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #main-content { margin-left: 0; }
        }

        /* ── Alerts / Flash ── */
        .flash-message { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 300px; max-width: 420px; }

        /* ── Form ── */
        .form-label { font-weight: 500; font-size: 0.875rem; color: #374151; }
        .form-control, .form-select { border-color: #d1d5db; font-size: 0.875rem; }
        .form-control:focus, .form-select:focus { border-color: var(--kobo-primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        /* ── AI Chat bubble ── */
        #ai-chat-fab {
            position: fixed; bottom: 24px; right: 24px;
            width: 52px; height: 52px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border-radius: 50%; border: none;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 20px rgba(37,99,235,0.4);
            cursor: pointer; z-index: 9998;
            transition: transform 0.2s;
        }
        #ai-chat-fab:hover { transform: scale(1.1); }
        #ai-chat-panel {
            position: fixed; bottom: 88px; right: 24px;
            width: 360px; height: 480px;
            background: #fff; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            display: none; flex-direction: column;
            z-index: 9997; border: 1px solid #e2e8f0;
        }
        #ai-chat-panel.open { display: flex; }
        .ai-message { max-width: 85%; border-radius: 12px; padding: 0.6rem 0.9rem; font-size: 0.85rem; }
        .ai-message.user { background: var(--kobo-primary); color: #fff; margin-left: auto; }
        .ai-message.bot  { background: #f1f5f9; color: #1e293b; }

        /* ── Loader ── */
        .page-loader { position: fixed; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #2563eb, #7c3aed); z-index: 99999; animation: loading 1.5s ease infinite; }
        @keyframes loading { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    </style>

    @stack('styles')
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="page-loader" style="display:none"></div>

<!-- ── Sidebar ───────────────────────────────────────────────────────────── -->
<nav id="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        @php $logo = \App\Models\Setting::get('company_logo') @endphp
        @if($logo)
            <img src="{{ asset('storage/'.$logo) }}" alt="Logo">
        @else
            <i class="bi bi-bank2" style="font-size:1.5rem;color:#4f9ef8"></i>
        @endif
        {{ \App\Models\Setting::get('company_name', 'Big Cash') }}
    </a>

    <div class="py-2">
        <span class="nav-section-label">Main</span>

        <a href="{{ route('admin.dashboard') }}" class="nav-link @active('admin/dashboard')">
            <i class="bi bi-grid-1x2-fill"></i> Dashboard
        </a>

        @canany(['borrowers.view'])
        <a href="{{ route('admin.borrowers.index') }}" class="nav-link @active('admin/borrowers*')">
            <i class="bi bi-people-fill"></i> Borrowers
        </a>
        @endcanany

        @canany(['loans.view'])
        <a href="{{ route('admin.loans.index') }}" class="nav-link @active('admin/loans*')">
            <i class="bi bi-cash-stack"></i> Loans
            @php $pendingCount = \App\Models\Loan::whereIn('status',['submitted','under_review','recommended'])->when(!auth()->user()->isSuperAdmin() && !auth()->user()->hasRole('admin'), fn($q)=>$q->where('branch_id',auth()->user()->branch_id))->count() @endphp
            @if($pendingCount > 0)
                <span class="badge bg-warning text-dark ms-auto">{{ $pendingCount }}</span>
            @endif
        </a>
        @endcanany

        @canany(['repayments.view'])
        <a href="{{ route('admin.repayments.index') }}" class="nav-link @active('admin/repayments*')">
            <i class="bi bi-receipt"></i> Repayments
        </a>
        @endcanany

        @canany(['products.view'])
        <span class="nav-section-label">Configuration</span>
        <a href="{{ route('admin.products.index') }}" class="nav-link @active('admin/products*')">
            <i class="bi bi-box-seam"></i> Loan Products
        </a>
        <a href="{{ route('admin.products.calculator') }}" class="nav-link">
            <i class="bi bi-calculator"></i> Loan Calculator
        </a>
        @endcanany

        @canany(['branches.view'])
        <a href="{{ route('admin.branches.index') }}" class="nav-link @active('admin/branches*')">
            <i class="bi bi-building"></i> Branches
        </a>
        @endcanany

        @canany(['users.view'])
        <a href="{{ route('admin.users.index') }}" class="nav-link @active('admin/users*')">
            <i class="bi bi-person-badge"></i> Staff Users
        </a>
        @endcanany

        @canany(['reports.view'])
        <span class="nav-section-label">Analytics</span>
        <a href="{{ route('admin.reports.index') }}" class="nav-link @active('admin/reports*')">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        @endcanany

        @canany(['settings.view'])
        <span class="nav-section-label">System</span>
        <a href="{{ route('admin.settings.index') }}" class="nav-link @active('admin/settings*')">
            <i class="bi bi-gear"></i> Settings
        </a>
        @endcanany
    </div>

    <!-- User info at bottom -->
    <div style="position:sticky;bottom:0;background:rgba(0,0,0,0.2);padding:0.75rem 1.5rem;margin-top:auto;">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white"
                 style="width:32px;height:32px;font-size:0.75rem;font-weight:700;flex-shrink:0">
                {{ auth()->user()->initials }}
            </div>
            <div style="overflow:hidden">
                <div style="color:#fff;font-size:0.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ auth()->user()->name }}</div>
                <div style="color:#a8b4c1;font-size:0.7rem">{{ auth()->user()->roles->first()?->name ?? 'User' }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="ms-auto">
                @csrf
                <button type="submit" class="btn btn-link p-0" style="color:#a8b4c1" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

<!-- ── Main Content ───────────────────────────────────────────────────────── -->
<div id="main-content">

    <!-- Topbar -->
    <div id="topbar">
        <button class="btn btn-link p-0 d-lg-none" id="sidebar-toggle">
            <i class="bi bi-list" style="font-size:1.4rem"></i>
        </button>
        <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="badge bg-light text-dark border" style="font-size:0.7rem">
                <i class="bi bi-building2 me-1"></i>
                {{ auth()->user()->branch?->name ?? 'All Branches' }}
            </span>
            @canany(['ai.use'])
            <button class="btn btn-sm" id="ai-chat-fab-topbar" style="background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;border-radius:8px;font-size:0.78rem">
                <i class="bi bi-stars me-1"></i> BigCashAI
            </button>
            @endcanany
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="flash-message">
        <div class="alert alert-success alert-dismissible shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif
    @if(session('error'))
    <div class="flash-message">
        <div class="alert alert-danger alert-dismissible shadow-sm" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    <!-- Page Content -->
    <div class="p-3 p-lg-4">
        @yield('content')
    </div>
</div>

<!-- ── AI Chat Panel ──────────────────────────────────────────────────────── -->
@canany(['ai.use'])
<button id="ai-chat-fab" title="BigCashAI Assistant">
    <i class="bi bi-stars" style="color:#fff;font-size:1.3rem"></i>
</button>

<div id="ai-chat-panel">
    <div style="padding:1rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:0.5rem">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-stars" style="color:#fff;font-size:0.9rem"></i>
        </div>
        <div>
            <div style="font-weight:600;font-size:0.9rem">BigCashAI Assistant</div>
            <div style="font-size:0.72rem;color:#64748b">Powered by AI</div>
        </div>
        <button class="btn btn-link ms-auto p-0" id="ai-chat-clear" title="Clear chat" style="color:#94a3b8;font-size:0.8rem">
            <i class="bi bi-trash3"></i>
        </button>
        <button class="btn btn-link p-0" id="ai-chat-close" style="color:#94a3b8">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div id="ai-chat-messages" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.75rem">
        <div class="ai-message bot">👋 Hello! I'm BigCashAI. I can help you with loan analysis, policy questions, and borrower assessments.</div>
    </div>
    <div style="padding:0.75rem;border-top:1px solid #e2e8f0;display:flex;gap:0.5rem">
        <input type="text" id="ai-chat-input" class="form-control form-control-sm" placeholder="Ask anything about lending..." style="border-radius:20px">
        <button class="btn btn-primary btn-sm" id="ai-chat-send" style="border-radius:20px;padding:0.3rem 0.9rem">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>
@endcanany

<!-- ── Sidebar Overlay for Mobile ─────────────────────────────────────────── -->
<div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999" onclick="closeSidebar()"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ── Sidebar ────────────────────────────────────────────────────────────────
function openSidebar()  { document.getElementById('sidebar').classList.add('show'); document.getElementById('sidebar-overlay').style.display='block'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('show'); document.getElementById('sidebar-overlay').style.display='none'; }
document.getElementById('sidebar-toggle')?.addEventListener('click', openSidebar);

// ── Flash dismiss ──────────────────────────────────────────────────────────
setTimeout(() => document.querySelectorAll('.flash-message .alert').forEach(a => { if(bootstrap.Alert.getOrCreateInstance(a)) bootstrap.Alert.getOrCreateInstance(a).close(); }), 5000);

// ── Select2 ───────────────────────────────────────────────────────────────
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    $('.select2-ajax').select2({
        theme: 'bootstrap-5', width: '100%',
        ajax: {
            url: '{{ route("admin.borrowers.search") }}',
            dataType: 'json',
            delay: 300,
            data: d => ({ q: d.term }),
            processResults: d => ({ results: d }),
            cache: true
        },
        minimumInputLength: 2,
        placeholder: 'Search by name, phone, or Ghana Card...'
    });

    // Date pickers
    flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });
    flatpickr('.datepicker-past', { dateFormat: 'Y-m-d', maxDate: 'today', allowInput: true });
    flatpickr('.datepicker-future', { dateFormat: 'Y-m-d', minDate: 'today', allowInput: true });
    flatpickr('.daterange', { mode: 'range', dateFormat: 'Y-m-d', allowInput: true });
});

// ── AI Chat ───────────────────────────────────────────────────────────────
@canany(['ai.use'])
const aiPanel     = document.getElementById('ai-chat-panel');
const aiMessages  = document.getElementById('ai-chat-messages');
const aiInput     = document.getElementById('ai-chat-input');
const aiSend      = document.getElementById('ai-chat-send');

function toggleAI() { aiPanel?.classList.toggle('open'); }
document.getElementById('ai-chat-fab')?.addEventListener('click', toggleAI);
document.getElementById('ai-chat-fab-topbar')?.addEventListener('click', toggleAI);
document.getElementById('ai-chat-close')?.addEventListener('click', () => aiPanel?.classList.remove('open'));

function appendMessage(text, role) {
    const div = document.createElement('div');
    div.className = `ai-message ${role}`;
    div.textContent = text;
    aiMessages.appendChild(div);
    aiMessages.scrollTop = aiMessages.scrollHeight;
}

async function sendAIMessage() {
    const msg = aiInput?.value.trim();
    if (!msg) return;
    appendMessage(msg, 'user');
    if (aiInput) aiInput.value = '';
    if (aiSend) aiSend.disabled = true;

    const thinking = document.createElement('div');
    thinking.className = 'ai-message bot';
    thinking.innerHTML = '<span class="placeholder-wave"><span class="placeholder col-8"></span></span>';
    aiMessages.appendChild(thinking);
    aiMessages.scrollTop = aiMessages.scrollHeight;

    try {
        const resp = await fetch('{{ route("admin.ai.chat") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ message: msg })
        });
        const data = await resp.json();
        thinking.textContent = data.response || 'Sorry, I could not respond.';
    } catch (e) {
        thinking.textContent = 'Error connecting. Please try again.';
    }
    if (aiSend) aiSend.disabled = false;
}

aiSend?.addEventListener('click', sendAIMessage);
aiInput?.addEventListener('keypress', e => { if (e.key === 'Enter') sendAIMessage(); });

document.getElementById('ai-chat-clear')?.addEventListener('click', async () => {
    await fetch('{{ route("admin.ai.chat_clear") }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
    aiMessages.innerHTML = '<div class="ai-message bot">Chat cleared. How can I help you?</div>';
});
@endcanany
</script>

@include('layouts.pwa-install')
<script src="{{ asset('js/pwa.js') }}"></script>
@stack('scripts')
</body>
</html>
