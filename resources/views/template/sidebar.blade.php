<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('home') }}" class="app-brand-link">
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
        <li class="menu-item {{ request()->routeIs('home') ? 'active' : '' }}">
            <a href="{{ route('home') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Home">Home</div>
            </a>
        </li>

        <!-- Header: PAYMENT & BILLING -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Payment &amp; Billing</span>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Fitur Tagihan">
                <i class="menu-icon tf-icons bx bx-receipt"></i>
                <div data-i18n="Invoices">Invoices</div>
                <div class="badge bg-label-primary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('virtual-accounts.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle" title="Manajemen Mandiri Virtual Account">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div data-i18n="Virtual Accounts">Virtual Accounts</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('virtual-accounts.fixed.*') ? 'active' : '' }}">
                    <a href="{{ route('virtual-accounts.fixed.index') }}" class="menu-link">
                        <div data-i18n="Fixed Virtual Accounts">Fixed Virtual Accounts</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="javascript:void(0);" class="menu-link">
                        <div data-i18n="Dynamic Virtual Accounts">Dynamic Virtual Accounts</div>
                        <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Monitoring Transaksi Pembayaran">
                <i class="menu-icon tf-icons bx bx-transfer-alt"></i>
                <div data-i18n="Transactions">Transactions</div>
                <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Rekonsiliasi Bank Mandiri">
                <i class="menu-icon tf-icons bx bx-check-double"></i>
                <div data-i18n="Reconciliation">Reconciliation</div>
                <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>

        <!-- Header: REPORTING -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Reporting</span>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Laporan Pembayaran & Keuangan">
                <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                <div data-i18n="Reports">Reports</div>
                <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>

        <!-- Header: SYSTEM & SECURITY -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">System &amp; Security</span>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Pengguna dan Hak Akses">
                <i class="menu-icon tf-icons bx bx-user-check"></i>
                <div data-i18n="Users & Permissions">Users &amp; Permissions</div>
                <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('settings.branches.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle" title="Pengaturan Sistem">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('settings.branches.*') ? 'active' : '' }}">
                    <a href="{{ route('settings.branches.index') }}" class="menu-link">
                        <div data-i18n="Branches">Branches</div>
                    </a>
                </li>
            </ul>
        </li>
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" title="Log Aktivitas & Riwayat API">
                <i class="menu-icon tf-icons bx bx-history"></i>
                <div data-i18n="Audit Logs">Audit Logs</div>
                <div class="badge bg-label-secondary fs-tiny rounded-pill ms-auto">Soon</div>
            </a>
        </li>
    </ul>
</aside>
