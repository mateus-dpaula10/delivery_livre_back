<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class DashboardAdminGeralController extends Controller
{
    public function index()
    {
        $banners = Banner::orderByDesc('created_at')->get();

        return response()->json($banners, 200);
    }

    public function bannersCompany()
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id ?? null;

        $banners = Banner::where(function ($query) use ($companyId) {
                $query->whereNull('target_company_id')
                    ->orWhere('target_company_id', $companyId);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($banners);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_company_id' => 'nullable|exists:companies,id',
            'image' => 'required|file|image|max:2048'
        ], [
            'title.required' => 'O campo título é obrigatório.',
            'image.file' => 'A imagem enviada não é um arquivo válido.',
            'image.image' => 'O arquivo enviado deve ser uma imagem.',
            'image.max' => 'A imagem não pode ultrapassar 2MB.',
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $validated['title'],
            'image_url' => asset('storage/' . $path),
            'target_company_id' => $validated['target_company_id'] ?? null
        ]);

        return response()->json([
            'message' => 'Banner criado com sucesso.',
            'banner' => $banner,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_company_id' => 'nullable|exists:companies,id',
            'image' => 'nullable|file|image|max:2048'
        ], [
            'title.required' => 'O campo título é obrigatório.',
            'image.file' => 'A imagem enviada não é um arquivo válido.',
            'image.image' => 'O arquivo enviado deve ser uma imagem.',
            'image.max' => 'A imagem não pode ultrapassar 2MB.',
        ]);

        $banner->title = $validated['title'];
        $banner->target_company_id = $validated['target_company_id'] ?? null;

        if ($request->hasFile('image')) {
            if ($banner->image_url && str_contains($banner->image_url, 'storage/')) {
                $oldPath = str_replace(asset('storage') . '/', '', $banner->image_url);
                \Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('banners', 'public');
            $banner->image_url = asset('storage/' . $path);
        }

        $banner->save();

        return response()->json([
            'message' => 'Banner atualizado com sucesso.',
            'banner' => $banner,
        ], 200);
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return response()->json([
            'message' => 'Banner excluído com sucesso.',
        ], 200);
    }
}
