<?php
// app/Http/Controllers/UserRequestController.php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserRequestController extends Controller
{
    public function create()
    {
        // Exclude users with Admin role from the assign-to list
        $users = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->select('users.id', 'employee_numbers.Full_Name', 'departments.Name as department', 'users.role')
            ->where(function($q) {
                $q->whereNull('users.role')->orWhere('users.role', '!=', 'Admin');
            })
            ->orderBy('employee_numbers.Full_Name')
            ->get();
        return view('users.request.request_asset', compact('users'));
    }
    
    public function store(HttpRequest $request)
    {
        $rules = [
            'request_type' => 'required|in:Repair,Disposal,Transfer,Replacement,Pullout,Other',
            'asset_code' => 'required|string|exists:assets,Asset_code',
            'notes' => 'required|string',
            'attachment' => 'nullable|image|max:10240',
            'qr_code' => 'nullable|image|max:10240',
            'assign_to_user_id' => 'nullable|exists:users,id',
        ];

        // If transfer, require assign_to_user_id
        if ($request->input('request_type') === 'Transfer') {
            $rules['assign_to_user_id'] = 'required|exists:users,id';
        }

        $validated = $request->validate($rules);

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

        $insertData = [
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
        ];

        // Only include assign_to_user_id if the column exists (migration may not have been run)
        if (Schema::hasColumn('requests', 'assign_to_user_id')) {
            $insertData['assign_to_user_id'] = $validated['assign_to_user_id'] ?? null;
        }

        $requestId = DB::table('requests')->insertGetId($insertData);

        // Audit: user submitted a request
        try {
            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'request_id' => $requestId,
                'asset_id' => $asset->id,
                'notes' => 'Submitted ' . $validated['request_type'] . ' request for ' . ($asset->Asset_code ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // ignore
        }

        return redirect()->route('user.request-asset')->with('success', 'Asset request has been sent successfully.');
    }
}
