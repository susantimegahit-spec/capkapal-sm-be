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
            'payment_method'   => ['required', 'string', 'in:virtual_account,ewallet,qris,credit_card,retail_outlet,flat,manual_qris'],
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
        // ── SURABAYA (31 kecamatan) ──
        $surabayaDistricts = [
            'Karang Pilang' => ['Karang Pilang', 'Kebraon', 'Kedurus', 'Waru Gunung'],
            'Wonocolo' => ['Sidosermo', 'Bendul Merisi', 'Margorejo', 'Jemur Wonosari', 'Siwalankerto'],
            'Rungkut' => ['Kalirungkut', 'Rungkut Kidul', 'Kedung Baruk', 'Penjaringansari', 'Wonorejo', 'Medokan Ayu'],
            'Wonokromo' => ['Wonokromo', 'Jagir', 'Ngagel', 'Ngagel Rejo', 'Darmo', 'Sawunggaling'],
            'Tegalsari' => ['Tegalsari', 'Dr. Soetomo', 'Kedungdoro', 'Keputran', 'Wonorejo'],
            'Sawahan' => ['Petemon', 'Sawahan', 'Banyu Urip', 'Putat Jaya', 'Kupang Krajan', 'Pakis'],
            'Genteng' => ['Embong Kaliasin', 'Genteng', 'Kapasari', 'Ketabang', 'Peneleh'],
            'Gubeng' => ['Gubeng', 'Mojo', 'Airlangga', 'Kertajaya', 'Baratajaya', 'Pucang Sewu'],
            'Sukolilo' => ['Keputih', 'Gebang Putih', 'Klampis Ngasem', 'Menur Pumpungan', 'Nginden Jangkungan', 'Semolowaru', 'Medokan Semampir'],
            'Tambaksari' => ['Tambaksari', 'Ploso', 'Gading', 'Pacarkembang', 'Rangkah', 'Pacarkeling', 'Kapasmadya Baru', 'Dukuh Setro'],
            'Simokerto' => ['Simokerto', 'Kapasan', 'Sidodadi', 'Simolawang', 'Tambakrejo'],
            'Pabean Cantian' => ['Bongkaran', 'Nyamplungan', 'Krembangan Utara', 'Tanjung Perak'],
            'Bubutan' => ['Alun-alun Contong', 'Bubutan', 'Gundih', 'Jepara', 'Tembok Dukuh'],
            'Tandes' => ['Tandes', 'Karang Poh', 'Balongsari', 'Manukan Kulon', 'Manukan Wetan', 'Banjar Sugihan'],
            'Krembangan' => ['Krembangan Selatan', 'Kemayoran', 'Perak Barat', 'Dupak', 'Morokrembangan'],
            'Semampir' => ['Ampel', 'Pegirian', 'Wonokusumo', 'Ujung', 'Sidotopo'],
            'Kenjeran' => ['Tanah Kali Kedinding', 'Sidotopo Wetan', 'Bulak Banteng', 'Tambak Wedi'],
            'Lakarsantri' => ['Bangkingan', 'Jeruk', 'Lakarsantri', 'Lidah kulon', 'Lidah Wetan', 'Sumurwelut'],
            'Benowo' => ['Kandangan', 'Sememi', 'Tambak Oso Wilangun', 'Romokalisari'],
            'Wiyung' => ['Wiyung', 'Jajar Tunggal', 'Babatan', 'Balas Klumprik'],
            'Dukuh Pakis' => ['Dukuh Pakis', 'Dukuh Kupang', 'Gunung Sari', 'Pradah Kalikendal'],
            'Gayungan' => ['Gayungan', 'Menanggal', 'Dukuh Menanggal', 'Ketintang'],
            'Jambangan' => ['Jambangan', 'Karah', 'Kebonsari', 'Pagesangan'],
            'Tenggilis Mejoyo' => ['Kutisari', 'Kendangsari', 'Tenggilis Mejoyo', 'Panjang Jiwo'],
            'Gunung Anyar' => ['Gunung Anyar', 'Rungkut Tengah', 'Rungkut Menanggal', 'Gunung Anyar Tambak'],
            'Mulyorejo' => ['Mulyorejo', 'Manyar Sabrangan', 'Kejawan Putih Tambak', 'Kalisari', 'Dukuh Sutorejo', 'Kalijudan'],
            'Sukomanunggal' => ['Sukomanunggal', 'Tanjungsari', 'Sonokwijenan', 'Putat Gede', 'Simomulyo', 'Simomulyo Baru'],
            'Asem Rowo' => ['Asem Rowo', 'Genting Kalianak', 'Tambak Sarioso'],
            'Bulak' => ['Kedung Cowek', 'Kenjeran', 'Bulak', 'Sukolilo Baru'],
            'Pakal' => ['Pakal', 'Babat Jerawat', 'Sumber Rejo', 'Benowo'],
            'Sambikerep' => ['Sambikerep', 'Made', 'Beringin', 'Lontar'],
        ];

        // ── GRESIK (18 kecamatan) ──
        $gresikDistricts = [
            'Dukun' => ['Sawo', 'Karangcangkring', 'Bulangan', 'Wonokerto', 'Bangeran', 'Lowayu', 'Petiyin Tunggal', 'Tirem Enggal', 'Tebuwung', 'Dukuh Kembar', 'Madumulyorejo', 'Mentaras', 'Baron', 'Jrebeng', 'Mojopetung', 'Sekargadung', 'Babakbawo', 'Babaksari', 'Sambogunung', 'Sembungan Kidul', 'Sembunganyar', 'Kalirejo', 'Padang Bandung', 'Dukunanyar'],
            'Balongpanggang' => ['Jombangdelik', 'Brangkal', 'Ngampel', 'Bandungsekaran', 'Babatan', 'Tanahlandean', 'Kedungsumber', 'Wonorejo', 'Dapet', 'Dohoagung', 'Mojogede', 'Tenggor', 'Wahas', 'Kedungpring', 'Sekarputih', 'Pinggir', 'Karangsemanding', 'Pacuh', 'Balongpanggang', 'Wotansari', 'Pucung', 'Klotok', 'Ngasin', 'Banjaragung', 'Ganggang'],
            'Panceng' => ['Pantenan', 'Sumurber', 'Siwalan', 'Ketanen', 'Banyutengah', 'Serah', 'Surowiti', 'Prupuh', 'Campurejo', 'Sukodono', 'Dalegan', 'Petung', 'Wotan', 'Doudo'],
            'Benjeng' => ['Karangankidul', 'Sedapur Klagen', 'Deliksumber', 'Lundo', 'Kalipadang', 'Kedungrukem', 'Balungtunjung', 'Munggugebang', 'Balongmojo', 'Bulangkulon', 'Banter', 'Sirnoboyo', 'Munggugianti', 'Klampok', 'Metatu', 'Bengkelolor', 'Gurangploso', 'Kedungsekar', 'Dermo', 'Jogodalu', 'Jatirembe', 'Bulurejo', 'Punduttrate'],
            'Duduksampeyan' => ['Panjunan', 'Kandangan', 'Pandanan', 'Tumapel', 'Tambakrejo', 'Glanggang', 'Palebon', 'Setrohadi', 'Bendungan', 'Sumengko', 'Wadak Lor', 'Wadak Kidul', 'Petisbenem', 'Duduksampeyan', 'Gredek', 'Kramat', 'Sumari', 'Samirplapan', 'Kawistowindu', 'Kemudi', 'Tebaloan', 'Tirem', 'Ambeng-ambeng Watangrejo'],
            'Wringinanom' => ['Kepuhklagen', 'Sumbergede', 'Mondoluku', 'Kedunganyar', 'Sumberwaru', 'Kesambenkulon', 'Sumberame', 'Sembung', 'Sooko', 'Wringinanom', 'Pedagangan', 'Lebanisuko', 'Lebaniwaras', 'Watestanjung', 'Sumengko', 'Pasinanlemahputih'],
            'Ujungpangkah' => ['Pangkahkulon', 'Cangaan', 'Ngemboh', 'Sekapuk', 'Gosari', 'Banyuurip', 'Kebonagung', 'Pangkahwetan', 'Bolo', 'Glatik', 'Karangrejo', 'Ketapanglor', 'Tanjangawan'],
            'Kedamean' => ['Mojowuku', 'Glindah', 'Sidoraharjo', 'Tulung', 'Cermen', 'Lampah', 'Slempit', 'Turirejo', 'Belahanrejo', 'Menunggal', 'Tanjung', 'Katimoho', 'Banyuurip', 'Kedamean', 'Ngepung'],
            'Sidayu' => ['Sukorejo', 'Gedangan', 'Wadeng', 'Lasem', 'Kertosono', 'Sambipondok', 'Raci Kulon', 'Golokan', 'Raci Tengah', 'Purwodadi', 'Sidomulyo', 'Srowo', 'Kauman', 'Bunderan', 'Asempapak', 'Mriyunan', 'Mojoasem', 'Sedagaran', 'Ngawen', 'Pengulu', 'Randuboto'],
            'Manyar' => ['Tanggulrejo', 'Morobakung', 'Pejangganan', 'Gumeno', 'Sumberejo', 'Ngampel', 'Betoyokauman', 'Sembayat', 'Betoyoguci', 'Leran', 'Tebalo', 'Karangrejo', 'Banyuwangi', 'Manyarejo', 'Manyar Sidomukti', 'Manyar Sidorukun', 'Banjarsari', 'Suci', 'Paganden', 'Pongangan', 'Sukomulyo', 'Yosowilangun', 'Roomo'],
            'Cerme' => ['Dadapkuning', 'Lengkong', 'Kandangan', 'Dooro', 'Dampaan', 'Ngembung', 'Sukoanyar', 'Gedangkulud', 'Wedani', 'Dungus', 'Guranganyar', 'Kambingan', 'Morowudi', 'Betiting', 'Ngabetan', 'Padeg', 'Iker-iker Geger', 'Semampir', 'Cagakagung', 'Cerme Kidul', 'Cerme Lor', 'Pandu', 'Banjarsari', 'Tambakberas', 'Jono'],
            'Bungah' => ['Sidomukti', 'Mojopurogede', 'Mojopurowetan', 'Melirang', 'Sidorejo', 'Raciwetan', 'Masangan', 'Sidokumpul', 'Pegundan', 'Sukowati', 'Abar-abir', 'Bungah', 'Kemangi', 'Kisik', 'Gumeng', 'Sukorejo', 'Indrodelik', 'Bedanten', 'Sungonlegowo', 'Tanjung Widoro', 'Watuagung', 'Kramat'],
            'Menganti' => ['Beton', 'Pranti', 'Gadingwatu', 'Bringkang', 'Boteng', 'Putatlor', 'Domas', 'Mojotengah', 'Boboh', 'Sidojangkung', 'Hendrosari', 'Pelemwatu', 'Menganti', 'Hulaan', 'Kepatihan', 'Gempolkurung', 'Drancang', 'Sidowungu', 'Randupadangan', 'Setro', 'Pengalangan', 'Laban'],
            'Kebomas' => ['Gulomantung', 'Sidomukti', 'Kebomas', 'Kawis Anyar', 'Ngargosari', 'Gending', 'Sidomoro', 'Tenggulunan', 'Singosari', 'Indro', 'Dahanrejo', 'Kembangan', 'Kedayang', 'Randuagung', 'Klangonan', 'Sukorejo', 'Giri', 'Segoromadu', 'Karangkiring', 'Prambangan', 'Sekarkurung'],
            'Driyorejo' => ['Karangandong', 'Banjaran', 'Krikilan', 'Mojosari Rejo', 'Tanjungan', 'Anom', 'Sumput', 'Kesamben Wetan', 'Randegansari', 'Tenaru', 'Driyorejo', 'Petiken', 'Cangkir', 'Gadung', 'Mulung', 'Bambe'],
            'Gresik' => ['Ngipik', 'Tlogo Pojok', 'Karang Turi', 'Tlogo Patut', 'Sukorame', 'Lumpur', 'Karang Poh', 'Trate', 'Sido Kumpul', 'Bedilan', 'Sukodono', 'Kemuteran', 'Pekelingan', 'Kroman', 'Kebungson', 'Pekauman', 'Tlogobendung', 'Gapuro Sukolilo', 'Pulopancikan', 'Kramatinggil', 'Sidorukun'],
            'Sangkapura' => ['Dekatagung', 'Kumalasa', 'Suwari', 'Pudakitbarat', 'Pudakittimur', 'Lebak', 'Bululanjang', 'Patarselamat', 'Kotakusuma', 'Sungaiteluk', 'Gunungteguh', 'Sawahmulya', 'Sungairujing', 'Balikterus', 'Kebontelukdalam', 'Sidogedungbatu', 'Daun'],
            'Tambak' => ['Gelam', 'Sukaoneng', 'Sukalela', 'Pekalongan', 'Tambak', 'Telukjatidawang', 'Klumpanggubug', 'Grejeg', 'Tanjungori', 'Peromaan', 'Diponggo', 'Kepuhteluk', 'Kepuhlegundi'],
        ];

        // ── SIDOARJO (18 kecamatan) ──
        $sidoarjoDistricts = [
            'Tarik' => ['Mliriprowo', 'Kedungbocok', 'Singogalih', 'Tarik', 'Mergobener', 'Mergosari', 'Kendalsewu', 'Klantingsari', 'Kalimati', 'Gempolklutuk', 'Banjarwungu', 'Balongmacekan', 'Gampingrowo', 'Sebani', 'Kramattemenggung', 'Mindugading', 'Kemuning', 'Janti', 'Segodobancang', 'Kedinding'],
            'Prambon' => ['Prambon', 'Kajartengguli', 'Gedangrowo', 'Wirobiting', 'Simpang', 'Bulang', 'Gampang', 'Jatikalang', 'Jatialun-alun', 'Pejangkungan', 'Kedungsugo', 'Kedungwonokerto', 'Bendotretek', 'Wonoplintahan', 'Kedungkembar', 'Jedongcangkring', 'Cangkringturi', 'Simogirang', 'Temu', 'Watutulis'],
            'Krembung' => ['Tambakrejo', 'Keper', 'Kedungsumur', 'Kedungrawan', 'Tanjekwagir', 'Mojoruntut', 'Gading', 'Wangkal', 'Jenggot', 'Waung', 'Ploso', 'Rejeni', 'Kandangan', 'Krembung', 'Lemujut', 'Cangkring', 'Keret', 'Wonomlati', 'Balonggarut'],
            'Porong' => ['Porong', 'Juwetkenongo', 'Gedang', 'Kebonagung', 'Kedungsolo', 'Kesambi', 'Kebakalan', 'Lajuk', 'Kedungboto', 'Candipari', 'Pamotan', 'Glagaharum', 'Plumbon', 'Wunut', 'Pesawahan'],
            'Jabon' => ['Panggreh', 'Trompoasri', 'Kedungrejo', 'Semambung', 'Kedungpandan', 'Kupang', 'Tambakkalisogo', 'Balongtani', 'Jemirahan', 'Dukuhsari', 'Kedungcangkring', 'Keboguyang', 'Permisan'],
            'Tanggulangin' => ['Kalisampurno', 'Ketapang', 'Kalitengah', 'Gempolsari', 'Sentul', 'Penatarsewu', 'Banjarasri', 'Banjarpanji', 'Kedungbanteng', 'Kalidawir', 'Putat', 'Ngaban', 'Kludan', 'Boro', 'Kedensari', 'Ketegan', 'Ganggangpanjang', 'Randegan'],
            'Candi' => ['Karangtanjung', 'Sumorame', 'Ngampelsari', 'Balonggabus', 'Balongdowo', 'Kendalpecabean', 'Kedungpeluk', 'Kalipecabean', 'Klurak', 'Kebonsari', 'Gelam', 'Candi', 'Sugihwaras', 'Kedungkendo', 'Durungbanjar', 'Durungbedug', 'Jambangan', 'Sidodadi', 'Sepande', 'Sumokali', 'Tenggulunan', 'Bligo', 'Wedoroklurak', 'Larangan'],
            'Sidoarjo' => ['Sidokare', 'Celep', 'Sekardangan', 'Gebang', 'Bulusidokare', 'Pucanganom', 'Pekauman', 'Lemahputro', 'Sidokumpul', 'Sidoklumpuk', 'Pucang', 'Magersari', 'Cemengkalang', 'Urangagung', 'Lebo', 'Suko', 'Banjarbendo', 'Rangkahkidul', 'Blurukidul', 'Kemiri', 'Jati', 'Cemengbakalan', 'Sarirogo', 'Sumput'],
            'Tulangan' => ['Janti', 'Kebaron', 'Kenongo', 'Gelang', 'Jiken', 'Pangkemiri', 'Kepatihan', 'Tulangan', 'Kepadangan', 'Tlasih', 'Kajeksan', 'Singopadu', 'Kemantren', 'Kepunten', 'Kepuhkemiri', 'Grinting', 'Modong', 'Grogol', 'Medalem', 'Sudimoro', 'Kedondong', 'Grabagan'],
            'Wonoayu' => ['Tanggul', 'Simoketawang', 'Popoh', 'Jimbarankulon', 'Jimbaranwetan', 'Ketimang', 'Pilang', 'Sumberejo', 'Mojorangagung', 'Wonokasian', 'Ploso', 'Mulyodadi', 'Wonoayu', 'Semambung', 'Simoangin-angin', 'Wonokalang', 'Pagerngumbuk', 'Plaosan', 'Lambangan', 'Sawocangkring', 'Becirongengor', 'Karangpuri', 'Candinegoro'],
            'Krian' => ['Kemasan', 'Tambakkemerakan', 'Krian', 'Tropodo', 'Sedenganmijen', 'Katerungan', 'Jerukgamping', 'Gamping', 'Terik', 'Junwangi', 'Terungkulon', 'Terungwetan', 'Jatikalang', 'Keboharan', 'Ponokawan', 'Sidomojo', 'Kraton', 'Sidomulyo', 'Tempel', 'Watugolong', 'Barengkrajan', 'Sidorejo'],
            'Balongbendo' => ['Wonokupang', 'Sumokembangsri', 'Singkalan', 'Bakungpringgodani', 'Wonokarang', 'Seduri', 'Bakalanwringinpitu', 'Gagangkepuhsari', 'Suwaluh', 'Watesari', 'Seketi', 'Kemangsen', 'Jabaran', 'Balongbendo', 'Jeruklegi', 'Penambangan', 'Waruberon', 'Bogempinggir', 'Kedungsukodani', 'Bakungtemenggungan'],
            'Taman' => ['Taman', 'Geluran', 'Kalijaten', 'Ketegan', 'Sepanjang', 'Bebekan', 'Wonocolo', 'Ngelom', 'Bohar', 'Wage', 'Kedungturi', 'Jemundo', 'Sadang', 'Sambibulu', 'Bringinbendo', 'Sidodadi', 'Kramatjegu', 'Trosobo', 'Pertapanmaduretno', 'Tawangsari', 'Gilang', 'Kletek', 'Tanjungsari', 'Krembangan'],
            'Sukodono' => ['Wilayut', 'Kebonagung', 'Anggaswangi', 'Jumputrejo', 'Suruh', 'Pekarungan', 'Pademonegoro', 'Cangkringsari', 'Jogosatru', 'Ngaresrejo', 'Sambungrejo', 'Plumbungan', 'Sukodono', 'Kloposepuluh', 'Masanganwetan', 'Suko', 'Masangankulon', 'Panjunan', 'Bangsri'],
            'Buduran' => ['Entalsewu', 'Pagerwojo', 'Sidokerto', 'Buduran', 'Siwalanpanji', 'Sidomulyo', 'Prasung', 'Sawohan', 'Damarsi', 'Dukuhtengah', 'Banjarsari', 'Wadungasih', 'Banjarkemantren', 'Sukorejo', 'Sidokepung'],
            'Gedangan' => ['Ganting', 'Karangbong', 'Tebel', 'Kragan', 'Gemurung', 'Punggul', 'Wedi', 'Ketajen', 'Gedangan', 'Sruni', 'Keboansikep', 'Keboananom', 'Bangah', 'Sawotratap', 'Semambung'],
            'Sedati' => ['Kwangsan', 'Pepe', 'Buncitan', 'Kalanganyar', 'Tambakcemandi', 'Gisikcemandi', 'Cemandi', 'Pulungan', 'Betro', 'Sedatiagung', 'Sedatigede', 'Pabean', 'Semampir', 'Pranti', 'Segorotambak', 'Banjarkemuning'],
            'Waru' => ['Pepelegi', 'Waru', 'Kureksari', 'Ngingas', 'Tropodo', 'Tambaksawah', 'Tambakrejo', 'Tambakoso', 'Tambaksumur', 'Wadungasri', 'Kepuhkiriman', 'Berbek', 'Wedoro', 'Janti', 'Kedungrejo', 'Medaeng', 'Bungurasih'],
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
