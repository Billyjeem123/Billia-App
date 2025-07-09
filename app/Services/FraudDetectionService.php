<?php

namespace App\Services;

use App\Models\FraudCheck;
use App\Models\TransactionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class FraudDetectionService
{
    private const DEBIT_TYPES = [
        'airtime', 'data', 'electricity', 'cable',
        'jamb', 'waec', 'wallet_transfer_out'
    ];

    private const CREDIT_TYPES = [
        'deposit', 'gift_card', 'wallet_transfer_in',
        'referral', 'external-deposit'
    ];

    /**
     * Perform comprehensive fraud detection on a user transaction
     */
    public function checkTransaction( $user, float $amount, string $transactionType = 'debit', array $context = []): array
    {
        $fraudCheckId = $this->generateFraudCheckId();

        try {
            # Log the start of fraud detection
            $this->logFraudActivity('fraud_check_started', [
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $user->id,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'context' => $context
            ]);

            # Only perform fraud detection on debit transactions
            if ($transactionType !== 'debit') {
                $this->storeFraudCheck(
                    $fraudCheckId,
                    $user->id,
                    $amount,
                    $transactionType,
                    'passed',
                    0,
                    [],
                    [],
                    $context,
                    'none',
                    'Credit transaction - no fraud check needed'
                );

                $this->logFraudActivity('fraud_check_skipped', [
                    'fraud_check_id' => $fraudCheckId,
                    'user_id' => $user->id,
                    'reason' => 'Not a debit transaction'
                ]);

                return $this->buildResponse(true, 'Credit transaction - no fraud check needed', [], $fraudCheckId);
            }

            # Perform fraud detection checks
            $fraudAnalysis = $this->performFraudAnalysis($user, $amount, $fraudCheckId);

            # Determine if fraud was detected
            $fraudDetected = $this->evaluateFraudRisk($fraudAnalysis, $user);

            # ALWAYS store the fraud check result - whether passed or failed
            $this->storeFraudCheck(
                $fraudCheckId,
                $user->id,
                $amount,
                $transactionType,
                $fraudDetected['detected'] ? 'failed' : 'passed',
                $fraudAnalysis['risk_score'],
                $fraudDetected['detected'] ? $fraudDetected['risk_factors'] : [],
                $fraudAnalysis['checks'],
                $context,
                $fraudDetected['action'],
                $fraudDetected['message']
            );

            if ($fraudDetected['detected']) {
                # Log fraud detection
                $this->logFraudActivity('fraud_detected', [
                    'fraud_check_id' => $fraudCheckId,
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'fraud_details' => $fraudAnalysis,
                    'risk_factors' => $fraudDetected['risk_factors'],
                    'recommended_action' => $fraudDetected['action']
                ]);

                # Take action based on fraud severity
                $this->handleFraudDetection($user, $fraudDetected, $fraudCheckId);

                return $this->buildResponse(
                    false,
                    $fraudDetected['message'],
                    $fraudAnalysis,
                    $fraudCheckId,
                    $fraudDetected['action']
                );
            }

            # Log successful fraud check
            $this->logFraudActivity('fraud_check_passed', [
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $user->id,
                'amount' => $amount,
                'fraud_details' => $fraudAnalysis
            ]);

            return $this->buildResponse(true, 'Fraud check passed', $fraudAnalysis, $fraudCheckId);

        } catch (Exception $e) {
            # Log error and store failed fraud check
            $this->logFraudActivity('fraud_check_error', [
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            # Store error record
            $this->storeFraudCheck(
                $fraudCheckId,
                $user->id,
                $amount,
                $transactionType,
                'error',
                0,
                ['system_error'],
                [],
                $context,
                'block_transaction',
                'System error during fraud check: ' . $e->getMessage()
            );

            return $this->buildResponse(false, 'System error during fraud check', [], $fraudCheckId, 'block_transaction');
        }
    }

    /**
     * Store fraud check in database - Enhanced with better error handling
     */


    // Solution 3: Wrap storeFraudCheck in DB::afterCommit
    /**
     * Store fraud check in database - Enhanced with better error handling
     * This will persist even if the main transaction rolls back
     */


    private function storeFraudCheck(
        string $fraudCheckId,
        int $userId,
        float $amount,
        string $transactionType,
        string $status,
        int $riskScore,
        array $riskFactors,
        array $checkDetails,
        array $context,
        string $actionTaken,
        string $message
    ): void {
        try {
            FraudCheck::create([
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $userId,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'status' => $status,
                'risk_score' => $riskScore,
                'risk_factors' => $riskFactors,
                'check_details' => $checkDetails,
                'context' => $context,
                'action_taken' => $actionTaken,
                'message' => $message,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            # Log successful storage
            $this->logFraudActivity('fraud_check_stored', [
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $userId,
                'status' => $status,
                'risk_score' => $riskScore
            ]);

        } catch (Exception $e) {
            # Log storage error
            $this->logFraudActivity('fraud_check_storage_error', [
                'fraud_check_id' => $fraudCheckId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            # Rethrow to handle in calling method
            throw $e;
        }
    }

    /**
     * Get fraud history for a user
     */
    public function getUserFraudHistory(User $user, int $limit = 50): Collection
    {
        return FraudCheck::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed fraud checks for a user
     */
    public function getUserFailedFraudChecks(User $user, int $limit = 20): Collection
    {
        return FraudCheck::forUser($user->id)
            ->failed()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get fraud statistics for a user
     */
    public function getUserFraudStatistics(User $user): array
    {
        $totalChecks = FraudCheck::forUser($user->id)->count();
        $failedChecks = FraudCheck::forUser($user->id)->failed()->count();
        $passedChecks = FraudCheck::forUser($user->id)->where('status', 'passed')->count();
        $errorChecks = FraudCheck::forUser($user->id)->where('status', 'error')->count();

        $lastFailedCheck = FraudCheck::forUser($user->id)
            ->failed()
            ->orderBy('created_at', 'desc')
            ->first();

        $mostCommonRiskFactors = FraudCheck::forUser($user->id)
            ->failed()
            ->get()
            ->pluck('risk_factors')
            ->flatten()
            ->countBy()
            ->sort()
            ->reverse()
            ->take(5);

        return [
            'user_id' => $user->id,
            'total_fraud_checks' => $totalChecks,
            'failed_checks' => $failedChecks,
            'passed_checks' => $passedChecks,
            'error_checks' => $errorChecks,
            'success_rate' => $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 2) : 0,
            'last_failed_check' => $lastFailedCheck ? $lastFailedCheck->created_at->format('Y-m-d H:i:s') : null,
            'most_common_risk_factors' => $mostCommonRiskFactors->toArray()
        ];
    }

    /**
     * Get detailed fraud analysis for a specific check
     */
    public function getFraudCheckDetails(string $fraudCheckId): ?FraudCheck
    {
        return FraudCheck::where('fraud_check_id', $fraudCheckId)->first();
    }

    /**
     * Get fraud trends for admin dashboard
     */
    public function getFraudTrends(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $dailyStats = FraudCheck::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $trends = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dayStats = $dailyStats->get($date, collect());

            $trends[] = [
                'date' => $date,
                'total_checks' => $dayStats->sum('count'),
                'failed_checks' => $dayStats->where('status', 'failed')->sum('count'),
                'passed_checks' => $dayStats->where('status', 'passed')->sum('count'),
                'error_checks' => $dayStats->where('status', 'error')->sum('count')
            ];
        }

        return array_reverse($trends);
    }

    /**
     * Get most common risk factors across all users
     */
    public function getGlobalRiskFactors(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $riskFactors = FraudCheck::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->get()
            ->pluck('risk_factors')
            ->flatten()
            ->countBy()
            ->sort()
            ->reverse()
            ->take(10);

        return $riskFactors->toArray();
    }

    /**
     * Perform detailed fraud analysis
     */
    private function performFraudAnalysis(User $user, float $amount, string $fraudCheckId): array
    {
        # Calculate legitimate deposits
        $totalDeposits = TransactionLog::where('user_id', $user->id)
            ->whereIn('category', self::CREDIT_TYPES)
            ->where('status', "successful")
            ->sum('amount');

        # Calculate total debits
        $totalDebits = TransactionLog::where('user_id', $user->id)
            ->whereIn('category', self::DEBIT_TYPES)
            ->where('status', "successful")
            ->sum('amount');

        # Get current balance
        $currentBalance = $user->wallet->amount ?? 0;

        # Calculate projected debits
        $projectedDebits = $totalDebits + $amount;

        # Calculate what the balance should be based on transactions
        $calculatedBalance = $totalDeposits - $totalDebits;

        # Perform individual checks
        $checks = [
            'balance_vs_deposits' => [
                'passed' => $currentBalance <= $totalDeposits,
                'current_balance' => $currentBalance,
                'total_deposits' => $totalDeposits,
                'difference' => $currentBalance - $totalDeposits
            ],
            'debits_vs_deposits' => [
                'passed' => $totalDebits <= $totalDeposits,
                'total_debits' => $totalDebits,
                'total_deposits' => $totalDeposits,
                'difference' => $totalDebits - $totalDeposits
            ],
            'projection_check' => [
                'passed' => $projectedDebits <= $totalDeposits,
                'projected_debits' => $projectedDebits,
                'total_deposits' => $totalDeposits,
                'difference' => $projectedDebits - $totalDeposits
            ],
            'balance_integrity_check' => [
                'passed' => abs($currentBalance - $calculatedBalance) < 0.01,
                'current_balance' => $currentBalance,
                'calculated_balance' => $calculatedBalance,
                'difference' => $currentBalance - $calculatedBalance,
                'description' => 'Checks if current balance matches transaction history'
            ],
            'sufficient_funds_check' => [
                'passed' => $currentBalance >= $amount,
                'current_balance' => $currentBalance,
                'requested_amount' => $amount,
                'difference' => $currentBalance - $amount,
                'description' => 'Checks if user has sufficient funds for transaction'
            ]
        ];

        # Calculate risk score
        $riskScore = $this->calculateRiskScore($checks, $user, $amount);

        return [
            'checks' => $checks,
            'risk_score' => $riskScore,
            'user_id' => $user->id,
            'amount' => $amount,
            'timestamp' => Carbon::now()->toISOString(),
            'fraud_check_id' => $fraudCheckId
        ];
    }

    /**
     * Evaluate fraud risk based on analysis
     */
    private function evaluateFraudRisk(array $analysis, User $user): array
    {
        $checks = $analysis['checks'];
        $riskScore = $analysis['risk_score'];

        $riskFactors = [];

        # Check each fraud condition
        if (!$checks['balance_vs_deposits']['passed']) {
            $riskFactors[] = 'Balance exceeds legitimate deposits';
        }

        if (!$checks['debits_vs_deposits']['passed']) {
            $riskFactors[] = 'Total debits exceed deposits';
        }

        if (!$checks['projection_check']['passed']) {
            $riskFactors[] = 'Projected debits would exceed deposits';
        }

        if (!$checks['balance_integrity_check']['passed']) {
            $riskFactors[] = 'Balance does not match transaction history';
        }

        if (!$checks['sufficient_funds_check']['passed']) {
            $riskFactors[] = 'Insufficient funds for transaction';
        }

        # Determine if fraud is detected
        $fraudDetected = !empty($riskFactors);

        $action = 'none';
        $message = 'Transaction approved';

        if ($fraudDetected) {
            if ($riskScore >= 80) {
                $action = 'ban_account';
                $message = 'Account has been suspended due to high-risk activity. Please contact support.';
            } elseif ($riskScore >= 60) {
                $action = 'restrict_account';
                $message = 'Transaction blocked due to security concerns. Please contact support.';
            } else {
                $action = 'block_transaction';
                $message = 'Transaction cannot be processed due to security concerns. Please verify your account or contact support.';
            }
        }

        return [
            'detected' => $fraudDetected,
            'risk_factors' => $riskFactors,
            'risk_score' => $riskScore,
            'action' => $action,
            'message' => $message
        ];
    }

    /**
     * Calculate risk score based on various factors
     */
    private function calculateRiskScore(array $checks, User $user, float $amount): int
    {
        $score = 0;

        # Base risk factors
        if (!$checks['balance_vs_deposits']['passed']) {
            $score += 30;
        }

        if (!$checks['debits_vs_deposits']['passed']) {
            $score += 25;
        }

        if (!$checks['projection_check']['passed']) {
            $score += 25;
        }

        if (!$checks['balance_integrity_check']['passed']) {
            $score += 35;
        }

        if (!$checks['sufficient_funds_check']['passed']) {
            $score += 20;
        }

        # Additional risk factors
        $balanceDifference = abs($checks['balance_integrity_check']['difference'] ?? 0);
        if ($balanceDifference > 100000) {
            $score += 20;
        }

        # Account age factor
        $accountAge = Carbon::now()->diffInDays($user->created_at);
        if ($accountAge < 7) {
            $score += 10;
        }

        return min($score, 100);
    }

    private function handleFraudDetection(User $user, array $fraudResult, string $fraudCheckId): void
    {
        switch ($fraudResult['action']) {
            case 'ban_account':
                $user->update([
                    'is_account_restricted' => 1,
                    'is_ban' => 1,
                    'view' => 0
                ]);
                $this->logFraudActivity('account_banned', [
                    'fraud_check_id' => $fraudCheckId,
                    'user_id' => $user->id,
                    'reason' => 'High-risk fraud detected'
                ]);
                break;

            case 'restrict_account':
                $user->update(['is_account_restricted' => 1]);
                $this->logFraudActivity('account_restricted', [
                    'fraud_check_id' => $fraudCheckId,
                    'user_id' => $user->id,
                    'reason' => 'Medium-risk fraud detected'
                ]);
                break;

            case 'block_transaction':
                $this->logFraudActivity('transaction_blocked', [
                    'fraud_check_id' => $fraudCheckId,
                    'user_id' => $user->id,
                    'reason' => 'Low-risk fraud detected'
                ]);
                break;
        }
    }

    private function logFraudActivity(string $activity, array $data): void
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'activity' => $activity,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data
        ];

        Log::channel('fraud')->info($activity, $logData);
    }

    private function generateFraudCheckId(): string
    {
        return 'FRAUD_' . strtoupper(uniqid()) . '_' . time();
    }

    private function buildResponse(bool $passed, string $message, array $details, string $fraudCheckId, string $action = 'none'): array
    {
        return [
            'passed' => $passed,
            'message' => $message,
            'details' => $details,
            'fraud_check_id' => $fraudCheckId,
            'action' => $action,
            'timestamp' => Carbon::now()->toISOString()
        ];
    }
}
