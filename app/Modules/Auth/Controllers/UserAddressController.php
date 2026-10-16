<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\UserAddressRequest;
use App\Modules\Auth\Services\UserAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private readonly UserAddressService $addressService) {}

  // GET /api/v1/user/addresses
  public function index(Request $request): JsonResponse
  {
    $addresses = $this->addressService->getUserAddresses($request->user()->id);
    return $this->success($addresses);
  }

  // POST /api/v1/user/addresses
  public function store(UserAddressRequest $request): JsonResponse
  {
    $address = $this->addressService->createAddress($request->user()->id, $request->validated());
    return $this->created($address, 'Alamat berhasil ditambahkan.');
  }

  // PUT /api/v1/user/addresses/{address}
  public function update(UserAddressRequest $request, string $addressId): JsonResponse
  {
    $address = $this->addressService->updateAddress($request->user()->id, $addressId, $request->validated());
    return $this->success($address, 'Alamat berhasil diperbarui.');
  }

  // DELETE /api/v1/user/addresses/{address}
  public function destroy(Request $request, string $addressId): JsonResponse
  {
    $this->addressService->deleteAddress($request->user()->id, $addressId);
    return $this->noContent();
  }
}