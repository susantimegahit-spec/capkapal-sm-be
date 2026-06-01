<?php

namespace App\Modules\Reward\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reward\Models\PointItem;
use App\Modules\Reward\Models\Redemption;
use App\Modules\Reward\Models\UserVoucher;
use App\Modules\Reward\Services\PointRedemptionService;
use Illuminate\Http\Request;

class PointRedemptionController extends Controller
{
    protected $redemptionService;

    public function __construct(PointRedemptionService $redemptionService)
    {
        $this->redemptionService = $redemptionService;
    }

    public function redeem(Request $request)
    {
        // Validate base fields
        $request->validate([
            'point_item_id' => 'required|exists:point_items,id',
            'shipping_address' => 'nullable|string|max:1000',
        ]);

        // Check item type and enforce shipping_address for PHYSICAL
        $item = PointItem::findOrFail($request->point_item_id);
        if ($item->type === 'PHYSICAL' && empty($request->shipping_address)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat pengiriman wajib diisi untuk hadiah fisik.'
            ], 422);
        }

        try {
            $redemption = $this->redemptionService->redeem(
                $request->user()->id,
                $request->point_item_id,
                $request->shipping_address
            );

            // Refresh user to get updated points
            $user = $request->user()->fresh();

            return response()->json([
                'success' => true,
                'message' => $item->type === 'VOUCHER'
                    ? 'Voucher berhasil ditukar! Cek menu Voucher Saya.'
                    : 'Hadiah berhasil ditukar! Menunggu verifikasi admin.',
                'data' => $redemption->load('pointItem'),
                'updated_points' => (int) $user->points,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getItems(Request $request)
    {
        $items = PointItem::where('is_active', true)
            ->where('stock', '>', 0)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function getTransactions(Request $request)
    {
        $transactions = Redemption::with('pointItem')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function getVouchers(Request $request)
    {
        $vouchers = UserVoucher::with('redemption.pointItem')
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')
            ->where('valid_until', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vouchers
        ]);
    }

    public function getRedemptionDetail(Request $request, $id)
    {
        $redemption = Redemption::with('pointItem')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $redemption
        ]);
    }
}
