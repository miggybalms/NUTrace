<?php
// app/Http/Controllers/UserRequestController.php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserRequestController extends Controller
{
    public function create()
    {
        return view('users.request.request_asset');
    }
    
    public function store(HttpRequest $request)
    {
        $validated = $request->validate([
            'request_type' => 'required|in:Repair,Disposal,Transfer,Replacement,Pullout,Other',
            'asset_code' => 'required|string|exists:assets,Asset_code',
            'notes' => 'required|string',
            'attachment' => 'nullable|image|max:10240',
            'qr_code' => 'nullable|image|max:10240',
        ]);

        $asset = Asset::where('Asset_code', $validated['asset_code'])->first();

        if (!$asset) {
            return back()->withErrors([
                'asset_code' => 'The asset code was not found.',
            ])->withInput();
        }

        $uploadedFile = $request->file('attachment') ?? $request->file('qr_code');
        $fileName = null;
        $filePath = null;
        $fileSize = null;
        $mimeType = null;
        $url = null;

        if ($uploadedFile) {
            $filePath = $uploadedFile->store('request_files', 'public');
            $fileName = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();
            $mimeType = $uploadedFile->getClientMimeType();
            $url = Storage::url($filePath);
        }

        DB::table('requests')->insert([
            'user_id' => Auth::id(),
            'asset_id' => $asset->id,
            'request_type' => $validated['request_type'],
            'status' => 'Pending',
            'Note' => $validated['notes'],
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'url' => $url,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('user.request-asset')->with('success', 'Asset request has been sent successfully.');
    }
}
