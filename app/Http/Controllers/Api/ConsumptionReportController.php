<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsumptionReportRequest;
use App\Http\Resources\ConsumptionReportResource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsumptionReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ConsumptionReportRequest $request): ConsumptionReportResource
    {
        $filters = $request->validated();
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $toExclusive = CarbonImmutable::parse($filters['to'])->addDay()->startOfDay();

        $aggregatedProducts = DB::table('stock_movements')
            ->join('departments', 'departments.id', '=', 'stock_movements.department_id')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.type', 'OUT')
            ->where('stock_movements.created_at', '>=', $from)
            ->where('stock_movements.created_at', '<', $toExclusive)
            ->when(
                isset($filters['department_id']),
                fn (Builder $query): Builder => $query->where('departments.id', $filters['department_id']),
            )
            ->select([
                'departments.id as department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
            ])
            ->selectRaw('SUM(stock_movements.quantity) as consumed_quantity')
            ->selectRaw('COUNT(stock_movements.id) as movement_count')
            ->groupBy(
                'departments.id',
                'departments.name',
                'departments.code',
                'products.id',
                'products.name',
                'products.sku',
            )
            ->orderBy('departments.name')
            ->orderBy('departments.id')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->get();

        $departments = $aggregatedProducts
            ->groupBy('department_id')
            ->map(fn (Collection $products): array => $this->departmentSummary($products))
            ->values()
            ->all();

        return new ConsumptionReportResource([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'department_id' => isset($filters['department_id']) ? (int) $filters['department_id'] : null,
            'departments' => $departments,
        ]);
    }

    /**
     * @param  Collection<int, object>  $products
     * @return array<string, mixed>
     */
    private function departmentSummary(Collection $products): array
    {
        $department = $products->first();

        return [
            'id' => (int) $department->department_id,
            'name' => $department->department_name,
            'code' => $department->department_code,
            'consumed_quantity' => (int) $products->sum('consumed_quantity'),
            'movement_count' => (int) $products->sum('movement_count'),
            'products' => $products->map(fn (object $product): array => [
                'id' => (int) $product->product_id,
                'name' => $product->product_name,
                'sku' => $product->product_sku,
                'consumed_quantity' => (int) $product->consumed_quantity,
                'movement_count' => (int) $product->movement_count,
            ])->all(),
        ];
    }
}
