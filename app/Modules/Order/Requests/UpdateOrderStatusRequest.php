<?php
// UpdateOrderStatusRequest.php
namespace App\Modules\Order\Requests;

use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin']);
    }

    public function rules(): array
    {
        return [
            'status'          => ['required', new Enum(OrderStatus::class)],
            'notes'           => ['nullable', 'string', 'max:500'],
            'tracking_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
