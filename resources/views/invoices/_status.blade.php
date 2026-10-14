@switch($invoice->displayStatus())
    @case('paid')
        <span class="badge bg-label-success">Paid</span>
    @break
    @case('pending')
        <span class="badge bg-label-warning">Pending</span>
    @break
    @case('expired')
        <span class="badge bg-label-secondary">Expired</span>
    @break
    @default
        <span class="badge bg-label-secondary">{{ ucfirst($invoice->status) }}</span>
@endswitch
