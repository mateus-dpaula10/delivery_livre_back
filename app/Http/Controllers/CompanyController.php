<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with('admin')->get();
        
        return response()->json($companies);
    }

    public function store(Request $request)
    {
        $request->validate([
            'legal_name'                    => 'required|string|max:255',
            'final_name'                    => 'required|string|max:255',
            'cnpj'                          => 'required|string|unique:companies,cnpj',
            'admin.email'                   => 'required|email|unique:users,email',
            'admin.name'                    => 'required|string|max:255',
            'admin.password'                => 'required|min:8',
        ], [
            'legal_name.required'        => 'O nome empresarial é obrigatório.',
            'final_name.required'        => 'O nome real da loja é obrigatório.',
            'cnpj.required'              => 'O campo CNPJ é obrigatório.',
            'cnpj.unique'                => 'Este CNPJ já está cadastrado.',
            'admin.email.required'       => 'O e-mail do administrador é obrigatório.',
            'admin.email.email'          => 'O e-mail do administrador deve ser um e-mail válido.',
            'admin.email.unique'         => 'Este e-mail já está em uso.',
            'admin.name.required'        => 'O nome do administrador é obrigatório.',
            'admin.name.max'             => 'O nome do administrador não pode ter mais de :max caracteres.',
            'admin.password.required'    => 'A senha é obrigatória.',
            'admin.password.min'         => 'A senha deve ter pelo menos :min caracteres.'
        ]);

        $company = Company::create($request->only([
            'legal_name', 
            'final_name', 
            'cnpj', 
            'phone', 
            'address', 
            'plan'
        ]));

        $admin = new User([
            'name' => $request->admin['name'],
            'email' => $request->admin['email'],
            'password' => bcrypt($request->admin['password']),
            'role' => 'store'
        ]);

        $admin->company()->associate($company);
        $admin->save();

        return response()->json([
            'company' => $company, 
            'admin' => $admin
        ], 201);
    }

    public function myCompany()
    {
        $authUser = auth()->user();

        if (!$authUser->company) {
            return response()->json(['message' => 'Nenhuma empresa associada'], 404);
        }

        return response()->json($authUser->company);
    }

    public function addInfo(Request $request)
    {
        $authUser = auth()->user();
        $company = $authUser->company;

        $request->merge([
            'delivery_fee' => str_replace(',', '.', $request->delivery_fee)
        ]);

        $request->validate([
            'final_name'                          => 'required|string|unique:companies,final_name,' . $company->id,
            'phone'                               => 'nullable|string|max:20',
            'email'                               => 'nullable|email|unique:companies,email,' . $company->id,
            'category'                            => 'nullable|string|max:255',
            'status'                              => 'nullable|in:active,suspended,pending',
            'logo'                                => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'delivery_fee'                        => 'nullable|numeric|min:0',
            'delivery_radius'                     => 'nullable|integer|min:1',
            'free_shipping'                       => 'nullable|boolean',
            'first_purchase_discount_store'       => 'nullable|boolean',
            'first_purchase_discount_store_value' => 'nullable|integer|in:15,20,25',
            'first_purchase_discount_app'         => 'nullable|boolean',
            'first_purchase_discount_app_value'   => 'nullable|integer|in:15,20,25',
            'opening_hours'                       => 'nullable|array',
            'opening_hours.*.day'                 => 'required_with:opening_hours|string|max:20',
            'opening_hours.*.open'                => 'required_with:opening_hours|string|max:5',
            'opening_hours.*.close'               => 'required_with:opening_hours|string|max:5',
            'pix_key'                             => 'nullable|string|max:255',
            'pix_key_type'                        => 'nullable|in:cpf,cnpj,email,phone,random',
            'cep'                                 => 'nullable|string|max:9',
            'street'                              => 'nullable|string|max:255',
            'number'                              => 'nullable|string|max:10',
            'neighborhood'                        => 'nullable|string|max:255',
            'city'                                => 'nullable|string|max:255',
            'state'                               => 'nullable|string|max:2',
        ], [
            'email.email'                            => 'O e-mail informado não é válido.',
            'email.unique'                           => 'Este e-mail já está sendo usado por outra empresa.',
            'category.string'                        => 'A categoria deve ser uma string.',
            'category.max'                           => 'A categoria não pode ter mais de :max caracteres.',
            'status.in'                              => 'Status inválido. Deve ser: active, suspended ou pending.',
            'logo.image'                             => 'O arquivo deve ser uma imagem.',
            'logo.mimes'                             => 'O logo deve ser JPG, JPEG ou PNG.',
            'logo.max'                               => 'O logo não pode ter mais de 2MB.',
            'delivery_fee.numeric'                   => 'A taxa de entrega deve ser numérica.',
            'delivery_fee.min'                       => 'A taxa de entrega não pode ser negativa.',
            'delivery_radius.integer'                => 'O raio de entrega deve ser um número inteiro.',
            'delivery_radius.min'                    => 'O raio de entrega deve ser pelo menos 1 km.',
            'opening_hours.array'                    => 'O horário de funcionamento deve ser um array.',
            'opening_hours.*.day.required_with'      => 'O dia é obrigatório para horário de funcionamento.',
            'opening_hours.*.open.required_with'     => 'O horário de abertura é obrigatório para o dia informado.',
            'opening_hours.*.close.required_with'    => 'O horário de fechamento é obrigatório para o dia informado.',
            'opening_hours.*.day.max'                => 'O nome do dia não pode ter mais de :max caracteres.',
            'opening_hours.*.open.max'               => 'O horário de abertura deve ter no máximo :max caracteres.',
            'opening_hours.*.close.max'              => 'O horário de fechamento deve ter no máximo :max caracteres.',
            'first_purchase_discount_store_value.in' => 'O desconto da loja deve ser 15%, 20% ou 25%.',
            'first_purchase_discount_app_value.in'   => 'O desconto do app deve ser 15%, 20% ou 25%.',
            'pix_key.max'                            => 'A chave PIX não pode ter mais de :max caracteres.',
            'pix_key_type.in'                        => 'Tipo da chave PIX inválido.',
            'cep.max'                                => 'O CEP não pode ter mais de :max caracteres.',
            'street.max'                             => 'O nome da rua não pode ter mais de :max caracteres.',
            'number.max'                             => 'O número não pode ter mais de :max caracteres.',
            'neighborhood.max'                       => 'O bairro não pode ter mais de :max caracteres.',
            'city.max'                               => 'O nome da cidade não pode ter mais de :max caracteres.',
            'state.max'                              => 'O estado deve ter no máximo 2 caracteres.'
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $company->logo = $path;
        }

        if ($request['first_purchase_discount_store'] && $request['first_purchase_discount_app']) {
            return response()->json([
                'message' => 'Apenas um tipo de desconto de primeira compra pode estar ativo.'
            ], 422);
        }

        $company->final_name = $request->final_name ?? $company->final_name;
        $company->email = $request->email ?? $company->email;
        $company->cep = $request->cep ?? $company->cep;
        $company->street = $request->street ?? $company->street;
        $company->number = $request->number ?? $company->number;
        $company->neighborhood = $request->neighborhood ?? $company->neighborhood;
        $company->city = $request->city ?? $company->city;
        $company->state = $request->state ?? $company->state;
        $company->category = $request->category ?? $company->category;
        $company->status = $request->status ?? $company->status;
        $company->delivery_fee = $request->delivery_fee !== '' ? $request->delivery_fee : null;
        $company->delivery_radius = $request->delivery_radius !== '' ? $request->delivery_radius : null;
        $company->free_shipping = $request->free_shipping ?? $company->free_shipping ?? false;

        $company->first_purchase_discount_store = $request->first_purchase_discount_store ?? false;
        $company->first_purchase_discount_store_value = $request->first_purchase_discount_store ? $request->first_purchase_discount_store_value : null;

        $company->first_purchase_discount_app = $request->first_purchase_discount_app ?? false;
        $company->first_purchase_discount_app_value = $request->first_purchase_discount_app ? $request->first_purchase_discount_app_value : null;

        if ($request->opening_hours) {
            $company->opening_hours = json_encode($request->opening_hours);
        }

        $company->pix_key = $request->pix_key ?? $company->pix_key;
        $company->pix_key_type = $request->pix_key_type ?? $company->pix_key_type;

        $company->save();

        return response()->json([
            'message' => 'Informações adicionais salvas com sucesso.',
            'company' => $company
        ], 200);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'legal_name'     => 'sometimes|string|max:255',
            'final_name'     => 'sometimes|string|max:255',
            'cnpj'           => 'sometimes|string|unique:companies,cnpj,' . $company->id,
            'phone'          => 'nullable|string',
            'address'        => 'nullable|string',
            'plan'           => 'nullable|string',
            'admin.email'    => 'sometimes|email|unique:users,email,' . ($company->admin->id ?? 'null'),
            'admin.name'     => 'sometimes|string|max:255',
            'admin.password' => ['nullable', 'string', 'min:6', function ($attribute, $value, $fail) {
                if (trim($value) === '') {
                    $fail('A senha não pode estar em branco.');
                }
            }]
        ], [
            'legal_name.max'             => 'O nome empresarial não pode ter mais de :max caracteres.',
            'final_name.max'             => 'O nome real da loja não pode ter mais de :max caracteres.',
            'cnpj.unique'                => 'Este CNPJ já está em uso por outra empresa.',
            'admin.email.email'          => 'Informe um e-mail válido.',
            'admin.email.unique'         => 'Este e-mail já está sendo utilizado.',
            'admin.name.max'             => 'O nome do administrador não pode ter mais de :max caracteres.',
            'admin.password.min'         => 'A nova senha deve ter pelo menos :min caracteres.'
        ]);

        $company->update($request->only([
            'legal_name', 
            'final_name', 
            'cnpj', 
            'phone', 
            'address', 
            'plan'
        ]));

        if ($request->has('admin') && $company->admin) {
            $admin = $company->admin;
            $admin->fill([
                'name'  => $request->admin['name'] ?? $admin->name,
                'email' => $request->admin['email'] ?? $admin->email
            ]);

            if (!empty($request->admin['password'])) {
                $admin->password = bcrypt($request->admin['password']);
            }

            $admin->save();
        }
        
        return response()->json(['company' => $company]);
    }

    public function destroy(Company $company)
    {
        $company->admin()->delete();

        $company->delete();
        
        return response()->json(['message' => 'Empresa excluída com sucesso.']);
    }
    
    public function companies()
    {
        $companies = Company::with(['products' => function ($query) {
            $query->where('stock_quantity', '>', 0)
                ->where('status', 'ativo')
                ->with(['images', 'variations', 'category']);
        }])
        ->where('active', true)
        ->whereHas('products', function ($query) {
            $query->where('stock_quantity', '>', 0)
                ->where('status', 'ativo');
        })
        ->get();
        
        return response()->json($companies);
    }
}
