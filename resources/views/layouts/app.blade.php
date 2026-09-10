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

            --bg-page:    #ffffff;
            --bg-white:   #ffffff;
            --bg-sidebar: #ffffff;

            --text-primary:   #1e293b;
            --text-secondary: #475569;
            --text-muted:     #94a3b8;

            --border-color:  #e2e8f0;
            --border-radius: 10px;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-pill: 999px;
            --space-1: .25rem;
            --space-2: .5rem;
            --space-3: .75rem;
            --space-4: 1rem;
            --space-5: 1.5rem;
            --space-6: 2rem;
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
            background: #ffffff;
            color: var(--text-primary);
            font-size: 16px;
            line-height: 1.6;
            overflow-x: hidden;
        }

        img, svg, video, canvas { max-width: 100%; }
        input, select, textarea, button { max-width: 100%; }

        :focus-visible {
            outline: 3px solid rgba(2, 132, 199, .45) !important;
            outline-offset: 3px;
        }

        /* ─── e-Doc Design System: reusable UI primitives ─────────── */
        .ds-card { background: var(--bg-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
        .ds-btn { min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); padding: .55rem 1rem; border: 1px solid transparent; border-radius: var(--radius-pill); font-weight: 700; line-height: 1.2; text-decoration: none; transition: .18s ease; }
        .ds-btn-primary { color: #fff; background: var(--primary); border-color: var(--primary); }
        .ds-btn-primary:hover { color: #fff; background: var(--primary-dark); border-color: var(--primary-dark); }
        .ds-btn-secondary { color: var(--text-primary); background: #fff; border-color: var(--border-color); }
        .ds-btn-secondary:hover { color: var(--primary-dark); background: var(--primary-light); border-color: var(--primary); }
        .ds-form-control { width: 100%; min-height: 42px; padding: .6rem .8rem; color: var(--text-primary); background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); }
        .ds-form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2,132,199,.12); outline: 0; }
        .ds-status { display: inline-flex; align-items: center; gap: var(--space-1); padding: .3rem .65rem; border: 1px solid; border-radius: var(--radius-pill); font-size: .78rem; font-weight: 700; }
        .ds-status-success { color: var(--success-text); background: var(--success-bg); border-color: var(--success-border); }
        .ds-status-warning { color: var(--warning-text); background: var(--warning-bg); border-color: var(--warning-border); }
        .ds-status-danger { color: var(--danger-text); background: var(--danger-bg); border-color: var(--danger-border); }
        .ds-status-info { color: var(--info-text); background: var(--info-bg); border-color: var(--info-border); }
        .ds-alert { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-4); border: 1px solid; border-radius: var(--radius-md); }
        .ds-alert-success { color: var(--success-text); background: var(--success-bg); border-color: var(--success-border); }
        .ds-alert-warning { color: var(--warning-text); background: var(--warning-bg); border-color: var(--warning-border); }
        .ds-alert-danger { color: var(--danger-text); background: var(--danger-bg); border-color: var(--danger-border); }
        .ds-alert-info { color: var(--info-text); background: var(--info-bg); border-color: var(--info-border); }
        .ds-back-link { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: .45rem; padding: .45rem 1rem; color: var(--text-primary); background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-pill); box-shadow: var(--shadow-sm); font-size: .9rem; font-weight: 700; line-height: 1.2; text-decoration: none; white-space: nowrap; transition: .18s ease; }
        .ds-back-link:hover { color: var(--primary-dark); background: var(--primary-light); border-color: var(--primary); transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .ds-back-link i { font-size: .85rem; }

        .visually-hidden-focusable:focus {
            position: fixed !important;
            top: 8px; left: 8px;
            z-index: 10000;
            background: #fff;
            color: var(--primary-dark);
            padding: 10px 16px;
            border-radius: 8px;
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
            font-size: 13px;
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
            font-size: 15px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border-right: 3px solid transparent;
        }
        .nav-link-item .nav-text { min-width: 0; flex: 1; }
        .nav-count-badge { min-width: 24px; height: 24px; padding: 0 7px; display: inline-grid; place-items: center; flex: 0 0 auto; border-radius: var(--radius-pill); color: #fff; background: var(--danger-text); font-size: 12px; font-weight: 700; line-height: 1; box-shadow: 0 0 0 2px #fff; }

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
            background: #ffffff;
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
            min-width: 0;
            background: #ffffff;
        }

        /* Shared responsive safeguards for every Blade page. */
        .content-area > .container,
        .content-area > .container-fluid {
            max-width: 1440px;
            background-color: #ffffff !important;
        }

        .card, .row, [class*="col-"] { min-width: 0; }
        .card-body { overflow-wrap: anywhere; }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        .table-responsive > .table { margin-bottom: 0; }
        iframe { display: block; max-width: 100%; border: 0; }

        /* Keep controls comfortable on touch devices. */
        @media (pointer: coarse) {
            .btn, .form-control, .form-select, .input-group-text { min-height: 44px; }
            .nav-link-item { min-height: 44px; }
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
        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: 0; left: 0; bottom: 0;
                transform: translateX(-100%);
                box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
            }

            .sidebar.open         { transform: translateX(0); }
            .sidebar-overlay.open { display: block; }
            .hamburger-btn        { display: flex; }
            .content-area         { padding: 18px; }
            .topbar               { padding: 0 16px; }
        }

        @media (max-width: 767.98px) {
            :root { --topbar-height: 56px; }

            body { font-size: 14px; }
            .sidebar { width: min(86vw, 300px); min-width: min(86vw, 300px); }
            .content-area { padding: 12px; }
            .topbar { padding: 0 12px; gap: 8px; }
            .topbar-left { min-width: 0; gap: 9px; }
            .topbar-title {
                font-size: 15px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .user-info { display: none; }
            .user-badge { padding: 3px; border: 0; box-shadow: none; }
            .user-avatar { width: 34px; height: 34px; }

            .content-area > .container,
            .content-area > .container-fluid {
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-top: 8px !important;
            }

            .content-area .card-body { padding: 1rem; }
            .content-area .card-header { padding-left: 1rem; padding-right: 1rem; }
            .content-area h1 { font-size: 1.65rem; }
            .content-area h2 { font-size: 1.45rem; }
            .content-area h3, .content-area h4 { font-size: 1.2rem; }

            /* Common page headers and action bars wrap instead of overflowing. */
            .content-area .d-flex.justify-content-between {
                flex-wrap: wrap;
                gap: .75rem;
            }
            .content-area .d-flex.justify-content-end { flex-wrap: wrap; gap: .5rem; }
            .content-area .d-flex.justify-content-end > .btn,
            .content-area .d-flex.justify-content-end > button { flex: 1 1 auto; }

            .table-responsive {
                margin-inline: -1rem;
                width: calc(100% + 2rem);
            }
            .table-responsive > .table { min-width: 680px; }
            .table-responsive::after {
                content: 'เลื่อนตารางซ้าย–ขวาเพื่อดูข้อมูลเพิ่มเติม';
                display: block;
                position: sticky;
                left: 0;
                width: 100vw;
                padding: 6px 16px;
                color: var(--text-muted);
                background: #fff;
                font-size: 11px;
                text-align: center;
            }

            /* A4 previews become a readable fluid page on small screens. */
            .doc-container { padding: 12px !important; overflow-x: hidden !important; }
            .doc-container .doc-paper,
            .doc-paper[style*="210mm"] {
                width: 100% !important;
                min-height: 0 !important;
                padding: 24px 18px !important;
            }
            iframe[height="800px"], iframe[height="850px"], iframe[height="600px"] {
                height: 65vh !important;
                min-height: 420px;
            }

            .modal-dialog { margin: .75rem; }
            .dropdown-menu { max-width: calc(100vw - 24px); }
        }

        @media (max-width: 374.98px) {
            .content-area { padding: 8px; }
            .topbar-title { max-width: 190px; }
            .content-area .card-body { padding: .8rem; }
        }

        .file-attachment-preview { margin-top: 1rem; padding: 1rem; border: 1px solid #dbe4ee; border-radius: 12px; background: #f8fafc; }
        .file-attachment-preview__header { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .75rem; }
        .file-attachment-preview__name { min-width: 0; overflow-wrap: anywhere; font-weight: 700; color: #1e293b; }
        .file-attachment-preview__size { color: #64748b; font-size: .85rem; white-space: nowrap; }
        .file-attachment-preview__frame { display: block; width: 100%; height: min(62vh, 620px); min-height: 360px; border: 0; border-radius: 8px; background: #525659; }
        .file-attachment-preview__image { display: block; max-width: 100%; max-height: 620px; margin: 0 auto; border-radius: 8px; object-fit: contain; }
        @media (max-width: 767.98px) {
            .file-attachment-preview__header { align-items: flex-start; flex-direction: column; }
            .file-attachment-preview__frame { height: 55vh; min-height: 300px; }
        }
    </style>
</head>
<body>

@auth
<a class="visually-hidden-focusable" href="#main-content">ข้ามไปยังเนื้อหาหลัก</a>
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

        @php
            /*
             * Keep sidebar visibility aligned with the duties granted to the user.
             * Route middleware and Policies remain the actual security boundary;
             * these flags only prevent irrelevant destinations appearing in the UI.
             */
            $menuUser = Auth::user();
            $isSuperAdmin = $menuUser->hasRole('super-admin');
            $canUseDocumentWorkspace = $isSuperAdmin
                || $menuUser->hasAnyRole([
                    'executive', 'palad', 'deputy-palad', 'head', 'saraban', 'officer',
                    'finance', 'parcel', 'hr', 'analyst', 'engineer', 'education',
                    'health', 'disaster', 'auditor', 'teacher',
                ]);
            $canViewExecutiveDashboard = $isSuperAdmin
                || $menuUser->hasAnyRole(['executive', 'palad', 'deputy-palad', 'head', 'saraban']);
            // ทุกบทบาทในพื้นที่งานเอกสารสร้างบันทึกข้อความได้ตาม Route เดิม
            // รวมถึงนายก ปลัด รองปลัด และหัวหน้าส่วน/หัวหน้าสำนักปลัด
            $canCreateInternalDocument = $canUseDocumentWorkspace;
            $canUploadIncomingDocument = $canUseDocumentWorkspace
                && ($isSuperAdmin || $menuUser->can('receive_register'));
            $canUploadOutgoingDocument = $canUseDocumentWorkspace
                && ($isSuperAdmin || $menuUser->can('send_register'));
            $canViewDocumentRegistry = $isSuperAdmin
                || $menuUser->hasRole('saraban');
            $canReviewDocuments = $isSuperAdmin
                || $menuUser->hasAnyRole(['executive', 'palad', 'deputy-palad', 'head', 'saraban'])
                || ($reviewPendingCount ?? 0) > 0;
            $canReviewLeaves = $isSuperAdmin
                || $menuUser->hasAnyRole(['executive', 'palad', 'deputy-palad', 'head', 'hr', 'saraban', 'officer']);
            $canManageHolidays = $isSuperAdmin || $menuUser->hasRole('palad');
            $canSeeSarabanSection = $canCreateInternalDocument
                || $canUploadIncomingDocument
                || $canUploadOutgoingDocument
                || $canViewDocumentRegistry;
            $canSeeReviewSection = $canReviewDocuments || $canReviewLeaves;
            $canSeeAssignedWork = $canUseDocumentWorkspace || ($assignedPendingCount ?? 0) > 0;
        @endphp

        {{-- 🌟 1. ภาพรวมและติดตามงาน --}}
        <div class="sidebar-section">
            <div class="sidebar-label">แดชบอร์ดและภาพรวม</div>

            <a href="{{ url('/home') }}" class="nav-link-item {{ request()->is('home') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                <span class="nav-text">หน้าหลักของฉัน</span>
            </a>

            @if($canViewExecutiveDashboard)
            <a href="{{ route('dashboard.executive') }}" class="nav-link-item {{ request()->routeIs('dashboard.executive') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span>
                <span class="nav-text">รายงานภาพรวม</span>
            </a>
            @endif
            
            
            @if($canSeeAssignedWork)
            <a href="{{ route('documents.assigned') }}" class="nav-link-item {{ request()->routeIs('documents.assigned') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--warning-text);"><i class="fas fa-clipboard-list"></i></span>
                <span class="nav-text">งานที่ได้รับมอบหมาย</span>
                @if(($assignedPendingCount ?? 0) > 0)
                    <span class="nav-count-badge" aria-label="งานค้าง {{ $assignedPendingCount }} รายการ">{{ $assignedPendingCount > 99 ? '99+' : $assignedPendingCount }}</span>
                @endif
            </a>
            @endif

        </div>

        {{-- 🌟 2. งานสารบรรณ (สร้างและลงทะเบียน) --}}
        @if($canSeeSarabanSection)
        <div class="sidebar-section">
            <div class="sidebar-label">งานสารบรรณ (e-Saraban)</div>

            @if($canCreateInternalDocument)
            <a href="{{ route('documents.create') }}" class="nav-link-item {{ request()->routeIs('documents.create') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fas fa-file-signature"></i></span>
                <span class="nav-text">สร้างบันทึกข้อความ</span>
            </a>
            @endif

            @if($canUploadOutgoingDocument)
            <a href="{{ route('documents.create_outgoing') }}" class="nav-link-item {{ request()->routeIs('documents.create_outgoing') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-file-export"></i></span>
                <span class="nav-text">อัปโหลดหนังสือส่งออก</span>
            </a>
            @endif

            @if($canUploadIncomingDocument)
            <a href="{{ route('documents.create_incoming') }}" class="nav-link-item {{ request()->routeIs('documents.create_incoming') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-file-import"></i></span>
                <span class="nav-text">อัปโหลดหนังสือรับเข้า</span>
            </a>
            @endif

            @if($canViewDocumentRegistry)
            <a href="{{ route('documents.registry') }}" class="nav-link-item {{ request()->routeIs('documents.registry') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fas fa-book"></i></span>
                <span class="nav-text">สมุดคุมเลขสารบรรณ</span>
                @if(($numberingPendingCount ?? 0) > 0)
                    <span class="nav-count-badge" aria-label="เอกสารรอลงเลข {{ $numberingPendingCount }} รายการ">{{ $numberingPendingCount > 99 ? '99+' : $numberingPendingCount }}</span>
                @endif
            </a>
            @endif
        </div>
        @endif

        {{-- ผู้ใช้ทุกคนอาจได้รับเลือกให้อยู่ในเส้นทางเอกสาร --}}
        @if($canSeeReviewSection)
        <div class="sidebar-section">
            <div class="sidebar-label">แฟ้มพิจารณาอนุมัติ</div>

            @if($canReviewDocuments)
            <a href="{{ route('documents.approve_list') }}" class="nav-link-item {{ request()->routeIs('documents.approve_list') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--primary);"><i class="fa-solid fa-folder-open"></i></span>
                <span class="nav-text">พิจารณาแฟ้มเอกสาร</span>
                @if(($reviewPendingCount ?? 0) > 0)
                    <span class="nav-count-badge" aria-label="แฟ้มรอพิจารณา {{ $reviewPendingCount }} รายการ">{{ $reviewPendingCount > 99 ? '99+' : $reviewPendingCount }}</span>
                @endif
            </a>
            @endif

            @if($canReviewLeaves)
            <a href="{{ route('leaves.approve_list') }}" class="nav-link-item {{ request()->routeIs('leaves.approve_list') ? 'active' : '' }}">
                <span class="nav-icon" style="color: var(--accent-green);"><i class="fa-solid fa-clipboard-check"></i></span>
                <span class="nav-text">พิจารณาใบลา</span>
                @if(($leaveReviewPendingCount ?? 0) > 0)
                    <span class="nav-count-badge" aria-label="ใบลารอพิจารณา {{ $leaveReviewPendingCount }} รายการ">{{ $leaveReviewPendingCount > 99 ? '99+' : $leaveReviewPendingCount }}</span>
                @endif
            </a>
            @endif
            
          {{--  @role('executive')
            <a href="{{ route('documents.secret') }}" class="nav-link-item {{ request()->routeIs('documents.secret') ? 'active' : '' }}">
                <span class="nav-icon" style="color: #dc2626;"><i class="fa-solid fa-lock"></i></span>
                <span class="nav-text">แฟ้มเอกสารความลับ</span>
            </a>
            @endrole --}}

        </div>
        @endif
        

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

            <a href="{{ route('admin.rooms.index') }}" class="nav-link-item {{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-door-open"></i></span>
                <span class="nav-text">จัดการห้องประชุม</span>
            </a>

            <a href="{{ route('admin.audit_logs.index') }}" class="nav-link-item {{ request()->routeIs('admin.audit_logs.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="nav-text">ประวัติการใช้งานระบบ</span>
            </a>

            <a href="{{ route('holidays.index') }}" class="nav-link-item {{ request()->routeIs('holidays.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                <span class="nav-text">ตั้งค่าปฏิทินวันหยุด</span>
            </a>
        </div>
        @endhasrole

        @if($canManageHolidays && !$isSuperAdmin)
        <div class="sidebar-section">
            <div class="sidebar-label">การตั้งค่าระบบ</div>
            <a href="{{ route('holidays.index') }}" class="nav-link-item {{ request()->routeIs('holidays.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                <span class="nav-text">ตั้งค่าปฏิทินวันหยุด</span>
            </a>
        </div>
        @endif

        @role('auditor')
        <div class="sidebar-section" style="background: #f8fafc;">
            <div class="sidebar-label" style="color: var(--primary);">การตรวจสอบระบบ</div>
            <a href="{{ route('admin.audit_logs.index') }}" class="nav-link-item {{ request()->routeIs('admin.audit_logs.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="nav-text">ประวัติการใช้งานระบบ</span>
            </a>
        </div>
        @endrole

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
        <main class="content-area" id="main-content" tabindex="-1">
            @yield('content')
        </main>

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

    // ปิด sidebar เมื่อกดลิงก์บนมือถือและแท็บเล็ตแนวตั้ง
    document.querySelectorAll('.nav-link-item').forEach(function (el) {
        el.addEventListener('click', function () {
            if (window.innerWidth < 992) closeSidebar();
        });
    });

    // ปิดด้วย Escape และล้างสถานะเมื่อหมุนจอ/ขยายกลับเป็นเดสก์ท็อป
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });

    // Keyboard support สำหรับ user-badge
    document.querySelector('.user-badge')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            window.location.href = '{{ route('profile.index') }}';
        }
    });

    // Preview กลางสำหรับช่องแนบเอกสารทุกหน้า (หน้าที่มี Preview เฉพาะใช้ data-file-preview="custom")
    (function initializeAttachmentPreviews() {
        const objectUrls = new WeakMap();

        function readableSize(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
            const units = ['B', 'KB', 'MB', 'GB'];
            const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            return `${(bytes / Math.pow(1024, unit)).toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
        }

        function clearPreview(input) {
            const oldUrl = objectUrls.get(input);
            if (oldUrl) URL.revokeObjectURL(oldUrl);
            objectUrls.delete(input);
            document.querySelector(`.file-attachment-preview[data-preview-for="${input.dataset.previewId}"]`)?.remove();
        }

        function renderPreview(input) {
            clearPreview(input);
            const file = input.files?.[0];
            if (!file) return;

            const preview = document.createElement('section');
            preview.className = 'file-attachment-preview';
            preview.dataset.previewFor = input.dataset.previewId;
            preview.setAttribute('aria-live', 'polite');

            const header = document.createElement('div');
            header.className = 'file-attachment-preview__header';
            const name = document.createElement('div');
            name.className = 'file-attachment-preview__name';
            name.textContent = `เอกสารที่แนบ: ${file.name}`;
            const size = document.createElement('div');
            size.className = 'file-attachment-preview__size';
            size.textContent = readableSize(file.size);
            header.append(name, size);
            preview.appendChild(header);

            const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
            const isImage = file.type.startsWith('image/');
            if (isPdf || isImage) {
                const url = URL.createObjectURL(file);
                objectUrls.set(input, url);
                if (isPdf) {
                    const frame = document.createElement('iframe');
                    frame.className = 'file-attachment-preview__frame';
                    frame.src = `${url}#toolbar=1&navpanes=0`;
                    frame.title = `ตัวอย่างเอกสาร ${file.name}`;
                    preview.appendChild(frame);
                } else {
                    const image = document.createElement('img');
                    image.className = 'file-attachment-preview__image';
                    image.src = url;
                    image.alt = `ตัวอย่างเอกสาร ${file.name}`;
                    preview.appendChild(image);
                }
            } else {
                const note = document.createElement('div');
                note.className = 'text-muted small';
                note.textContent = 'ไฟล์ประเภทนี้ไม่รองรับการแสดงตัวอย่าง แต่แนบไฟล์เรียบร้อยแล้ว';
                preview.appendChild(note);
            }

            input.insertAdjacentElement('afterend', preview);
        }

        document.querySelectorAll('input[type="file"][name]:not([data-file-preview="custom"])').forEach((input, index) => {
            input.dataset.previewId = `attachment-${index}`;
            input.addEventListener('change', () => renderPreview(input));
            if (input.files?.length) renderPreview(input);
        });

        window.addEventListener('beforeunload', () => {
            document.querySelectorAll('input[type="file"][name]').forEach(clearPreview);
        });
    })();
</script>

@else
    @yield('content')
@endauth

@yield('scripts')
@stack('scripts')

</body>
</html>
