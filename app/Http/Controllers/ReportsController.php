<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\Implementations\PaymentReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(private readonly PaymentReportService $reports) {}

    public function index(ReportFilterRequest $request): View
    {
        $filters = $request->validated();
        $query = $this->reports->query($filters['report'], $filters);

        return view('reports.index', [
            'reportOptions' => $this->reports->reports(),
            'columns' => $this->reports->columns($filters['report']),
            'rows' => $query->paginate(25)->withQueryString(),
            'metrics' => $this->reports->metrics($query, $filters['report']),
            'filters' => $filters,
            'branches' => DB::table('branches')->orderBy('name')->get(['id', 'name']),
            'banks' => DB::table('banks')->orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions($filters['report']),
        ]);
    }

    public function export(ReportFilterRequest $request): StreamedResponse
    {
        $filters = $request->validated();
        $columns = $this->reports->columns($filters['report']);
        $query = $this->reports->query($filters['report'], $filters);
        $filename = Str::slug($filters['report']).'-'.$filters['from'].'-'.$filters['to'].'.csv';

        return response()->streamDownload(function () use ($columns, $query): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('CSV output stream could not be opened.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($columns));

            foreach ($query->cursor() as $row) {
                $values = [];
                foreach ($columns as $key => $label) {
                    $value = $row->{$key};
                    $values[] = $key === 'amount' || $key === 'transaction_count'
                        ? $value
                        : $this->safeCsvText($value);
                }
                fputcsv($output, $values);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(string $report): array
    {
        return match ($report) {
            'unpaid' => ['pending' => 'Belum dibayar', 'expired' => 'Kedaluwarsa'],
            'reconciliations' => [
                'matched' => 'Sesuai',
                'amount_mismatch' => 'Nominal berbeda',
                'not_found' => 'Tidak ditemukan',
                'duplicate' => 'Duplikat',
                'missing_from_bank' => 'Tidak ada di bank',
            ],
            'issues' => [
                'failed' => 'Gagal',
                'verification_required' => 'Perlu verifikasi',
                'pending' => 'Menunggu callback',
                '400' => 'HTTP 400',
                '401' => 'HTTP 401',
                '404' => 'HTTP 404',
                '409' => 'HTTP 409',
                '422' => 'HTTP 422',
                '500' => 'HTTP 500',
                '502' => 'HTTP 502',
            ],
            'summary', 'branches', 'students' => ['paid' => 'Dibayar'],
            default => [],
        };
    }

    private function safeCsvText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+\-@]/u', $text) === 1 ? "'".$text : $text;
    }
}
