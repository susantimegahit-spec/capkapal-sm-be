<?php

namespace App\Modules\Reward\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Reward\Models\PointItem;
use App\Modules\Reward\Models\PointTransaction;
use App\Modules\Reward\Models\Redemption;
use App\Modules\Reward\Services\PointRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRedemptionController extends Controller
{
    public function updateStatus(Request $request, $id, PointRedemptionService $service)
    {
        $request->validate([
            'status' => 'required|in:APPROVED,REJECTED,SHIPPED,DELIVERED',
            'tracking_number' => 'required_if:status,SHIPPED'
        ]);

        try {
            if ($request->status === 'REJECTED') {
                $service->rejectRedemption($id);
                return response()->json(['success' => true, 'message' => 'Penukaran ditolak, poin dikembalikan.']);
            }

            $redemption = Redemption::findOrFail($id);
            $redemption->status = $request->status;
            
            if ($request->has('tracking_number')) {
                $redemption->tracking_number = $request->tracking_number;
            }
            
            $redemption->save();

            return response()->json(['success' => true, 'message' => 'Status berhasil diupdate.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ── POINT ITEMS CRUD ────────────────────────────────────────────

    public function getItems(Request $request)
    {
        $items = PointItem::orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function createItem(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:PHYSICAL,VOUCHER',
            'stock' => 'required|integer|min:0',
            'point_cost' => 'required|integer|min:0',
            'discount_type' => 'nullable|in:PERCENTAGE,FIXED',
            'discount_value' => 'nullable|numeric|min:0',
            'expiry_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'eligible_for' => 'sometimes|in:all,user,agen',
            'max_redeem' => 'nullable|integer|min:1'
        ]);

        $item = PointItem::create($request->all());
        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item created successfully']);
    }

    public function updateItem(Request $request, $id)
    {
        $item = PointItem::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:PHYSICAL,VOUCHER',
            'stock' => 'sometimes|required|integer|min:0',
            'point_cost' => 'sometimes|required|integer|min:0',
            'discount_type' => 'nullable|in:PERCENTAGE,FIXED',
            'discount_value' => 'nullable|numeric|min:0',
            'expiry_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'eligible_for' => 'sometimes|in:all,user,agen',
            'max_redeem' => 'nullable|integer|min:1'
        ]);

        $item->update($request->all());
        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item updated successfully']);
    }

    public function deleteItem($id)
    {
        $item = PointItem::findOrFail($id);
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Item deleted successfully']);
    }

    // ── TRANSACTIONS / REDEMPTIONS ──────────────────────────────────

    public function getTransactions(Request $request)
    {
        $query = Redemption::with(['pointItem', 'user']);
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // ── SUMMARY / REPORTS ───────────────────────────────────────────

    public function getSummary()
    {
        $validStatuses = ['PENDING', 'APPROVED', 'SHIPPED', 'DELIVERED', 'COMPLETED'];

        $totalPointsRedeemed = Redemption::whereIn('status', $validStatuses)
            ->sum('points_used');

        $totalPhysical = Redemption::whereIn('status', $validStatuses)
            ->where('type', 'PHYSICAL')
            ->count();

        $totalVoucher = Redemption::whereIn('status', $validStatuses)
            ->where('type', 'VOUCHER')
            ->count();

        $uniqueUsers = Redemption::whereIn('status', $validStatuses)
            ->distinct('user_id')
            ->count('user_id');

        $pendingCount = Redemption::where('status', 'PENDING')->count();

        $statusBreakdown = Redemption::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'total_points_redeemed' => (int) $totalPointsRedeemed,
                'total_physical_claimed' => $totalPhysical,
                'total_voucher_claimed' => $totalVoucher,
                'unique_users' => $uniqueUsers,
                'pending_count' => $pendingCount,
                'status_breakdown' => $statusBreakdown,
            ]
        ]);
    }

    // ── USER HISTORY ────────────────────────────────────────────────

    public function getUserHistory($id)
    {
        // Accept UUID string (User model uses UUID)
        $user = User::findOrFail($id);

        $pointHistory = PointTransaction::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $redemptions = Redemption::with('pointItem')
            ->where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true, 
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'points' => (int) $user->points,
                ],
                'history' => $pointHistory,
                'redemptions' => $redemptions,
            ]
        ]);
    }
}
