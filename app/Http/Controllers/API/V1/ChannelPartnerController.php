<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ChannelPartner;

class ChannelPartnerController extends BaseController
{
    public function index()
    {
       $data = ChannelPartner::latest()->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Channel Partner List',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $partner = ChannelPartner::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Channel Partner Detail',
            'data' => $partner
        ]);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'photo' => 'required|image|max:2048',

            'name' => 'required|string|max:150',
            'company_name' => 'required|string|max:150',
            'designation' => 'required|string|max:100',

            'mobile' => 'required|regex:/^\+?[0-9]{10,15}$/|unique:channel_partners,mobile',
            'whatsapp_no' => 'required|regex:/^\+?[0-9]{10,15}$/',

            'address' => 'required|string',
            'state' => 'required|string|max:100',
            'city' => 'required|string|max:100',

            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $data['photo'] = $req->file('photo')
            ->store('channel_partners','public');

        $partner = ChannelPartner::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Created',
            'data' => $partner
        ], 201);
    }

    public function update(Request $req, $id)
    {
        $partner = ChannelPartner::findOrFail($id);

        $data = $req->validate([
            'photo' => 'nullable|image|max:2048',

            'name' => 'required|string|max:150',
            'company_name' => 'required|string|max:150',
            'designation' => 'required|string|max:100',

            'mobile' => 'required|regex:/^\+?[0-9]{10,15}$/|unique:channel_partners,mobile,'.$id,
            'whatsapp_no' => 'required|regex:/^\+?[0-9]{10,15}$/',

            'address' => 'required|string',
            'state' => 'required|string|max:100',
            'city' => 'required|string|max:100',

            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        // only if new photo uploaded
        if ($req->hasFile('photo')) {

            // old photo delete (optional but recommended)
            if ($partner->photo) {
                Storage::disk('public')->delete($partner->photo);
            }

            $data['photo'] = $req->file('photo')
                ->store('channel_partners','public');
        }

        $partner->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Updated',
            'data' => $partner
        ]);
    }

    public function destroy($id)
    {
        $partner = ChannelPartner::findOrFail($id);

        if ($partner->photo) {
            Storage::disk('public')->delete($partner->photo);
        }

        $partner->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted'
        ]);
    }
}
