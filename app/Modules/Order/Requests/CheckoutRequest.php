<?php
// CheckoutRequest.php
namespace App\Modules\Order\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            // Shipping address
            'recipient_name'   => ['required', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:20'],
            'address'          => ['required', 'string', 'max:500'],
            'district'         => ['nullable', 'string', 'max:100'],
            'city'             => ['required', 'string', 'max:100'],
            'province'         => ['required', 'string', 'max:100'],
            'postal_code'      => ['required', 'string', 'max:10'],

            // Shipping method
            'shipping_courier' => ['required', 'string', 'in:jne,jnt,sicepat,pos,flat'],
            'shipping_service' => ['required', 'string', 'max:20'],

            // Payment
            'payment_method'   => ['required', 'string', 'in:virtual_account,ewallet,qris,credit_card,retail_outlet,flat'],
            'payment_provider' => ['nullable', 'string', 'max:30'],

            // Optional
            'coupon_code'      => ['nullable', 'string', 'max:50'],
            'points_used'      => ['nullable', 'integer', 'min:0'],
            'customer_notes'   => ['nullable', 'string', 'max:500'],
        ];
    }
}
