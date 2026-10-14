<?php

namespace App\Services\Implementations;

use App\Models\Bank;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\PaymentTransaction;
use App\Services\Contracts\BankReconciliationServiceInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationService implements BankReconciliationServiceInterface
{
    private const MAX_ROWS = 10000;

    private const REPORT_TIMEZONE = 'Asia/Jakarta';

    public function activeBanks(): Collection
    {
        return Bank::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function paginateRuns(int $perPage = 20): LengthAwarePaginator
    {
        return BankReconciliation::query()
            ->with(['bank:id,name,code', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function reconcile(
        int $bankId,
        int $uploadedBy,
        string $periodFrom,
        string $periodTo,
        UploadedFile $statementFile,
    ): BankReconciliation {
        $rows = $this->readStatement($statementFile, $periodFrom, $periodTo);
        $periodStart = Carbon::parse($periodFrom, self::REPORT_TIMEZONE)->startOfDay()->utc();
        $periodEnd = Carbon::parse($periodTo, self::REPORT_TIMEZONE)->endOfDay()->utc();
        $references = array_values(array_unique(array_column($rows, 'reference_no')));
        $transactionsByReference = PaymentTransaction::query()
            ->where('bank_id', $bankId)
            ->whereIn('payment_reference_no', $references)
            ->get()
            ->keyBy('payment_reference_no');
        $seenReferences = [];
        $items = [];
        $counts = [
            'matched' => 0,
            'amount_mismatch' => 0,
            'not_found' => 0,
            'duplicate' => 0,
            'missing_from_bank' => 0,
        ];
        $now = now();

        foreach ($rows as $row) {
            $transaction = $transactionsByReference->get($row['reference_no']);

            if (isset($seenReferences[$row['reference_no']])) {
                $status = 'duplicate';
            } elseif ($transaction === null) {
                $status = 'not_found';
            } elseif ($this->normalizeAmount($row['amount']) !== $this->normalizeAmount((string) $transaction->paid_amount)) {
                $status = 'amount_mismatch';
            } else {
                $status = 'matched';
            }

            $seenReferences[$row['reference_no']] = true;
            $counts[$status]++;
            $items[] = [
                'bank_reconciliation_id' => null,
                'payment_transaction_id' => $transaction?->id,
                'row_number' => $row['row_number'],
                'reference_no' => $row['reference_no'],
                'statement_amount' => $row['amount'],
                'internal_amount' => $transaction?->paid_amount,
                'statement_paid_at' => $row['paid_at']->toDateTimeString(),
                'status' => $status,
                'raw_data' => json_encode($row['raw_data'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $reportedReferences = array_keys($seenReferences);

        return DB::transaction(function () use ($bankId, $uploadedBy, $periodFrom, $periodTo, $periodStart, $periodEnd, $reportedReferences, $statementFile, $counts, $items, $now): BankReconciliation {
            $reconciliation = BankReconciliation::query()->create([
                'bank_id' => $bankId,
                'uploaded_by' => $uploadedBy,
                'original_filename' => mb_substr($statementFile->getClientOriginalName(), 0, 255),
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'matched_count' => $counts['matched'],
                'amount_mismatch_count' => $counts['amount_mismatch'],
                'not_found_count' => $counts['not_found'],
                'duplicate_count' => $counts['duplicate'],
                'missing_from_bank_count' => $counts['missing_from_bank'],
            ]);

            foreach (array_chunk($items, 500) as $itemChunk) {
                BankReconciliationItem::query()->insert(array_map(
                    fn (array $item): array => [...$item, 'bank_reconciliation_id' => $reconciliation->id],
                    $itemChunk,
                ));
            }

            $missingFromBankCount = 0;

            PaymentTransaction::query()
                ->where('bank_id', $bankId)
                ->whereNotNull('payment_reference_no')
                ->whereBetween('paid_at', [$periodStart, $periodEnd])
                ->whereNotIn('payment_reference_no', $reportedReferences)
                ->select(['id', 'payment_reference_no', 'paid_amount'])
                ->chunkById(500, function (Collection $transactions) use (&$missingFromBankCount, $reconciliation, $now): void {
                    $missingItems = [];

                    foreach ($transactions as $transaction) {
                        $missingFromBankCount++;
                        $missingItems[] = [
                            'bank_reconciliation_id' => $reconciliation->id,
                            'payment_transaction_id' => $transaction->id,
                            'row_number' => null,
                            'reference_no' => $transaction->payment_reference_no,
                            'statement_amount' => null,
                            'internal_amount' => $transaction->paid_amount,
                            'statement_paid_at' => null,
                            'status' => 'missing_from_bank',
                            'raw_data' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    BankReconciliationItem::query()->insert($missingItems);
                });

            $reconciliation->update(['missing_from_bank_count' => $missingFromBankCount]);

            return $reconciliation->refresh();
        });
    }

    public function detail(BankReconciliation $reconciliation, int $perPage = 50): BankReconciliation
    {
        $reconciliation->load(['bank:id,name,code', 'uploader:id,name']);
        $reconciliation->setRelation(
            'items',
            $reconciliation->items()
                ->with([
                    'paymentTransaction:id,payment_reference_no,invoice_id',
                    'paymentTransaction.invoice:id,invoice_number',
                ])
                ->orderByRaw('row_number IS NULL')
                ->orderBy('row_number')
                ->orderBy('id')
                ->paginate($perPage),
        );

        return $reconciliation;
    }

    /**
     * @return array<int, array{row_number: int, reference_no: string, amount: string, paid_at: Carbon, raw_data: array<string, string>}>
     */
    private function readStatement(UploadedFile $statementFile, string $periodFrom, string $periodTo): array
    {
        $path = $statementFile->getRealPath();
        $stream = $path === false ? false : fopen($path, 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages([
                'statement_file' => 'File laporan tidak dapat dibaca.',
            ]);
        }

        try {
            $headers = fgetcsv($stream, null, ',', '"', '');

            if ($headers === false || $headers === [null]) {
                throw ValidationException::withMessages([
                    'statement_file' => 'File CSV kosong atau tidak memiliki header.',
                ]);
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(static fn (string $header): string => trim($header), $headers);

            if (count($headers) !== 3 || array_diff(['reference_no', 'amount', 'paid_at'], $headers) !== []) {
                throw ValidationException::withMessages([
                    'statement_file' => 'Header CSV harus berisi reference_no,amount,paid_at.',
                ]);
            }

            $rows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $lineNumber++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages([
                        'statement_file' => "Jumlah kolom pada baris {$lineNumber} tidak sesuai header.",
                    ]);
                }

                $rawData = array_combine($headers, array_map(
                    static fn (?string $value): string => trim((string) $value),
                    $values,
                ));
                $reference = $rawData['reference_no'];
                $amount = $rawData['amount'];
                $paidAt = $rawData['paid_at'];

                if ($reference === '' || mb_strlen($reference) > 100) {
                    throw ValidationException::withMessages([
                        'statement_file' => "Nomor referensi pada baris {$lineNumber} kosong atau terlalu panjang.",
                    ]);
                }

                if (! preg_match('/\A\d{1,12}(?:\.\d{1,2})?\z/', $amount)) {
                    throw ValidationException::withMessages([
                        'statement_file' => "Nominal pada baris {$lineNumber} harus berupa angka desimal maksimal 2 digit.",
                    ]);
                }

                $parsedPaidAt = $this->parseTimestamp($paidAt);

                if ($parsedPaidAt === null) {
                    throw ValidationException::withMessages([
                        'statement_file' => "Waktu pada baris {$lineNumber} harus ISO-8601, contoh 2026-10-14T09:00:02+07:00.",
                    ]);
                }

                $localPaidDate = $parsedPaidAt->copy()->timezone(self::REPORT_TIMEZONE)->toDateString();

                if ($localPaidDate < $periodFrom || $localPaidDate > $periodTo) {
                    throw ValidationException::withMessages([
                        'statement_file' => "Tanggal pada baris {$lineNumber} berada di luar rentang laporan.",
                    ]);
                }

                $rows[] = [
                    'row_number' => $lineNumber,
                    'reference_no' => $reference,
                    'amount' => $this->normalizeAmount($amount),
                    'paid_at' => $parsedPaidAt,
                    'raw_data' => $rawData,
                ];

                if (count($rows) > self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'statement_file' => 'File laporan maksimal berisi 10.000 transaksi.',
                    ]);
                }
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'statement_file' => 'File CSV harus memiliki minimal satu transaksi.',
                ]);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function parseTimestamp(string $value): ?Carbon
    {
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
            return null;
        }

        $timestamp = str_ends_with($value, 'Z')
            ? substr($value, 0, -1).'+00:00'
            : $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $timestamp);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return Carbon::instance($date)->utc();
    }

    private function normalizeAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }
}
