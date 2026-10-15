@php($currentUser = auth()->user())
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route($currentUser->landingRouteName()) }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('assets/img/6462fbd7cab78.png') }}" alt="MHIS" style="width: 32px;">
            </span>
            <span class="app-brand-text demo menu-text fw-bolder ms-2">MHIS</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <!-- Main / Dashboard -->
        @if ($currentUser->hasPermission('dashboard.view'))
        <li class="menu-item {{ request()->routeIs('home') ? 'active' : '' }}">
            <a href="{{ route('home') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Home">Home</div>
            </a>
        </li>
        @endif

        <!-- Header: PAYMENT & BILLING -->
        @if ($currentUser->hasPermission('invoices.view') || $currentUser->hasPermission('fixed_virtual_accounts.view') || $currentUser->hasPermission('dynamic_virtual_accounts.view') || $currentUser->hasPermission('transactions.view') || $currentUser->hasPermission('reconciliations.view'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Payment &amp; Billing</span>
        </li>
        @endif
        @if ($currentUser->hasPermission('invoices.view'))
        <li class="menu-item {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
            <a href="{{ route('invoices.index') }}" class="menu-link" title="Tagihan dan hasil transaksi">
                <i class="menu-icon tf-icons bx bx-receipt"></i>
                <div data-i18n="Invoices">Invoices</div>
            </a>
        </li>
        @endif
        @if ($currentUser->hasPermission('fixed_virtual_accounts.view') || $currentUser->hasPermission('dynamic_virtual_accounts.view'))
        <li class="menu-item {{ request()->routeIs('virtual-accounts.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle" title="Manajemen Mandiri Virtual Account">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div data-i18n="Virtual Accounts">Virtual Accounts</div>
            </a>
            <ul class="menu-sub">
                @if ($currentUser->hasPermission('fixed_virtual_accounts.view'))
                <li class="menu-item {{ request()->routeIs('virtual-accounts.fixed.*') ? 'active' : '' }}">
                    <a href="{{ route('virtual-accounts.fixed.index') }}" class="menu-link">
                        <div data-i18n="Fixed Virtual Accounts">Fixed Virtual Accounts</div>
                    </a>
                </li>
                @endif
                @if ($currentUser->hasPermission('dynamic_virtual_accounts.view'))
                <li class="menu-item {{ request()->routeIs('virtual-accounts.dynamic.*') ? 'active' : '' }}">
                    <a href="{{ route('virtual-accounts.dynamic.index') }}" class="menu-link">
                        <div data-i18n="Dynamic Virtual Accounts">Dynamic Virtual Accounts</div>
                    </a>
                </li>
                @endif
            </ul>
        </li>
        @endif
        @if ($currentUser->hasPermission('transactions.view'))
        <li class="menu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
            <a href="{{ route('transactions.index') }}" class="menu-link" title="Monitoring Transaksi Pembayaran">
                <i class="menu-icon tf-icons bx bx-transfer-alt"></i>
                <div data-i18n="Transactions">Transactions</div>
            </a>
        </li>
        @endif
        @if ($currentUser->hasPermission('reconciliations.view'))
        <li class="menu-item {{ request()->routeIs('reconciliations.*') ? 'active' : '' }}">
            <a href="{{ route('reconciliations.index') }}" class="menu-link" title="Rekonsiliasi laporan bank">
                <i class="menu-icon tf-icons bx bx-check-double"></i>
                <div data-i18n="Reconciliation">Reconciliation</div>
            </a>
        </li>
        @endif

        <!-- Header: REPORTING -->
        @if ($currentUser->hasPermission('reports.view'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Reporting</span>
        </li>
        <li class="menu-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <a href="{{ route('reports.index') }}" class="menu-link" title="Laporan Pembayaran & Keuangan">
                <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                <div data-i18n="Reports">Reports</div>
            </a>
        </li>
        @endif

        <!-- Header: SYSTEM & SECURITY -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">System &amp; Security</span>
        </li>
        @if ($currentUser->hasRole('super_admin'))
        <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <a href="{{ route('users.index') }}" class="menu-link" title="Pengguna">
                <i class="menu-icon tf-icons bx bx-user-check"></i>
                <div data-i18n="Users">Users</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <a href="{{ route('roles.index') }}" class="menu-link" title="Role dan izin">
                <i class="menu-icon tf-icons bx bx-shield-quarter"></i>
                <div data-i18n="Roles & Permissions">Roles &amp; Permissions</div>
            </a>
        </li>
        @endif
        @if ($currentUser->hasPermission('branches.view') || $currentUser->hasPermission('callback_api_keys.view'))
        <li class="menu-item {{ request()->routeIs('settings.branches.*', 'settings.callback-api-key.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle" title="Pengaturan Sistem">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
            <ul class="menu-sub">
                @if ($currentUser->hasPermission('branches.view'))
                <li class="menu-item {{ request()->routeIs('settings.branches.*') ? 'active' : '' }}">
                    <a href="{{ route('settings.branches.index') }}" class="menu-link">
                        <div data-i18n="Branches">Branches</div>
                    </a>
                </li>
                @endif
                @if ($currentUser->hasPermission('callback_api_keys.view'))
                <li class="menu-item {{ request()->routeIs('settings.callback-api-key.*') ? 'active' : '' }}">
                    <a href="{{ route('settings.callback-api-key.index') }}" class="menu-link">
                        <div data-i18n="Callback API Key">Callback API Key</div>
                    </a>
                </li>
                @endif
            </ul>
        </li>
        @endif
        @if ($currentUser->hasPermission('audit_logs.view'))
        <li class="menu-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
            <a href="{{ route('audit-logs.index') }}" class="menu-link" title="Log Aktivitas & Riwayat API">
                <i class="menu-icon tf-icons bx bx-history"></i>
                <div data-i18n="Audit Logs">Audit Logs</div>
            </a>
        </li>
        @endif
    </ul>
</aside>
