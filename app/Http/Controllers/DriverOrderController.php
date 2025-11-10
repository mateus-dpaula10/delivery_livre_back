<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Driver;
use App\Models\User;
use Carbon\Carbon;

class DriverOrderController extends Controller
{
    public function generatePixCode(string $id)
    {
        $user = auth()->user();
        $order = Order::findOrFail($id);

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $store = $order->store;

        if (!$store->pix_key) {
            return response()->json(['message' => 'Loja não possui chave PIX cadastrada'], 400);
        }

        $amount = number_format($order->total, 2, '.', '');
        $merchantName = mb_substr($store->final_name, 0, 25);

        $addressParts = array_filter([
            $store->street,
            $store->number,
            $store->neighborhood,
            $store->city,
            $store->state,
            $store->cep,
        ]);

        $fullAddress = implode(', ', $addressParts);
        $state = strtoupper($store->state ?? ''); 
        $addressField = strtoupper($fullAddress);

        $buildPixField = function ($id, $value) {
            $len = strlen($value);
            return $id . str_pad($len, 2, '0', STR_PAD_LEFT) . $value;
        };

        $merchantAccountInfo =
            $buildPixField('00', 'BR.GOV.BCB.PIX') .
            $buildPixField('01', $store->pix_key);

        $payload =
            $buildPixField('00', '01') .
            $buildPixField('26', $merchantAccountInfo) .
            $buildPixField('52', '0000') .
            $buildPixField('53', '986') .
            $buildPixField('54', $amount) .
            $buildPixField('58', 'BR') .
            $buildPixField('59', strtoupper($merchantName)) .
            $buildPixField('60', $state) .
            $buildPixField('61', $addressField) .
            $buildPixField('62', $buildPixField('05', $order->code));

        $crcInput = $payload . '6304';
        $crc = strtoupper(dechex($this->crc16($crcInput)));
        $crc = str_pad($crc, 4, '0', STR_PAD_LEFT);

        $pixCode = $payload . '6304' . $crc;
        $expiresAt = now()->addMinutes(10)->timestamp;

        return response()->json([
            'pix_code'  => $pixCode,
            'expira_em' => $expiresAt
        ]);
    }

    private function crc16($string)
    {
        $poly = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($string); $i++) {
            $crc ^= (ord($string[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) != 0) {
                    $crc = ($crc << 1) ^ $poly;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }

        return $crc;
    }

    public function index() 
    {
        $companyId = auth()->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'Loja não identificada para este usuário.'], 403);
        }

        $drivers = Driver::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return response()->json($drivers);
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->company_id;
        dd($companyId);

        if (!$companyId) {
            return response()->json(['message' => 'Loja não identificada para este usuário.'], 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:20',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'vehicle'  => 'nullable|string|max:100',
            'plate'    => 'nullable|string|max:20',
            'status'   => 'in:ativo,inativo'
        ], [
            'name.required'     => 'O nome do motorista é obrigatório.',
            'name.max'          => 'O nome do motorista não pode ultrapassar 255 caracteres.',
            'phone.required'    => 'O telefone do motorista é obrigatório.',
            'phone.max'         => 'O telefone não pode ultrapassar 20 caracteres.',
            'email.required'    => 'O e-mail é obrigatório.',
            'email.email'       => 'Digite um e-mail válido.',
            'email.unique'      => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.min'      => 'A senha deve ter pelo menos 6 caracteres.',
            'vehicle.max'       => 'O campo veículo não pode ultrapassar 100 caracteres.',
            'plate.max'         => 'A placa não pode ultrapassar 20 caracteres.',
            'status.in'         => 'O status deve ser "ativo" ou "inativo".'
        ]);        

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => bcrypt($validated['password']),
            'company_id' => $companyId,
            'role'       => 'driver'
        ]);

        $driver = Driver::create([
            'user_id'    => $user->id,
            'company_id' => $companyId,
            'phone'      => $validated['phone'],
            'vehicle'    => $validated['vehicle'] ?? null,
            'plate'      => $validated['plate'] ?? null,
            'status'     => $validated['status'] ?? 'ativo'
        ]);

        return response()->json([
            'message' => 'Motorista cadastrado com sucesso!',
            'data' => [
                'user'   => $user,
                'driver' => $driver
            ] 
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = auth()->user()->company_id;

        if (!$companyId) {
            return response()->json(['message' => 'Loja não identificada para este usuário.'], 403);
        }

        $driver = Driver::where('company_id', $companyId)->find($id);

        if (!$driver) {
            return response()->json(['message' => 'Motorista não encontrado.'], 404);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20',
            'vehicle' => 'nullable|string|max:100',
            'plate'   => 'nullable|string|max:20',
            'status'  => 'in:ativo,inativo'
        ], [
            'name.required'   => 'O nome do motorista é obrigatório.',
            'name.max'        => 'O nome do motorista não pode ultrapassar 255 caracteres.',
            'phone.required'  => 'O telefone do motorista é obrigatório.',
            'phone.max'       => 'O telefone não pode ultrapassar 20 caracteres.',
            'vehicle.max'     => 'O campo veículo não pode ultrapassar 100 caracteres.',
            'plate.max'       => 'A placa não pode ultrapassar 20 caracteres.',
            'status.in'       => 'O status deve ser "ativo" ou "inativo".'
        ]);

        $companyId = auth()->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'Loja não identificada para este usuário.'], 403);
        }

        $driver->update($validated);

        return response()->json([
            'message' => 'Motorista cadastrado com sucesso!',
            'data' => $driver
        ]);
    }
}
