<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceService
{
    /**
     * Get all invoices with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::with(['paymentCategory', 'userable', 'payable']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply invoice type filter
        if (!empty($filters['invoice_type'])) {
            $query->where('invoice_type', $filters['invoice_type']);
        }

        // Apply payment category filter
        if (!empty($filters['payment_category_id'])) {
            $query->where('payment_catgory_id', $filters['payment_category_id']);
        }

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Apply amount range filter
        if (!empty($filters['amount_from'])) {
            $query->where('amount', '>=', $filters['amount_from']);
        }
        if (!empty($filters['amount_to'])) {
            $query->where('amount', '<=', $filters['amount_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Find invoice by ID
     */
    public function findById(int $id): ?Invoice
    {
        return Invoice::with(['paymentCategory', 'userable', 'payable'])->find($id);
    }

    /**
     * Find invoice by reference
     */
    public function findByReference(string $reference): ?Invoice
    {
        return Invoice::where('reference', $reference)->first();
    }

    /**
     * Find invoice by invoice number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        return Invoice::where('invoice_number', $invoiceNumber)->first();
    }

    /**
     * Create new invoice
     */
    public function create(array $data): Invoice
    {
        // Generate invoice number if not provided
        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = $this->generateInvoiceNumber();
        }

        return Invoice::create($data);
    }

    /**
     * Update invoice
     */
    public function update(int $id, array $data): bool
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return false;
        }

        return $invoice->update($data);
    }

    /**
     * Delete invoice
     */
    public function delete(int $id): bool
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return false;
        }

        return $invoice->delete();
    }

    /**
     * Get invoices by user
     */
    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::with(['paymentCategory', 'payable'])
            ->where('userable_id', $userId);

        // Apply additional filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get invoices by payment category
     */
    public function getByPaymentCategory(int $categoryId): Collection
    {
        return Invoice::with(['userable', 'payable'])
            ->where('payment_catgory_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => Invoice::count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'pending' => Invoice::where('status', 'pending')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
            'total_amount' => Invoice::sum('amount'),
            'paid_amount' => Invoice::where('status', 'paid')->sum('amount'),
        ];
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(int $id, array $paymentData = []): bool
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return false;
        }

        $updateData = [
            'status' => 'paid',
            'payment_date' => $paymentData['payment_date'] ?? now(),
        ];

        if (!empty($paymentData['payment_method'])) {
            $updateData['payment_method'] = $paymentData['payment_method'];
        }

        if (!empty($paymentData['transaction_reference'])) {
            $updateData['transaction_reference'] = $paymentData['transaction_reference'];
        }

        return $invoice->update($updateData);
    }

    /**
     * Cancel invoice
     */
    public function cancel(int $id, ?string $reason = null): bool
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return false;
        }

        return $invoice->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now()
        ]);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        // Get the last invoice number for this month
        $lastInvoice = Invoice::where('invoice_number', 'like', "{$prefix}-{$year}{$month}%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}-{$year}{$month}{$newNumber}";
    }

    /**
     * Get monthly invoice summary
     */
    public function getMonthlySummary(int $year, int $month): array
    {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        return [
            'total_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_amount' => Invoice::whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
            'paid_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'paid')->count(),
            'paid_amount' => Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'paid')->sum('amount'),
        ];
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(): Collection
    {
        return Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->with(['paymentCategory', 'userable'])
            ->orderBy('due_date')
            ->get();
    }
}
