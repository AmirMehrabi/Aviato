<?php

namespace App\Support;

use App\Models\AdminTablePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminTableSort
{
    /**
     * Only database columns and Eloquent aggregate aliases declared here may be
     * used in ORDER BY clauses.
     *
     * @var array<string, array{columns: array<string, string>, default: array<int, array{0: string, 1: string}>, nullable?: array<int, string>}>
     */
    private const TABLES = [
        'customers' => [
            'columns' => ['name' => 'name', 'email' => 'email', 'status' => 'status', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
        ],
        'projects' => [
            'columns' => ['name' => 'name', 'is_default' => 'is_default', 'members_count' => 'members_count', 'virtual_machines_count' => 'virtual_machines_count', 'created_at' => 'created_at'],
            'default' => [['is_default', 'desc'], ['created_at', 'desc']],
        ],
        'virtual-machines' => [
            'columns' => ['display_name' => 'display_name', 'status' => 'status', 'cpu_cores' => 'cpu_cores', 'ram_gb' => 'ram_gb', 'disk_gb' => 'disk_gb', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
            'nullable' => ['display_name'],
        ],
        'api-activity' => [
            'columns' => ['method' => 'method', 'route' => 'route', 'status_code' => 'status_code', 'duration_ms' => 'duration_ms', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
        ],
        'billing-payments' => [
            'columns' => ['provider' => 'provider', 'amount' => 'amount', 'status' => 'status', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
        ],
        'billing-transactions' => [
            'columns' => ['type' => 'type', 'amount' => 'amount', 'balance_before' => 'balance_before', 'balance_after' => 'balance_after', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
        ],
        'billing-invoices' => [
            'columns' => ['number' => 'number', 'period_start' => 'period_start', 'period_end' => 'period_end', 'items_count' => 'items_count', 'tax_amount' => 'tax_amount', 'total_amount' => 'total_amount', 'issued_at' => 'issued_at'],
            'default' => [['issued_at', 'desc']],
            'nullable' => ['issued_at'],
        ],
        'billing-usage' => [
            'columns' => ['service_date' => 'service_date', 'amount' => 'amount', 'accruals_count' => 'accruals_count', 'settled_at' => 'settled_at'],
            'default' => [['service_date', 'desc']],
            'nullable' => ['settled_at'],
        ],
        'billing-wallets' => [
            'columns' => ['balance' => 'balance', 'is_locked' => 'is_locked', 'last_transaction_at' => 'last_transaction_at'],
            'default' => [['balance', 'asc']],
            'nullable' => ['last_transaction_at'],
        ],
        'billing-rates' => [
            'columns' => ['resource' => 'resource', 'monthly_price' => 'monthly_price', 'hourly_price' => 'hourly_price', 'billing_policy' => 'billing_policy', 'is_active' => 'is_active'],
            'default' => [['resource', 'asc']],
        ],
        'incidents' => [
            'columns' => ['title' => 'title', 'affected_service' => 'affected_service', 'status' => 'status', 'is_published' => 'is_published', 'started_at' => 'started_at'],
            'default' => [['started_at', 'desc']],
        ],
        'resellers' => [
            'columns' => ['name' => 'name', 'reseller_code' => 'reseller_code', 'reseller_status' => 'reseller_status', 'active_customers_count' => 'active_customers_count', 'reseller_commissions_sum_commission_amount' => 'reseller_commissions_sum_commission_amount', 'created_at' => 'created_at'],
            'default' => [['created_at', 'desc']],
        ],
        'tickets' => [
            'columns' => ['subject' => 'subject', 'status' => 'status', 'priority' => 'priority', 'last_activity_at' => 'last_activity_at', 'created_at' => 'created_at'],
            'default' => [['last_activity_at', 'desc'], ['created_at', 'desc']],
            'nullable' => ['last_activity_at'],
        ],
    ];

    /** @return array{table: string, column: string, direction: string, source: string} */
    public static function apply(Builder $query, Request $request, string $tableKey): array
    {
        $state = self::resolve($request, $tableKey);
        $table = self::table($tableKey);

        if ($state['source'] === 'default') {
            foreach ($table['default'] as [$column, $direction]) {
                self::nullsLast($query, $table, $column);
                $query->orderBy($table['columns'][$column], $direction);
            }
        } else {
            self::nullsLast($query, $table, $state['column']);
            $query->orderBy($table['columns'][$state['column']], $state['direction']);
        }

        $query->orderBy($query->getModel()->qualifyColumn('id'), $state['direction']);

        return $state;
    }

    /** @return array{table: string, column: string, direction: string, source: string} */
    public static function resolve(Request $request, string $tableKey): array
    {
        $table = self::table($tableKey);
        [$defaultColumn, $defaultDirection] = $table['default'][0];
        $legacy = self::legacySort($request->query('sort'));

        if ($legacy !== null && array_key_exists($legacy[0], $table['columns'])) {
            [$column, $direction] = $legacy;

            return ['table' => $tableKey, 'column' => $column, 'direction' => $direction, 'source' => 'query'];
        }

        $requestedColumn = $request->query('sort');
        if (is_string($requestedColumn) && array_key_exists($requestedColumn, $table['columns'])) {
            $direction = strtolower((string) $request->query('direction', 'asc'));
            $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

            return ['table' => $tableKey, 'column' => $requestedColumn, 'direction' => $direction, 'source' => 'query'];
        }

        $userId = Auth::guard('admin')->id();
        $preference = $userId
            ? AdminTablePreference::query()->where('user_id', $userId)->where('table_key', $tableKey)->first()
            : null;

        if ($preference && array_key_exists($preference->sort_column, $table['columns']) && in_array($preference->sort_direction, ['asc', 'desc'], true)) {
            return [
                'table' => $tableKey,
                'column' => $preference->sort_column,
                'direction' => $preference->sort_direction,
                'source' => 'preference',
            ];
        }

        return ['table' => $tableKey, 'column' => $defaultColumn, 'direction' => $defaultDirection, 'source' => 'default'];
    }

    public static function supports(string $tableKey, string $column): bool
    {
        return isset(self::TABLES[$tableKey]['columns'][$column]);
    }

    /** @return array<int, string> */
    public static function columns(string $tableKey): array
    {
        return array_keys(self::table($tableKey)['columns']);
    }

    public static function exists(string $tableKey): bool
    {
        return isset(self::TABLES[$tableKey]);
    }

    /** @return array{columns: array<string, string>, default: array<int, array{0: string, 1: string}>, nullable?: array<int, string>} */
    private static function table(string $tableKey): array
    {
        abort_unless(self::exists($tableKey), 404);

        return self::TABLES[$tableKey];
    }

    /**
     * The column is sourced exclusively from the registry above, so this raw
     * fragment never includes request input.
     *
     * @param  array{columns: array<string, string>, default: array<int, array{0: string, 1: string}>, nullable?: array<int, string>}  $table
     */
    private static function nullsLast(Builder $query, array $table, string $column): void
    {
        if (in_array($column, $table['nullable'] ?? [], true)) {
            $query->orderByRaw($table['columns'][$column].' IS NULL');
        }
    }

    /** @return array{0: string, 1: string}|null */
    private static function legacySort(mixed $sort): ?array
    {
        return match ($sort) {
            'latest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'name' => ['name', 'asc'],
            default => null,
        };
    }
}
