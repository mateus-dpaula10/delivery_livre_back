<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class DashboardAdminGeralController extends Controller
{
    public function storeBanners(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'image_url' => 'required|string|max:255',
            'target_company_id' => 'nullable|exists:companies,id',
        ]);

        $banner = Banner::create($validated);

        return response()->json([
            'message' => 'Banner criado com sucesso.',
            'banner' => $banner,
        ], 201);
    }
}
