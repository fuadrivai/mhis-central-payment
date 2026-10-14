@extends('template.main')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Invoices</h4>
        <p class="text-muted mb-0">Daftar tagihan. Gunakan tombol aksi untuk melihat detail invoice.</p>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('invoices.index') }}" id="invoice-filter-form">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="button" class="btn btn-outline-secondary d-flex justify-content-between align-items-center"
                        style="width: 180px;" data-bs-toggle="collapse" data-bs-target="#invoice-filter-panel"
                        aria-expanded="false" aria-controls="invoice-filter-panel">
                        <span>Filter</span>
                        <i class="bx bx-chevron-down"></i>
                    </button>
                    <div class="input-group" style="width: 460px; max-width: 100%;">
                        <select class="form-select flex-grow-0" style="width: 160px;" aria-label="Search by" name="search_by">
                            <option value="bill_code" @selected(($filters['search_by'] ?? 'bill_code') === 'bill_code')>Bill Code</option>
                            <option value="invoice_number" @selected(($filters['search_by'] ?? '') === 'invoice_number')>Invoice</option>
                            <option value="email" @selected(($filters['search_by'] ?? '') === 'email')>Email</option>
                        </select>
                        <input type="text" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search Bill Code" aria-label="Search invoices">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bx bx-search me-1"></i> Search
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-cloud-download me-1"></i> Excel
                    </button>
                    <button type="button" class="btn btn-primary px-3" aria-label="Pengaturan">
                        <i class="bx bx-cog"></i>
                    </button>
                </div>
            </div>

            <div class="collapse mt-4" id="invoice-filter-panel">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Clear All</a>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
                <div class="row g-4">
                    <div class="col-6 col-lg-3">
                        <h6 class="mb-3">Date</h6>
                        @foreach (['paid_at' => 'Paid Date', 'created_at' => 'Created Date', 'expired_at' => 'Expiry Date'] as $value => $label)
                            <div class="form-check mb-3">
                                <input class="form-check-input invoice-date-filter-toggle" type="checkbox" value="{{ $value }}"
                                    id="filter-date-{{ $value }}" name="date_filters[]" data-date-range="date-range-{{ $value }}"
                                    @checked(in_array($value, $filters['date_filters'] ?? [], true))>
                                <label class="form-check-label text-muted" for="filter-date-{{ $value }}">{{ $label }}</label>
                            </div>
                            <div class="invoice-date-range d-none mb-3" id="date-range-{{ $value }}">
                                <div class="d-flex align-items-center gap-2">
                                    <input type="date" class="form-control form-control-sm"
                                        style="min-width: 0; flex: 1 1 0;"
                                        name="date_ranges[{{ $value }}][from]"
                                        value="{{ data_get($filters, 'date_ranges.'.$value.'.from', now()->subMonth()->startOfDay()->format('Y-m-d')) }}"
                                        aria-label="{{ $label }} start date">
                                    <span class="text-muted">-</span>
                                    <input type="date" class="form-control form-control-sm"
                                        style="min-width: 0; flex: 1 1 0;"
                                        name="date_ranges[{{ $value }}][to]"
                                        value="{{ data_get($filters, 'date_ranges.'.$value.'.to', now()->endOfDay()->format('Y-m-d')) }}"
                                        aria-label="{{ $label }} end date">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-6 col-lg-3">
                        <h6 class="mb-3">Status</h6>
                        @foreach (['settled' => 'Settled', 'paid' => 'Paid', 'pending' => 'Pending', 'expired' => 'Expired'] as $value => $label)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="statuses[]" value="{{ $value }}"
                                    id="filter-status-{{ $value }}" @checked(in_array($value, $filters['statuses'] ?? [], true))>
                                <label class="form-check-label text-muted" for="filter-status-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-6 col-lg-3">
                        <h6 class="mb-3">Created From</h6>
                        @foreach (['api' => 'API', 'dashboard' => 'Dashboard', 'integration' => 'Integration', 'on_demand' => 'On Demand', 'recurring' => 'Recurring', 'mobile' => 'Mobile'] as $value => $label)
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="created_from[]" value="{{ $value }}"
                                    id="filter-source-{{ $value }}" @checked(in_array($value, $filters['created_from'] ?? [], true))>
                                <label class="form-check-label text-muted" for="filter-source-{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-6 col-lg-3">
                        <h6 class="mb-3">Amount</h6>
                        <select class="form-select mb-3" aria-label="Amount operator" name="amount_operator">
                            <option value="equals" @selected(($filters['amount_operator'] ?? 'equals') === 'equals')>Equals to</option>
                            <option value="greater" @selected(($filters['amount_operator'] ?? '') === 'greater')>Greater than</option>
                            <option value="less" @selected(($filters['amount_operator'] ?? '') === 'less')>Less than</option>
                        </select>
                        <input type="number" class="form-control" name="amount" value="{{ $filters['amount'] ?? '' }}"
                            min="0" step="0.01" aria-label="Amount" id="invoice-filter-amount">
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Bill Code</th>
                        <th>Payer Email</th>
                        <th>Description</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>@include('invoices._status')</td>
                            <td>{{ $invoice->created_at?->format('d M Y H:i') }}</td>
                            <td class="fw-semibold">{{ $invoice->bill_code }}</td>
                            <td>{{ $invoice->email ?? '-' }}</td>
                            <td>{{ $invoice->bill_name }}</td>
                            <td class="text-end">Rp. {{ number_format((float) $invoice->total_amount, 0, '.', ',') }}</td>
                            <td class="text-center">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary"
                                    aria-label="Detail invoice">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bx bx-receipt fs-1 text-muted d-block mb-2"></i>
                                <p class="text-muted mb-0">Belum ada invoice.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())
            <div class="card-body border-top">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection

@section('content-script')
    <script>
        document.querySelectorAll('.invoice-date-filter-toggle').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const dateRange = document.getElementById(this.dataset.dateRange);

                if (dateRange) {
                    dateRange.classList.toggle('d-none', !this.checked);
                }
            });
        });

        const searchField = document.querySelector('[name="search_by"]');
        const searchInput = document.querySelector('[name="search"]');
        const searchPlaceholders = {
            bill_code: 'Search Bill Code',
            invoice_number: 'Search Invoice',
            email: 'Search Email',
        };

        searchField.addEventListener('change', function () {
            searchInput.placeholder = searchPlaceholders[this.value];
        });
    </script>
@endsection
