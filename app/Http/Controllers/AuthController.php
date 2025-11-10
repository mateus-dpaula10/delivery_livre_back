<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('addresses');
        return response()->json($user);
    }

    public function login(Request $request) 
    {
        $credentials = $request->only('email', 'password');

        if (!auth()->attempt($credentials)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $user = auth()->user();

        $token = $user->createToken('app_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ]);
    }

    public function register(Request $request) 
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed'
        ], [
            'name.required'     => 'O nome é obrigatório.',
            'email.required'    => 'O e-mail é obrigatório.',
            'email.email'       => 'Formato de e-mail inválido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min'      => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed'=> 'A confirmação da senha não confere.'
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password)
        ]);

        $token = $user->createToken('app_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ], 201);
    }

    public function destroy($id)
    {
        $authUser = auth()->user();
        $address = $authUser->addresses()->find($id);

        if (!$address) {
            return response()->json(['message' => 'Endereço não encontrado'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Endereço removido com sucesso']);
    }

    public function update(Request $request)
    {
        $authUser = auth()->user();

        $request->validate([
            'name'                     => 'required|string|max:255',
            'email'                    => 'required|email|unique:users,email,' . $authUser->id,
            'password'                 => 'nullable|min:6|confirmed',
            'photo'                    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'addresses'                => 'nullable|array',
            'addresses.*.label'        => 'nullable|string|max:255',
            'addresses.*.cep'          => 'nullable|string|size:8',
            'addresses.*.street'       => 'nullable|string|max:255',
            'addresses.*.neighborhood' => 'nullable|string|max:255',
            'addresses.*.city'         => 'nullable|string|max:255',
            'addresses.*.state'        => 'nullable|string|max:2',
            'addresses.*.number'       => 'nullable|string|max:50',
            'addresses.*.complement'   => 'nullable|string|max:255',
            'addresses.*.note'         => 'nullable|string|max:500'
        ], [
            'name.required'      => 'O nome é obrigatório.',
            'email.required'     => 'O e-mail é obrigatório.',
            'email.email'        => 'Informe um e-mail válido.',
            'email.unique'       => 'Este e-mail já está sendo usado.',
            'password.min'       => 'A senha deve conter pelo menos 6 caracteres.',
            'password.confirmed' => 'As senhas não coincidem.',
            'photo.image'        => 'O arquivo deve ser uma imagem.',
            'photo.mimes'        => 'A imagem deve ser JPG, JPEG ou PNG.',
            'photo.max'          => 'A imagem não pode ter mais de 2MB.',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('users', 'public');
            $authUser->photo = $path;
        }

        $data = $request->only('name', 'email');

        if ($request->filled('password')) {
            $authUser->password = bcrypt($request->password);
        }

        $authUser->fill($request->only('name', 'email'));
        $authUser->save();

        if ($request->has('addresses')) {
            foreach ($request->addresses as $addrData) {
                if (isset($addrData['id'])) {
                    $address = $authUser->addresses()->find($addrData['id']);
                    if ($address) $address->update($addrData);
                } else {                    
                    $authUser->addresses()->create($addrData);
                }
            }
        }

        return response()->json([
            'user' => $authUser->load('addresses')
        ]);
    }
}
