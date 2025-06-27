<?php

namespace App\Services;

use App\Models\TransactionLog;
use Illuminate\Support\Facades\Auth;

class TransactionService
{

    public function getAllUserTransactions(array $filters = []): array
    {
        $query = TransactionLog::where('user_id', Auth::id());

        if (!empty($filters['service_type'])) {
            $query->where('service_type', 'LIKE', '%' . $filters['service_type'] . '%');
        }

        if (!empty($filters['amount'])) {
            $query->where('amount', $filters['amount']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', 'LIKE', '%' . $filters['status'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $this->paginate($query);
    }

    public function getUserTransactionById($id): ?TransactionLog
    {
        return TransactionLog::where('user_id', Auth::id())->find($id);
    }

    private function paginate($query): array
    {
        $page = (int) request()->get('page', 1);
        $perPage = (int) request()->get('per_page', 10);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $data = $query->skip($offset)->take($perPage)->get();

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + count($data),
            ]
        ];
    }

}
