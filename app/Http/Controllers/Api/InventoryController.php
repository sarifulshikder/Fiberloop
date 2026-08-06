<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Resources\Api\InventoryItemResource;
use App\Http\Resources\Api\StockTransactionResource;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    /**
     * Get list of inventory items assigned to the authenticated customer.
     */
    public function customerInventory(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $items = InventoryItem::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', [InventoryStatus::ASSIGNED, InventoryStatus::NEEDS_INSPECTION])
            ->with(['stockTransactions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => InventoryItemResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Get details of a specific inventory item assigned to the customer.
     */
    public function customerInventoryItem(Request $request, string $uuid): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $item = InventoryItem::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $customer->id)
            ->with(['stockTransactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory item not found or not assigned to you',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new InventoryItemResource($item),
        ]);
    }

    /**
     * Get inventory item history/transactions for a customer's item.
     */
    public function customerInventoryHistory(Request $request, string $uuid): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $item = InventoryItem::query()
            ->where('uuid', $uuid)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory item not found or not assigned to you',
            ], 404);
        }

        $transactions = StockTransaction::query()
            ->where('inventory_item_id', $item->id)
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => StockTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Staff API: Get all inventory items with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $query = InventoryItem::query()
            ->with(['customer', 'reseller', 'createdBy', 'updatedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->has('warehouse')) {
            $query->where('warehouse', $request->warehouse);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('serial_number')) {
            $query->where('serial_number', 'like', '%' . $request->serial_number . '%');
        }

        if ($request->has('mac_address')) {
            $query->where('mac_address', 'like', '%' . $request->mac_address . '%');
        }

        $items = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => InventoryItemResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Staff API: Get details of a specific inventory item.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeStaff($request);

        $item = InventoryItem::query()
            ->where('uuid', $uuid)
            ->with(['customer', 'reseller', 'createdBy', 'updatedBy', 'stockTransactions'])
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory item not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new InventoryItemResource($item),
        ]);
    }

    /**
     * Staff API: Create a new inventory item.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:17',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'purchase_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'purchase_date' => 'nullable|date',
            'warranty_months' => 'nullable|integer|min:0',
            'warehouse' => 'nullable|string|max:100',
            'bin_location' => 'nullable|string|max:100',
            'status' => 'required|string|in:' . implode(',', InventoryStatus::values()),
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name', 'item_type', 'category', 'brand', 'model',
            'serial_number', 'imei', 'mac_address', 'barcode', 'asset_tag',
            'warehouse', 'bin_location', 'assigned_location',
            'purchase_price', 'selling_price', 'purchase_date',
            'purchase_invoice_id', 'warranty_start', 'warranty_end', 'warranty_months',
            'assigned_at', 'returned_at', 'assignment_notes',
            'condition', 'condition_notes', 'specifications', 'notes',
            'customer_id', 'reseller_id', 'supplier_id', 'subscription_id',
        ]);

        $data['uuid'] = \Illuminate\Support\Str::uuid();
        $data['tenant_id'] = auth()->user()?->tenant_id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = $request->status;

        // Convert BDT amounts to poysha
        if (isset($data['purchase_price'])) {
            $data['purchase_price'] = (int) round($data['purchase_price'] * 100);
        }
        if (isset($data['selling_price'])) {
            $data['selling_price'] = (int) round($data['selling_price'] * 100);
        }

        $item = InventoryItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item created successfully',
            'data' => new InventoryItemResource($item),
        ], 201);
    }

    /**
     * Staff API: Update an inventory item.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeStaff($request);

        $item = InventoryItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory item not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'item_type' => 'sometimes|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:17',
            'status' => 'sometimes|string|in:' . implode(',', InventoryStatus::values()),
            'warehouse' => 'nullable|string|max:100',
            'bin_location' => 'nullable|string|max:100',
            'purchase_price' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name', 'item_type', 'category', 'brand', 'model',
            'serial_number', 'imei', 'mac_address', 'barcode', 'asset_tag',
            'warehouse', 'bin_location', 'assigned_location',
            'purchase_price', 'selling_price',
            'customer_id', 'reseller_id', 'supplier_id', 'subscription_id',
            'assigned_at', 'returned_at', 'assignment_notes',
            'condition', 'condition_notes', 'specifications', 'notes',
        ]);

        $data['updated_by'] = auth()->id();

        // Convert BDT amounts to poysha
        if (isset($data['purchase_price'])) {
            $data['purchase_price'] = (int) round($data['purchase_price'] * 100);
        }
        if (isset($data['selling_price'])) {
            $data['selling_price'] = (int) round($data['selling_price'] * 100);
        }

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'data' => new InventoryItemResource($item->fresh()),
        ]);
    }

    /**
     * Staff API: Delete an inventory item (soft delete).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeStaff($request);

        $item = InventoryItem::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory item not found',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory item deleted successfully',
        ]);
    }

    /**
     * Get stock transactions (staff only).
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $query = StockTransaction::query()
            ->with(['inventoryItem', 'user', 'customer', 'fieldJob', 'createdBy']);

        if ($request->has('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => StockTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get stock transaction details (staff only).
     */
    public function transactionShow(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeStaff($request);

        $transaction = StockTransaction::where('uuid', $uuid)
            ->with(['inventoryItem', 'user', 'customer', 'fieldJob', 'createdBy'])
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Stock transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new StockTransactionResource($transaction),
        ]);
    }
}
