<?php

namespace App\Modules\Order\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['user', 'agen']);
    }

    public function rules(): array
    {
        return [
            // Shipping address
            'recipient_name'   => ['required', 'string', 'max:100'],
            'phone'            => ['required', 'string', 'max:20'],
            'address'          => ['required', 'string', 'max:500'],
            'postal_code'      => ['required', 'string', 'max:10'],
            'district'         => ['required', 'string', 'max:100'],
            'village'          => ['required', 'string', 'max:100'],

            'province' => [
                'required',
                'string',
                'max:100',
                Rule::in(['Jawa Timur'])
            ],
            'city' => [
                'required',
                'string',
                'max:100',
                Rule::in([
                    'Kota Surabaya',
                    'Surabaya',
                    'Kabupaten Gresik',
                    'Gresik',
                    'Kabupaten Sidoarjo',
                    'Sidoarjo'
                ])
            ],

            // Shipping method
            'shipping_courier' => ['required', 'string', 'in:jne,jnt,sicepat,pos,flat'],
            'shipping_service' => ['required', 'string', 'max:20'],

            // Payment
            'payment_method'   => ['required', 'string', 'in:virtual_account,ewallet,qris,credit_card,retail_outlet,flat'],
            'payment_provider' => ['nullable', 'string', 'max:30'],

            // Optional
            'cart_item_ids'    => ['nullable', 'array', 'min:1'],
            'cart_item_ids.*'  => ['required', 'string', 'uuid'],
            'coupon_code'      => ['nullable', 'string', 'max:50'],
            'points_used'      => ['nullable', 'integer', 'min:0'],
            'customer_notes'   => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Kustomisasi pesan error agar lebih informatif saat ditampilkan di Frontend.
     */
    public function messages(): array
    {
        return [
            'province.in' => 'Maaf, saat ini layanan pengiriman kami hanya tersedia untuk wilayah Jawa Timur.',
            'city.in'     => 'Maaf, pesanan Anda tidak dapat diproses karena layanan kami saat ini hanya menjangkau area Surabaya dan Gresik.',
        ];
    }

    /**
     * Data lokasi referensi untuk validasi district dan village.
     */
    private function getLocationData(): array
    {
        $surabayaDistricts = [
            'Tegalsari'   => ['Kedungdoro', 'Dr. Soetomo', 'Wonorejo'],
            'Wonokromo'   => ['Darmo', 'Sawunggaling', 'Ngagel'],
            'Rungkut'     => ['Rungkut Kidul', 'Kali Rungkut', 'Medokan Ayu'],
            'Sukolilo'    => ['Keputih', 'Gebang Putih', 'Menur Pumpungan'],
            'Gubeng'      => ['Airlangga', 'Mojo', 'Pucang Sewu'],
            'Tambaksari'  => ['Pacar Keling', 'Dukuh Setro', 'Tambaksari'],
            'Sawahan'     => ['Kupang Krajan', 'Banyu Urip', 'Pakis'],
            'Lakarsantri' => ['Lakarsantri', 'Jeruk', 'Lidah Kulon'],
            'Wiyung'      => ['Wiyung', 'Babatan', 'Balas Klumprik'],
            'Mulyorejo'   => ['Kalisari', 'Mulyorejo', 'Manyar Sabrangan'],
            'Gayungan'    => ['Gayungan', 'Ketintang', 'Menanggal'],
            'Asemrowo'    => ['Asemrowo', 'Tambak Sarioso', 'Genting Kalianak'],
        ];

        $gresikDistricts = [
            'Gresik'    => ['Sidokumpul', 'Sukorame', 'Karangpoh', 'Tlogopojok'],
            'Kebomas'   => ['Randuagung', 'Kembangan', 'Kedanyang', 'Singosari'],
            'Manyar'    => ['Yosowilangun', 'Manyar Sabrangan', 'Suci', 'Pongangan'],
            'Driyorejo' => ['Petiken', 'Driyorejo', 'Mulung', 'Bambe'],
            'Cerme'     => ['Cerme Kidul', 'Cerme Lor', 'Banjarsari'],
        ];

        $sidoarjoDistricts = [
            'Sidoarjo' => ['Magersari', 'Pucang', 'Lemahputro', 'Sidokare'],
            'Waru'     => ['Pepelegi', 'Bungurasih', 'Tropodo', 'Kedungrejo'],
            'Taman'    => ['Sepanjang', 'Ketegan', 'Bebekan', 'Wonocolo'],
            'Gedangan' => ['Gedangan', 'Keboansikep', 'Semambung', 'Sruni'],
            'Sedati'   => ['Sedati Gede', 'Pabean', 'Betro', 'Kalanganyar'],
        ];

        return [
            'Kota Surabaya'      => $surabayaDistricts,
            'Surabaya'           => $surabayaDistricts,
            'Kabupaten Gresik'   => $gresikDistricts,
            'Gresik'             => $gresikDistricts,
            'Kabupaten Sidoarjo' => $sidoarjoDistricts,
            'Sidoarjo'           => $sidoarjoDistricts,
        ];
    }

    /**
     * Kustomisasi validasi (after hooks) untuk district dan village.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $city     = $this->input('city');
            $district = $this->input('district');
            $village  = $this->input('village');

            $locations = $this->getLocationData();

            if ($city && isset($locations[$city])) {
                $cityData = $locations[$city];

                if ($district) {
                    if (!array_key_exists($district, $cityData)) {
                        $validator->errors()->add('district', "Kecamatan {$district} tidak valid untuk kota yang dipilih.");
                    } else {
                        // Validasi village jika district valid
                        if ($village) {
                            $validVillages = $cityData[$district];
                            if (!in_array($village, $validVillages)) {
                                $validator->errors()->add('village', "Kelurahan {$village} tidak valid untuk kecamatan {$district}.");
                            }
                        }
                    }
                }
            }
        });
    }
}
