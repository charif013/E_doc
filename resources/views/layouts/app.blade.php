<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'e-Doc อบต.พร่อน') }}</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <link  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link  rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link  href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link  rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

    <style>
        /* ─── CSS Variables ─────────────────────────────────── */
        :root {
            --font-sans: 'Sarabun', sans-serif;
            --sidebar-width: 260px;
            --topbar-height: 60px;

            /* Color Palette */
            --primary:       #0284c7;
            --primary-light: #e0f2fe;
            --primary-dark:  #0369a1;

            --accent-green:       #10b981;
            --accent-green-light: #d1fae5;
            --accent-green-hover: #f0fdf4;

            --bg-page:    #f4fbf7;
            --bg-white:   #ffffff;
            --bg-sidebar: #ffffff;

            --text-primary:   #1e293b;
            --text-secondary: #475569;
            --text-muted:     #94a3b8;

            --border-color:  #e2e8f0;
            --border-radius: 10px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.06);

            /* Status Colors */
            --success-bg: #f0fdf4; --success-text: #16a34a; --success-border: #bbf7d0;
            --warning-bg: #fffbeb; --warning-text: #d97706; --warning-border: #fde68a;
            --danger-bg:  #fef2f2; --danger-text:  #dc2626; --danger-border:  #fecaca;
            --info-bg:    #eff6ff; --info-text:    #2563eb; --info-border:    #bfdbfe;
        }

        /* ─── Reset ─────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            background: var(--bg-page);
            color: var(--text-primary);
            font-size: 15px;
            line-height: 1.6;
        }

        /* ─── App Layout ─────────────────────────────────── */
        .app-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ─── Sidebar ─────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
            transition: transform 0.3s cubic-bezier(.4, 0, .2, 1);
            z-index: 200;
            flex-shrink: 0;
        }

        .sidebar::-webkit-scrollbar       { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Sidebar Header */
        .sidebar-header {
            padding: 22px 20px 18px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo-img {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-logo-text    { line-height: 1.2; }
        .sidebar-logo-title   { font-size: 16px; font-weight: 700; color: var(--primary-dark); white-space: nowrap; }
        .sidebar-logo-sub     { font-size: 11.5px; color: var(--accent-green); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 175px; }

        /* Sidebar Sections */
        .sidebar-section         { padding: 12px 0; }
        .sidebar-section + .sidebar-section { border-top: 1px dashed var(--border-color); }

        .sidebar-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            padding: 4px 20px 8px;
        }

        /* Nav Links */
        .nav-link-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            margin: 2px 12px;
            border-radius: 8px;
            font-size: 14.5px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border-right: 3px solid transparent;
        }

        .nav-link-item:hover           { background: var(--accent-green-hover); color: var(--primary-dark); }
        .nav-link-item:hover .nav-icon { color: var(--accent-green); }

        .nav-link-item.active           { background: var(--primary-light); color: var(--primary-dark); font-weight: 600; border-right: 4px solid var(--accent-green); }
        .nav-link-item.active .nav-icon { color: var(--primary); }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
            color: #94a3b8;
            flex-shrink: 0;
            transition: color 0.2s ease;
        }

        .nav-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Danger nav (logout) */
        .nav-link-item.danger             { color: var(--danger-text); }
        .nav-link-item.danger .nav-icon   { color: #fca5a5; }
        .nav-link-item.danger:hover       { background: var(--danger-bg); color: var(--danger-text); border-right-color: transparent; }
        .nav-link-item.danger:hover .nav-icon { color: var(--danger-text); }

        /* Sidebar Footer */
        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid var(--border-color);
            padding: 12px 0;
            background: #fafafa;
        }

        /* ─── Main Content ─────────────────────────────────── */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
        }

        /* Topbar */
        .topbar {
            height: var(--topbar-height);
            background: var(--bg-white);
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .topbar-left  { display: flex; align-items: center; gap: 14px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }

        .topbar-title {
            font-size: 16.5px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .hamburger-btn {
            display: none;
            background: var(--primary-light);
            border: none;
            border-radius: 8px;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--primary-dark);
            font-size: 16px;
        }

        /* User Badge */
        .user-badge {
            background: var(--bg-white);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 4px 16px 4px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .user-badge:hover { background: var(--primary-light); border-color: var(--primary); }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--accent-green-light);
            color: var(--accent-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-info    { line-height: 1.2; text-align: left; }
        .user-name    { font-size: 13.5px; color: var(--text-primary); font-weight: 600; }
        .user-pos     { font-size: 11px; color: var(--primary); }

        /* Content Area */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .content-area::-webkit-scrollbar       { width: 6px; }
        .content-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(2, 132, 199, 0.4);
            z-index: 150;
            backdrop-filter: blur(2px);
        }

        /* ─── Responsive ─────────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0; left: 0; bottom: 0;
                transform: translateX(-100%);
                box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
            }

            .sidebar.open         { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .hamburger-btn        { display: flex; }
            .content-area         { padding: 16px; }
            .topbar               { padding: 0 16px; }
            .user-info            { display: none; }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            :root { --sidebar-width: 230px; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

@auth
<div class="app-wrapper">

    {{-- Mobile Overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar">

        {{-- Logo --}}
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="sidebar-logo-img">
                    <img src="{{ asset('images/logo.png') }}" alt="โลโก้ อบต.พร่อน"
                         onerror="this.src='https://via.placeholder.com/44x44?text=Logo'">
                </div>
                <div class="sidebar-logo-text">
                    <div class="sidebar-logo-title">e-Doc อบต.พร่อน</div>
                    <div class="sidebar-logo-sub">ระบบบริหารจัดการเอกสาร</div>
                </div>
            </div>
        </div>

        {{-- 🌟 1. ภาพรวมและติดตามงาน --}}
        <div class="sidebar-section">
            <div class="sidebar-label">แดชบอร์ดและภาพรวม</div>

            <a href="{{ url('/home') }}" class="nav-link-item {{ request()->is('home') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                <span class="nav-text">หน้าหลักของฉัน</span>
            </a>

            @hasanyrole('saraban|head|palad|deputy-palad|executive|super-admin')
            <a href="{{ route('dashboard.executive') }}" class="nav-link-item {{ request()->routeIs('dashboard.executive') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
                <span class="nav-text">รายงานภาพรวม</span>
            </a>
            @endhasanyrole
            
            @role('head')
            <a href="{{ route('documents.assigned') }}" class="nav-link-item {{ request()->routeIs('documents.assigned') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--warning-text);"><i class="fas fa-clipboard-list"></i></span>
                <span class="nav-text">งานที่ได้รับมอบหมาย</span>
            </a>
            @endrole
        </div>

        {{-- 🌟 2. งานสารบรรณ (สร้างและลงทะเบียน) --}}
        <div class="sidebar-section">
            <div class="sidebar-label">งานสารบรรณ (e-Saraban)</div>

            <a href="{{ route('documents.create') }}" class="nav-link-item {{ request()->routeIs('documents.create') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fas fa-file-signature"></i></span>
                <span class="nav-text">สร้างบันทึกข้อความ</span>
            </a>

            <a href="{{ route('documents.create_outgoing') }}" class="nav-link-item {{ request()->routeIs('documents.create_outgoing') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-file-export"></i></span>
                <span class="nav-text">อัปโหลดหนังสือส่งออก</span>
            </a>

            <a href="{{ route('documents.create_incoming') }}" class="nav-link-item {{ request()->routeIs('documents.create_incoming') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-file-import"></i></span>
                <span class="nav-text">อัปโหลดหนังสือรับเข้า</span>
            </a>

            @hasrole('saraban')
            <a href="{{ route('documents.registry') }}" class="nav-link-item {{ request()->routeIs('documents.registry') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fas fa-book"></i></span>
                <span class="nav-text">สมุดคุมเลขสารบรรณ</span>
            </a>
            @endhasrole
        </div>

        {{-- 🌟 3. งานพิจารณา / อนุมัติ (สำหรับผู้ที่มีสิทธิ์) --}}
        @hasanyrole('saraban|head|palad|deputy-palad|executive|super-admin')
        <div class="sidebar-section">
            <div class="sidebar-label">แฟ้มพิจารณาอนุมัติ</div>

            <a href="{{ route('documents.approve_list') }}" class="nav-link-item {{ request()->routeIs('documents.approve_list') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--primary);"><i class="fa-solid fa-folder-open"></i></span>
                <span class="nav-text">พิจารณาแฟ้มเอกสาร</span>
            </a>

            <a href="{{ route('leaves.approve_list') }}" class="nav-link-item {{ request()->routeIs('leaves.approve_list') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--accent-green);"><i class="fa-solid fa-clipboard-check"></i></span>
                <span class="nav-text">พิจารณาใบลา</span>
            </a>
            
          {{--  @role('executive')
            <a href="{{ route('documents.secret') }}" class="nav-link-item {{ request()->routeIs('documents.secret') ? 'active' : '' }}">
                <span class="nav-icon" style="color: #dc2626;"><i class="fa-solid fa-lock"></i></span>
                <span class="nav-text">แฟ้มเอกสารความลับ</span>
            </a>
            @endrole --}}
        </div>
        @endhasanyrole

        {{-- 🌟 4. ระบบบริการบุคลากร (HR & Facilities) --}}
        <div class="sidebar-section">
            <div class="sidebar-label">ระบบบุคลากร (HR)</div>

            <a href="{{ route('leaves.create') }}" class="nav-link-item {{ request()->routeIs('leaves.create') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-umbrella-beach"></i></span>
                <span class="nav-text">ยื่นใบลาพักผ่อน</span>
            </a>

            <a href="{{ route('leaves.index') }}" class="nav-link-item {{ request()->routeIs('leaves.index') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <span class="nav-text">ประวัติการลาของฉัน</span>
            </a>

            <a href="{{ route('bookings.index') }}" class="nav-link-item {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-calendar-check"></i></span>
                <span class="nav-text">จองห้องประชุม</span>
            </a>
        </div>

        {{-- 🌟 5. การตั้งค่าระบบ (สำหรับ Admin) --}}
        @hasrole('super-admin')
        <div class="sidebar-section" style="background: #f8fafc;">
            <div class="sidebar-label" style="color: var(--primary);">การตั้งค่าระบบ</div>

            <a href="{{ route('users.index') }}" class="nav-link-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span>
                <span class="nav-text">จัดการผู้ใช้งาน (Users)</span>
            </a>

            <a href="{{ route('holidays.index') }}" class="nav-link-item {{ request()->routeIs('holidays.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                <span class="nav-text">ตั้งค่าปฏิทินวันหยุด</span>
            </a>
        </div>
        @endhasrole

        {{-- 🌟 Logout --}}
        <div class="sidebar-footer">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="nav-link-item danger w-100 text-start border-0 bg-transparent">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">ออกจากระบบ</span>
                </button>
            </form>
        </div>

    </aside>
    {{-- /aside --}}

    {{-- Main Content --}}
    <div class="main-content">

        {{-- Topbar --}}
        <div class="topbar">
            <div class="topbar-left">
                <button class="hamburger-btn" id="hamburgerBtn"
                        onclick="openSidebar()" aria-label="เปิดเมนู">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-title">
                    @yield('title', 'ระบบบริหารเอกสาร')
                </div>
            </div>

            <div class="topbar-right">
                <div class="user-badge"
                     onclick="window.location.href='{{ route('profile.index') }}'"
                     title="คลิกเพื่อไปหน้าตั้งค่าโปรไฟล์"
                     role="button"
                     tabindex="0">
                    <div class="user-avatar">
                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="user-info">
                        <div class="user-name">{{ Auth::user()->name }}</div>
                        <div class="user-pos">{{ Auth::user()->position ?? 'ไม่มีตำแหน่ง' }}</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- /topbar --}}

        {{-- Page Content --}}
        <div class="content-area">
            @yield('content')
        </div>

    </div>
    {{-- /main-content --}}

</div>
{{-- /app-wrapper --}}

<script>
    function openSidebar() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sidebarOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    // ปิด sidebar เมื่อกดลิงก์บนมือถือ
    document.querySelectorAll('.nav-link-item').forEach(function (el) {
        el.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // Keyboard support สำหรับ user-badge
    document.querySelector('.user-badge')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            window.location.href = '{{ route('profile.index') }}';
        }
    });
</script>

@else
    @yield('content')
@endauth

@yield('scripts')

</body>
</html>