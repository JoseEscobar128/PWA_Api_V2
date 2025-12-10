<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // GET /api/users
    public function index()
    {
        try {
            $users = User::with('roles')->get();
            
            $usersData = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $usersData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to list users',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // POST /api/users
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'      => 'required|string|min:2|max:255',
                'last_name' => 'required|string|min:2|max:255',
                'email'     => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
                'password'  => 'required|min:8',
                'role'      => 'sometimes|string|exists:roles,name',
            ]);

            $validated['password'] = Hash::make($validated['password']);

            // Remove role from validated to avoid mass assignment error
            $roleName = $validated['role'] ?? null;
            unset($validated['role']);

            $user = User::create($validated);

            // Asignar rol si se envió
            if ($roleName) {
                $role = \App\Models\Role::where('name', $roleName)->first();
                if ($role) {
                    // Limitar a un solo rol: usar sync
                    $user->roles()->sync([$role->id]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->pluck('name'),
                ],
                'message' => 'User created'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // GET /api/users/{id}
    public function show($id)
    {
        try {
            $user = User::with('roles')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // PUT /api/users/{id}
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name'      => 'sometimes|string|min:2|max:255',
                'last_name' => 'sometimes|string|min:2|max:255',
                'email'     => 'sometimes|email|unique:users,email,' . $user->id . ',id,deleted_at,NULL',
                'password'  => 'sometimes|min:8',
                'role'      => 'sometimes|string|exists:roles,name',
            ]);

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            // Cambiar rol solo si el usuario autenticado es admin y se envió 'role'
            $roleName = $validated['role'] ?? null;
            unset($validated['role']);

            $user->update($validated);

            if ($roleName) {
                $authUser = $request->user();
                if ($authUser && $authUser->roles()->where('name', 'admin')->exists()) {
                    $role = \App\Models\Role::where('name', $roleName)->first();
                    if ($role) {
                        // Limitar a un solo rol: usar sync
                        $user->roles()->sync([$role->id]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'updated_at' => $user->updated_at,
                    'roles' => $user->roles->pluck('name'),
                ],
                'message' => 'User updated'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE /api/users/{id}
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'User deleted'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
