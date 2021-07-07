<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Format;
use App\Helpers\Gmaps;
use App\Http\Controllers\Controller;
use App\Member\Address;
use App\Member\AddressType;
use App\Member\Distance;
use App\Models\Wilayah\CoverageDetail;
use App\Wilayah\Wilayah;
use App\Wilayah\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberAddressController extends Controller
{
    public static function setPrimaryAddress($memberId, $addressId)
    {
        Address::where('member_id', $memberId)
            ->update(['is_primary' => 0]);
        Address::where('id', $addressId)
            ->update(['is_primary' => 1]);
    }

    public static function storeDistance($address, $wilayahId = 12)
    {
        $pool = Pool::select('id', 'wilayah_id', 'lat', 'lng')
            ->where('wilayah_id', $wilayahId)
            ->whereDistance($address->lat, $address->lng)
            ->first();

        $gmapsDistance = Gmaps::matrixDistance($pool->lat, $pool->lng, $address->lat, $address->lng);

        if ($gmapsDistance) {

            $distance = Distance::where('member_id', $address->member_id)
                ->where('address_id', $address->id)
                ->where('dc_id', $wilayahId)
                ->first();

            if ($distance) {
                $distance->update([
                    'pool_id' => $pool->id,
                    'jarak' => $gmapsDistance->value / 1000,
                    'updated_by' => $address->member_id,
                ]);

            } else {
                Distance::create([
                    'address_id' => $address->id,
                    'member_id' => $address->member_id,
                    'dc_id' => $pool->wilayah_id,
                    'pool_id' => $pool->id,
                    'jarak' => $gmapsDistance->value / 1000,
                    'created_by' => $address->member_id,
                    'updated_by' => $address->member_id,
                ]);
            }

            return true;
        } else {
            return false;
        }
    }

    public function index()
    {
        $addresses = Address::select(
                'tm_member_addresses.id',
                'tm_member_addresses.title',
                'tm_member_addresses.phone',
                'tm_member_addresses.email',
                'tm_member_addresses.address',
                'tm_member_addresses.alamat_region',
                'tm_member_addresses.g_route',
                'tm_member_addresses.adm_area_level_1',
                'tm_member_addresses.adm_area_level_2',
                'tm_member_addresses.adm_area_level_3',
                'tm_member_addresses.adm_area_level_4',
                'tm_member_addresses.country',
                'tm_member_addresses.postal_code',
                'tm_member_addresses.notes',
                'tm_member_addresses.lat',
                'tm_member_addresses.lng',
                'tm_member_addresses.is_primary',
                'tm_tipe_alamat.id AS tipe_id',
                'tm_tipe_alamat.title AS tipe'
            )
            ->leftJoin('tm_tipe_alamat', 'tm_tipe_alamat.id', '=', 'tm_member_addresses.tipe')
            ->where('tm_member_addresses.member_id', Auth::id())
            ->where('tm_member_addresses.status', 1)
            ->where('tm_member_addresses.publish', 1)
            ->orderBy('tm_member_addresses.is_primary', 'desc')
            ->orderBy('tm_member_addresses.title')
            ->get();

        foreach ($addresses as $address) {
            $address->phone = Format::castPhoneNumber($address->phone);
            $address->nama = ucwords(strtolower($address->title));
            $address->alamat = $address->fullAddress();
            $address->kodepos = $address->postal_code;

            unset($address->address);
            unset($address->title);
            unset($address->postal_code);
        }

        return responseArray([
            'count' => count($addresses),
            'data' => $addresses
        ]);
    }


    public function storeAddress(Request $request)
    {
        $this->validate($request, [
            'url_title' => 'nullable|string',
            'title' => 'required|string',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'alamat_region' => 'nullable|string',
            'g_route' => 'nullable|string',
            'adm_area_level_1' => 'nullable|string',
            'adm_area_level_2' => 'nullable|string',
            'adm_area_level_3' => 'required|string',
            'adm_area_level_4' => 'required|string',
            'country' => 'nullable|string',
            'lat' => 'nullable|string',
            'lng' => 'nullable|string',
            'phone' => 'required|string',
            'postal_code' => 'required|string',
            'notes' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
            'tipe' => 'nullable|exists:App\Member\AddressType,id',
        ], [], [
            'title' => 'Nama Penerima',
            'address' => 'Alamat',
            'adm_area_level_1' => 'Provinsi',
            'adm_area_level_2' => 'Kota/Kabupaten',
            'adm_area_level_3' => 'Kecamatan',
            'adm_area_level_4' => 'Kelurahan',
            'postal_code' => 'Kode Pos'
        ]);

        $user = Auth::user();

        $validated = $request->only(
            'url_title', 'title', 'address', 'alamat_region', 'g_route',
            'adm_area_level_1', 'adm_area_level_2', 'adm_area_level_3', 'adm_area_level_4',
            'country', 'lat', 'lng', 'phone', 'postal_code',
            'notes', 'tipe', 'email',
            'is_primary'
        );

        if (!$validated['adm_area_level_1'] || !$validated['adm_area_level_2']) {
            $coverageDetail = CoverageDetail::where('kecamatan', $validated['adm_area_level_3'])
                ->where('kelurahan', $validated['adm_area_level_4'])
                ->first();

            if ($coverageDetail) {
                $validated['adm_area_level_1'] = $coverageDetail->provinsi;
                $validated['adm_area_level_2'] = $coverageDetail->kota_kabupaten;
                // abort(200, 'Alamat di luar jangkauan pengiriman');
            }
        }
        
        if (!$validated['url_title']) {
            $validated['url_title'] = strtolower(str_replace(' ', '-', $validated['title']));
        }

        $validated['phone'] = Format::phoneNumber($request->phone);
        $validated['member_id'] = Auth::id();
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $validated['status'] = $validated['publish'] = 1;
        $validated['is_primary'] = 0;

        // Clean the text...
        $validated['url_title'] = Format::cleanSpecialChar($validated['url_title']);
        $validated['title'] = Format::cleanSpecialChar($validated['title']);
        $validated['address'] = Format::cleanSpecialChar($validated['address']);
        $validated['alamat_region'] = Format::cleanSpecialChar($validated['alamat_region']);
        $validated['adm_area_level_2'] = Format::cleanCity($validated['adm_area_level_2']);
        $validated['adm_area_level_3'] = Format::cleanDistric($validated['adm_area_level_3']);

        if ($validated['g_route'] == '' || $validated['g_route'] == null) {
            $validated['g_route'] = implode(', ', [
                $validated['address'],
                $validated['adm_area_level_4'],
                $validated['adm_area_level_3'],
                $validated['adm_area_level_2'],
                $validated['adm_area_level_1'],
                $validated['postal_code'],
                $validated['country'],
            ]);
        }

        if ($validated['alamat_region'] == '' || $validated['alamat_region'] == null) {
            $validated['alamat_region'] = $validated['g_route'];
        }

        $validated['country'] = $validated['country']
            ? $validated['country']
            : 'Indonesia';

        $address = Address::where([
            'member_id' => $validated['member_id'],
            'title' => $validated['title'],
            'url_title' => $validated['url_title'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
        ])->first();

        $error = null;
        $message = '';
        if ($address) {
            $oldLat = $address->lat;
            $oldLng = $address->lng;
            $address->update($validated);
            $error = false;
            $message = 'Alamat sudah ada sebelumnya dan berhasil diubah.';
        } else {
            $address = Address::create($validated);
            $message = $address ? 'Alamat tersimpan.' : 'Terjadi kesalahan dalam menyimpan alamat Anda.';
            $error = $address ? false : true;
            $oldLat = $oldLng = null;
        }

        // Check if this member has any other addresses, if not, let set this one as their primary address.
        $checkAddress = Address::where('member_id', Auth::id())
            ->where('is_primary', 1)
            ->active()
            ->count();

        if ($checkAddress == 0 || $request->input('is_primary') == 1) {
            self::setPrimaryAddress($user->id, $address->id);
            $message .= ' Alamat diatur sebagai alamat utama.';
        }

        /* if (($oldLat != $request->lat || $oldLng != $request->lng) && $error == false) {
            DistanceController::storeDistance($address);
        } */

        return responseArray([
            'message' => $message,
        ], 200, $error);
    }

    public function updateAddress(Request $request)
    {
        $this->validate($request, [
            'address_id' => 'required|exists:App\Member\Address,id',
            'url_title' => 'nullable|string',
            'title' => 'required|string',
            'email' => 'nullable|email',
            'address' => 'required|string',
            'alamat_region' => 'nullable|string',
            'g_route' => 'nullable|string',
            'adm_area_level_1' => 'nullable|string',
            'adm_area_level_2' => 'nullable|string',
            'adm_area_level_3' => 'required|string',
            'adm_area_level_4' => 'required|string',
            'country' => 'nullable|string',
            'lat' => 'nullable|string',
            'lng' => 'nullable|string',
            'phone' => 'required|string',
            'postal_code' => 'required|string',
            'notes' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
            'tipe' => 'nullable|exists:App\Member\AddressType,id',
        ], [], [
            'title' => 'Nama Penerima',
            'address' => 'Alamat',
            'adm_area_level_1' => 'Provinsi',
            'adm_area_level_2' => 'Kota/Kabupaten',
            'adm_area_level_3' => 'Kecamatan',
            'adm_area_level_4' => 'Kelurahan',
            'postal_code' => 'Kode Pos'
        ]);

        $address = Address::where('member_id', Auth::id())
            ->find($request->address_id);

        if (!$address)
            abort(200, 'Alamat Anda tidak ditemukan.');

        // $oldLat = $address->lat;
        // $oldLng = $address->lng;

        $user = Auth::user();

        $validated = $request->only(
            'url_title', 'title', 'address', 'alamat_region', 'g_route',
            'adm_area_level_1', 'adm_area_level_2', 'adm_area_level_3', 'adm_area_level_4',
            'country', 'lat', 'lng', 'phone', 'postal_code',
            'notes', 'tipe', 'email',
            'is_primary'
        );

        if (!$validated['adm_area_level_1'] || !$validated['adm_area_level_2']) {
            $coverageDetail = CoverageDetail::where('kecamatan', $validated['adm_area_level_3'])
                ->where('kelurahan', $validated['adm_area_level_4'])
                ->first();

            if ($coverageDetail) {
                $validated['adm_area_level_1'] = $coverageDetail->provinsi;
                $validated['adm_area_level_2'] = $coverageDetail->kota_kabupaten;
                // abort(200, 'Alamat di luar jangkauan pengiriman');
            }
        }

        if (!$validated['url_title']) {
            $validated['url_title'] = strtolower(str_replace(' ', '-', $validated['title']));
        }

        $validated['phone'] = $validated['phone'] = Format::phoneNumber($request->phone);

        if ($request->has('is_primary') && $request->is_primary) {
            self::setPrimaryAddress($user->id, $address->id);
        }

        // Clean the text...
        $validated['url_title'] = Format::cleanSpecialChar($validated['url_title']);
        $validated['title'] = Format::cleanSpecialChar($validated['title']);
        $validated['address'] = Format::cleanSpecialChar($validated['address']);
        $validated['adm_area_level_2'] = Format::cleanCity($validated['adm_area_level_2']);
        $validated['adm_area_level_3'] = Format::cleanDistric($validated['adm_area_level_3']);

        if ($validated['g_route'] == '' || $validated['g_route'] == null) {
            $validated['g_route'] = implode(', ', [
                $validated['address'],
                $validated['adm_area_level_4'],
                $validated['adm_area_level_3'],
                $validated['adm_area_level_2'],
                $validated['adm_area_level_1'],
                $validated['postal_code'],
                $validated['country'],
            ]);
        }
        
        if ($validated['alamat_region'] == '' || $validated['alamat_region'] == null) {
            $validated['alamat_region'] = $validated['g_route'];
        }

        $validated['country'] = $validated['country']
            ? $validated['country']
            : 'Indonesia';

        $checkSave = $address->update($validated);

        /* if ($oldLat != $address->lat || $oldLng != $address->lng) {
            DistanceController::storeDistance($address);
        } */

        return responseArray([
            'message' => $checkSave ? 'Alamat berhasil diubah.' : 'Terjadi kesalahan dalam memperbarui alamat Anda.'
        ], 200, $checkSave ? false : true);
    }

    public function destroyAddress(Request $request)
    {
        $this->validate($request, [
            'address_id' => 'required|exists:App\Member\Address,id',
        ]);

        Address::where('id', $request->address_id)
            ->update([
                'is_primary' => 0,
                'publish' => 0,
                'status' => 0,
            ]);

        $user = Auth::user();

        $primaryAddress = $user->addresses()
            ->where('is_primary', 1)
            ->active()
            ->primary()
            ->first();
        $firstActiveAddress = $user->addresses()
            ->active()
            ->first();

        $addMessage = '';
        if (!$primaryAddress && $firstActiveAddress) {
            $firstActiveAddress->is_primary = 1;
            $firstActiveAddress->save();
            $addMessage = ' Alamat utama telah diatur otomatis.';
        }

        return responseSuccess([
            'message' => 'Alamat dihapus.' . $addMessage
        ]);
    }

    public function primaryAddress(Request $request)
    {
        $this->validate($request, [
            'address_id' => 'required|exists:App\Member\Address,id',
        ]);

        $address = Address::where('member_id', Auth::id())
            ->find($request->address_id);

        if (!$address)
            abort(200, 'Alamat Anda tidak ditemukan.');

        self::setPrimaryAddress(Auth::id(), $request->address_id);

        return responseSuccess([
            'message' => 'Alamat utama telah diubah.'
        ]);
    }

    public function calculateDirection(Request $request)
    {
        $this->validate($request, [
            'lat' => 'required|string',
            'lng' => 'required|string',
        ]);

        $pool = Pool::select('id', 'lat', 'lng', 'wilayah_id', DB::raw('(ABS(? - lat) + ABS(? - lng) ) AS distance'))
            ->where('wilayah_id', 12) // CIPONDOH, this line should be commented in the future.
            ->orderBy('distance')
            ->setBindings([$request->lat, $request->lng, 12]) // the CIPONDOH also should be removed in the future.
            ->first();

        if (!$pool) {
            abort(200, 'Tidak ditemukan pool terdekat');
        }

        $wilayah = Wilayah::select('id', 'radius')->find($pool->wilayah_id);

        $gDistance = CartController::gDistance($pool->lat, $pool->lng, $request->lat, $request->lng);

        if ($gDistance->value > $wilayah->radius) {
            $message = 'belum dapat kami layani.';
            $error = false;
        } else {
            $message = 'berada dalam jangkauan.';
            $error = false;
        }

        return responseArray([
            'message' => 'Jarak delivery ' . $message,
            'jarak' => $gDistance->text,
            'jarak_value' => $gDistance->value,
            'radius' => $wilayah->radius . ' km',
        ], 200, $error);
    }

    public function addressType()
    {
        return Format::response([
            'data' => AddressType::select('id', 'title')->get()
        ]);
    }


    public function detail(Request $request)
    {
        $alamat_id = $request->alamat_id;

        $addresses = Address::select(
                'tm_member_addresses.id',
                'tm_member_addresses.title',
                'tm_member_addresses.phone',
                'tm_member_addresses.email',
                'tm_member_addresses.address',
                'tm_member_addresses.alamat_region',
                'tm_member_addresses.g_route',
                'tm_member_addresses.adm_area_level_1',
                'tm_member_addresses.adm_area_level_2',
                'tm_member_addresses.adm_area_level_3',
                'tm_member_addresses.adm_area_level_4',
                'tm_member_addresses.country',
                'tm_member_addresses.postal_code',
                'tm_member_addresses.notes',
                'tm_member_addresses.lat',
                'tm_member_addresses.lng',
                'tm_member_addresses.is_primary',
                'tm_tipe_alamat.id AS tipe_id',
                'tm_tipe_alamat.title AS tipe'
            )
            ->leftJoin('tm_tipe_alamat', 'tm_tipe_alamat.id', '=', 'tm_member_addresses.tipe')
            ->where('tm_member_addresses.member_id', Auth::id())
            ->where('tm_member_addresses.status', 1)
            ->where('tm_member_addresses.publish', 1)
            ->where('tm_member_addresses.id',$alamat_id)
            ->orderBy('tm_member_addresses.is_primary', 'desc')
            ->orderBy('tm_member_addresses.title')
            ->first();

        // foreach ($addresses as $address) {

            if($addresses){
                $addresses->phone = Format::castPhoneNumber($addresses->phone);
                $addresses->nama = ucwords(strtolower($addresses->title));
                $addresses->alamat = $addresses->fullAddress();
                $addresses->kodepos = $addresses->postal_code;
    
                unset($addresses->address);
                unset($addresses->title);
                unset($addresses->postal_code);
            }
        // }

        return responseArray([
            // 'count' => count($addresses),
            'data' => $addresses
        ]);
    }
}
