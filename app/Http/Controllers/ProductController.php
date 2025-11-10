<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Category;
use App\Models\CartItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::where('company_id', auth()->user()->company_id)->with(['images', 'variations'])->get();
        
        return response()->json($products);
    }

    public function getCategories()
    {
        $authUser = auth()->user();

        $categories = Category::where('company_id', $authUser->company_id)
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request) 
    {
        $authUser = auth()->user();

        $request->merge([
            'price' => str_replace(',', '.', $request->price)
        ]);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) use ($authUser) {
                    return $query->where('company_id', $authUser->company_id);
                })
            ],
            'description'                   => 'nullable|string',
            'price'                         => 'required|numeric|min:0',
            'stock_quantity'                => 'required|integer|min:0',
            'category'                      => 'nullable|string|max:255',
            'status'                        => 'required|in:ativo,em_falta,oculto',
            'images.*'                      => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ], [
            'name.required'           => 'O campo nome é obrigatório.',
            'name.unique'             => 'Já existe um produto com este nome.',
            'price.required'          => 'O campo preço é obrigatório.',
            'price.numeric'           => 'O campo preço deve ser um número.',
            'price.min'               => 'O campo preço deve ser no mínimo 0.',
            'stock_quantity.required' => 'O campo quantidade de estoque é obrigatório.',
            'stock_quantity.integer'  => 'O campo quantidade de estoque deve ser um número inteiro.',
            'stock_quantity.min'      => 'O campo quantidade de estoque deve ser no mínimo 0.',
            'images.*.image'          => 'Cada arquivo deve ser uma imagem.',
            'images.*.mimes'          => 'As imagens devem estar no formato jpg, jpeg ou png.',
            'images.*.max'            => 'Cada imagem não pode ultrapassar 2MB.'
        ]);

        $categoryId = null;
        if ($request->filled('category')) {
            $category = Category::firstOrCreate(
                [
                    'name'       => $request->category,
                    'company_id' => $authUser->company_id,
                ]
            );
            $categoryId = $category->id;
        }

        $product = Product::create([
            'company_id'                    => $authUser->company_id,
            'name'                          => $request->name,
            'description'                   => $request->description,
            'price'                         => $request->price,
            'stock_quantity'                => $request->stock_quantity,
            'status'                        => $request->status,
            'category_id'                   => $categoryId
        ]);

        if ($request->filled('variations')) {
            foreach ($request->variations as $v) {
                $product->variations()->create([
                    'type'  => $v['type'],
                    'value' => $v['value'],
                ]);
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path
                ]);
            }
        }

        return response()->json($product->load('images', 'variations'), 201);
    }

    public function update(Request $request, Product $product) 
    {
        $authUser = auth()->user();

        $request->merge([
            'price' => str_replace(',', '.', $request->price)
        ]);
        
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products')->ignore($product->id)->where(function ($query) use ($authUser) {
                    return $query->where('company_id', $authUser->company_id);
                }),
            ],
            'description'                   => 'nullable|string',
            'price'                         => 'required|numeric|min:0',
            'stock_quantity'                => 'required|integer|min:0',
            'category'                      => 'nullable|string|max:255',
            'status'                        => 'required|in:ativo,em_falta,oculto',
            'images.*'                      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'existing_images'               => 'nullable|array'
        ], [
            'name.required'            => 'O campo nome é obrigatório.',
            'name.unique'              => 'Já existe um produto com este nome.',
            'price.required'           => 'O campo preço é obrigatório.',
            'price.numeric'            => 'O campo preço deve ser um número.',
            'price.min'                => 'O campo preço deve ser no mínimo 0.',
            'stock_quantity.required'  => 'O campo quantidade de estoque é obrigatório.',
            'stock_quantity.integer'   => 'O campo quantidade de estoque deve ser um número inteiro.',
            'stock_quantity.min'       => 'O campo quantidade de estoque deve ser no mínimo 0.',
            'images.*.image'           => 'Cada arquivo deve ser uma imagem.',
            'images.*.mimes'           => 'As imagens devem estar no formato jpg, jpeg ou png.',
            'images.*.max'             => 'Cada imagem não pode ultrapassar 2MB.',
            'existing_images.array'    => 'As imagens existentes devem ser enviadas em formato de lista.',
            'existing_images.*.string' => 'Cada imagem existente deve ser identificada por um caminho válido.'
        ]);

        $categoryId = $product->category_id;
        if ($request->filled('category')) {
            $category = Category::firstOrCreate(
                ['name' => $request->category, 'company_id' => $authUser->company_id]
            );
            $categoryId = $category->id;
        }

        $product->update([
            'name'                          => $request->name,
            'description'                   => $request->description,
            'price'                         => $request->price,
            'stock_quantity'                => $request->stock_quantity,
            'status'                        => $request->status,
            'category_id'                   => $categoryId
        ]);

        if ($request->filled('variations')) {
            $product->variations()->delete();

            foreach ($request->variations as $v) {
                $product->variations()->create([
                    'type'  => $v['type'],
                    'value' => $v['value'],
                ]);
            }
        }

        $existingImages = $request->input('existing_images', []);
        $imagesToDelete = $product->images()
            ->whereNotIn('image_path', $existingImages)
            ->get();
            
        foreach ($imagesToDelete as $img) {
            Storage::disk('public')->delete($img->image_path);
            $img->delete();
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image_path' => $path
                ]);
            }
        }

        return response()->json($product->load('images', 'variations'), 200);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        
        return response()->json(['message' => 'Produto removido']);
    }

    public function getCart(Request $request)
    {
        $authUser = auth()->user();

        $cartQuery = Cart::query();
        if ($authUser->role === 'client') {
            $cartQuery->where('user_id', $authUser->id);
        } else {
            $cartQuery->where('company_id', $authUser->company_id);
        }

        $cart = $cartQuery->with([
            'items.product.images',
            'items.product.company:id,legal_name,final_name,cnpj,phone,address,plan,active,email,category,status,logo,delivery_fee,delivery_radius,opening_hours,free_shipping,first_purchase_discount_store,first_purchase_discount_store_value,first_purchase_discount_app,first_purchase_discount_app_value',
            'items.variations', 
        ])->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Carrinho vazio',
                'cart'    => null,
                'company' => null
            ]);
        }

        $total = $cart->items->reduce(function ($sum, $item) {
            return $sum + ($item->quantity * $item->price);
        }, 0);

        $items = $cart->items->map(function ($item) {
            $variationKey = $item->variations->map(fn($v) => "{$v->type}:{$v->value}")->implode(' | ');

            return [
                'id'                 => $item->id,
                'product_id'         => $item->product->id,
                'product' => [
                    'id'             => $item->product->id,
                    'name'           => $item->product->name,
                    'description'    => $item->product->description,
                    'price'          => $item->product->price,
                    'stock_quantity' => $item->product->stock_quantity,
                    'company_id'     => $item->product->company_id,
                    'images'         => $item->product->images->map(fn($img) => [
                        'id'         => $img->id,
                        'image_path' => $img->image_path,
                    ]),
                ],
                'quantity'           => $item->quantity,
                'price'              => $item->price,
                'subtotal'           => $item->quantity * $item->price,
                'variation_key'      => $variationKey,
                'variations' => $item->variations->map(fn($v) => [
                    'id'    => $v->id,
                    'type'  => $v->type,
                    'value' => $v->value,
                ]),
            ];
        });

        $company = optional($cart->items->first()->product)->company;

        return response()->json([
            'message' => 'Carrinho recuperado com sucesso',
            'cart' => [
                'id'         => $cart->id,
                'created_at' => $cart->created_at,
                'items'      => $items,
                'total'      => $total,
            ],
            'company' => $company
        ]);
    }

    public function addCart(Request $request)
    {
        $authUser = auth()->user();

        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_ids' => 'nullable|array',
            'products.*.variation_ids.*' => 'nullable|integer|exists:product_variations,id',
        ], [
            'products.required' => 'É necessário enviar ao menos um produto.',
            'products.array' => 'O campo produtos deve ser um array.',
            'products.min' => 'É necessário enviar ao menos um produto.',
            'products.*.id.required' => 'O ID do produto é obrigatório.',
            'products.*.id.integer' => 'O ID do produto deve ser um número inteiro.',
            'products.*.id.exists' => 'O produto informado não existe.',
            'products.*.quantity.required' => 'A quantidade do produto é obrigatória.',
            'products.*.quantity.integer' => 'A quantidade deve ser um número inteiro.',
            'products.*.quantity.min' => 'A quantidade mínima é 1.',
            'products.*.variation_ids.array' => 'As variações devem ser enviadas em um array.',
            'products.*.variation_ids.*.integer' => 'Cada ID de variação deve ser um número inteiro.',
            'products.*.variation_ids.*.exists' => 'A variação selecionada não existe.',
        ]);

        $cartQuery = Cart::query();
        if ($authUser->role === 'client') {
            $cartQuery->where('user_id', $authUser->id);
        } else {
            $cartQuery->where('company_id', $authUser->company_id);
        }

        $cart = $cartQuery->firstOrCreate([
            'user_id' => $authUser->role === 'client' ? $authUser->id : null,
            'company_id' => $authUser->role !== 'client' ? $authUser->company_id : null,
        ]);

        $existingCompanyId = $cart->items()->exists() ? $cart->items()->first()->product->company_id : null;

        foreach ($request->products as $p) {
            $product = Product::find($p['id']);
            $variationIds = $p['variation_ids'] ?? [];

            if (!$product) {
                return response()->json([
                    'message' => "Produto ID {$p['id']} não encontrado"
                ], 404);
            }

            $existingItem = $cart->items()
                ->where('product_id', $product->id)
                ->whereHas('variations', function ($q) use ($variationIds) {
                    $q->whereIn('product_variations.id', $variationIds);
                }, '=', count($variationIds))
                ->first();

            $newQuantity = $p['quantity'];

            if ($existingItem) {
                $newQuantity += $existingItem->quantity;
            }

            if ($product->stock_quantity < $newQuantity) {
                return response()->json([
                    'message' => "Produto {$product->name} não possui estoque suficiente"
                ], 422);
            }

            if ($existingItem) {
                $existingItem->update(['quantity' => $newQuantity]);
            } else {
                $newItem = $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $p['quantity'],
                    'price'      => $product->price,
                ]);

                if (!empty($variationIds)) {
                    $newItem->variations()->sync($variationIds);
                }
            }
        }

        return response()->json([
            'message' => 'Produtos adicionados ao carrinho com sucesso',
            'cart' => $cart->load('items.product.images', 'items.variations')
        ]);
    }

    private function calculateDistance(string $clientAddress, string $companyAddress): float
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');

        $clientAddressEncoded = urlencode($clientAddress);
        $companyAddressEncoded = urlencode($companyAddress);

        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$clientAddressEncoded}&destinations={$companyAddressEncoded}&key={$apiKey}";

        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (!isset($data['rows'][0]['elements'][0]['distance']['value'])) {
            throw new \Exception('Não foi possível calcular a distância');
        }

        $distanceMeters = $data['rows'][0]['elements'][0]['distance']['value'];
        $distanceKm = $distanceMeters / 1000;

        return $distanceKm;
    }

    public function calculate(Request $request)
    {
        $authUser = auth()->user();
        $address = $request->input('address');

        $cart = Cart::where('user_id', $authUser->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['error' => 'Carrinho vazio'], 422);
        }

        $fees = [];
        $maxDistance = 0;

        foreach ($cart->items as $item) {
            $company = $item->product->company;

            if (!$company) continue;

            $distance = $this->calculateDistance($address, $company->address);
            $maxDistance = max($maxDistance, $distance);

            // if ($distance > $company->delivery_radius) {
            //     return response()->json([
            //         'error' => 'Endereço fora do raio de entrega da empresa ' . $company->name
            //     ], 422);
            // }

            $fee = $company->delivery_fee * ceil($distance / $company->delivery_radius);
            $fees[] = $fee;
        }

        $totalFee = max($fees);

        return response()->json([
            'fee' => $totalFee,
            'distance' => $maxDistance
        ]);
    }

    public function removeItem(CartItem $item)
    {
        $authUser = auth()->user();

        $cartQuery = Cart::query();
        if ($authUser->role === 'client') {
            $cartQuery->where('user_id', $authUser->id);
        } else {
            $cartQuery->where('company_id', $authUser->company_id);
        }

        $cart = $cartQuery->first();

        if (!$cart) {
            return response()->json(['message' => 'Carrinho não encontrado'], 404);
        }

        if ($item->cart_id !== $cart->id) {
            return response()->json(['message' => 'Item não pertence a este carrinho'], 403);
        }

        $item->delete();

        if ($cart->items()->count() === 0) {
            $cart->delete();
            return response()->json(['message' => 'Produto removido e carrinho excluído']);
        }

        return response()->json(['message' => 'Produto removido do carrinho']);
    }

    public function incrementItem(CartItem $item)
    {
        $item->quantity += 1;
        $item->save();
        return response()->json(['message' => 'Quantidade aumentada']);
    }

    public function decrementItem(CartItem $item)
    {
        if ($item->quantity > 1) {
            $item->quantity -= 1;
            $item->save();
        } else {
            $item->delete();
        }
        return response()->json(['message' => 'Quantidade atualizada']);
    }

    public function checkout(Request $request)
    {
        $authUser = auth()->user();

        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'address_id'         => 'required|integer|exists:addresses,id',
        ],[
            'items.required'              => 'É necessário informar ao menos um item no carrinho.',
            'items.*.product_id.required' => 'Cada item deve conter o ID do produto.',
            'items.*.product_id.exists'   => 'Um dos produtos informados não foi encontrado.',
            'items.*.quantity.required'   => 'Informe a quantidade de cada produto.',
            'items.*.quantity.min'        => 'A quantidade mínima é 1.',
            'address_id.required'         => 'Selecione um endereço de entrega.',
            'address_id.exists'           => 'O endereço selecionado não foi encontrado.'
        ]);

        $address = $authUser->addresses()->find($request->address_id);

        if (!$address) {
            return response()->json(['message' => 'Endereço inválido'], 422);
        }

        $cart = Cart::with('items.product')->where('user_id', $authUser->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Carrinho vazio'], 422);
        }

        foreach ($request->items as $product) {
            $item = $cart->items->where('product_id', $product['product_id'])->first();
            if ($item) {
                $item->quantity = $product['quantity'];
                $item->save();
            }
        }

        foreach ($cart->items as $item) {
            if ($item->product->stock_quantity < $item->quantity) {
                return response()->json([
                    'message' => "Produto {$item->product->name} não possui estoque suficiente"
                ], 422);
            }
        }

        // foreach ($cart->items as $item) {
        //     $item->product->decrement('stock_quantity', $item->quantity);
        // }   

        $order = Order::create([
            'user_id'    => $authUser->id,
            'store_id'   => $cart->items->first()->product->company_id,
            'status'     => 'pending',
            'code'       => strtoupper(Str::random(6)),
            'total'      => $request->input('total'),
            'address_id' => $address->id, 
        ]);

        foreach ($cart->items as $item) {
            $order->items()->create([
                'product_id' => $item->product->id,
                'quantity'   => $item->quantity,
                'price'      => $item->product->price
            ]);
        }

        $cart->delete();

        return response()->json([
            'message' => 'Pedido criado com sucesso',
            'order'   => $order->load('items.product')
        ]);
    }

    public function getOrders()
    {
        $authUser = auth()->user();

        $orders = Order::with(['items.product.images', 'store', 'items.product.variations']) 
            ->where('user_id', $authUser->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function getStoreOrders()
    {
        $authUser = auth()->user();

        $storeId = $authUser->company_id;

        $orders = Order::with(['items.product.images', 'user']) 
            ->where('store_id', $storeId)
            // ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function updateClientOrders(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        $status = $request->input('status');

        $order->status = $status;
        $order->save();

        return response()->json([
            'message' => 'Status atualizado com sucesso',
            'status' => $status,
        ]);
    }

    public function updateStoreOrders(Request $request, Order $order)
    {
        $authUser = auth()->user();

        if ($order->store_id !== $authUser->company_id) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $request->validate([
            'status' => 'required|string|in:pending,processing,completed,canceled,ready_for_pickup,awaiting_confirmation,pending_payment',
        ]);

        $newStatus = $request->status;

        try {
            DB::transaction(function () use ($order, $newStatus) {
                if (
                    $newStatus === 'processing' &&
                    in_array($order->status, ['awaiting_confirmation', 'pending_payment'])
                ) {
                    $order->load('items.product');

                    foreach ($order->items as $item) {
                        $product = $item->product;

                        if ($product->stock_quantity < $item->quantity) {
                            throw ValidationException::withMessages([
                                'message' => "Produto '{$product->name}' não possui estoque suficiente"
                            ]);
                        }

                        $product->decrement('stock_quantity', $item->quantity);
                    }
                }

                $order->status = $newStatus;
                $order->save();
            });

            return response()->json([
                'message' => 'Status atualizado com sucesso',
                'order' => $order->fresh('items.product')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['message'][0]
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar status do pedido: ' . $e->getMessage()
            ], 500);
        }
    }
}
