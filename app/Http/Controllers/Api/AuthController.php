<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Reenvía el código 2FA por correo
     */
    public function resend2FA(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $user = \App\Models\User::where('email', $request->email)->firstOrFail();
        $this->send2FA($user);
        return response()->json([
            'message' => 'Código 2FA reenviado a tu correo'
        ]);
    }

    /**
     * Verifica el código 2FA y entrega el token
     */
    public function verify2FA(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'code' => 'required|string',
            ]);

            $user = \App\Models\User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            $validCode = $user->twoFactorCodes()
                ->where('code', $request->code)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$validCode) {
                // Verificar si el código existe pero está expirado
                $expiredCode = $user->twoFactorCodes()
                    ->where('code', $request->code)
                    ->where('used', false)
                    ->where('expires_at', '<=', now())
                    ->first();

                if ($expiredCode) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Código expirado. Solicita uno nuevo con /resend-2fa'
                    ], 400);
                }

                // Verificar si el código fue usado
                $usedCode = $user->twoFactorCodes()
                    ->where('code', $request->code)
                    ->where('used', true)
                    ->first();

                if ($usedCode) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Código ya utilizado. Solicita uno nuevo con /resend-2fa'
                    ], 400);
                }

                // Código incorrecto
                return response()->json([
                    'success' => false,
                    'error' => 'Código inválido. Verifica que sea correcto.'
                ], 400);
            }

            $validCode->update(['used' => true]);
            $user->is_verified = true;
            $user->save();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Autenticado correctamente',
                'token' => $token,
                'is_verified' => true
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida',
                'errors' => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al verificar el código. Intenta de nuevo.'
            ], 500);
        }
    }

    /**
     * Envía el código 2FA por correo usando Resend
     * @return bool true si se envió exitosamente, false si falló
     */
    public function send2FA(User $user)
    {
        try {
            $code = $this->generate2FA($user);
            
            $html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
                    .content { padding: 40px 20px; text-align: center; }
                    .code-box { background-color: #f9f9f9; border: 2px solid #667eea; border-radius: 8px; padding: 30px; margin: 30px 0; }
                    .code-box p { margin: 0 0 10px 0; font-size: 14px; color: #666; }
                    .code { font-size: 48px; font-weight: bold; color: #667eea; letter-spacing: 5px; font-family: 'Courier New', monospace; }
                    .expiration { font-size: 12px; color: #999; margin-top: 15px; }
                    .footer { background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #ddd; }
                    .info { background-color: #f0f7ff; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; text-align: left; font-size: 13px; color: #333; border-radius: 4px; }
                    .button { background-color: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 20px 0; font-weight: 600; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Verificación de Seguridad</h1>
                    </div>
                    
                    <div class='content'>
                        <p style='font-size: 16px; color: #333; margin-bottom: 10px;'>¡Hola <strong>{$user->name}</strong>!</p>
                        <p style='font-size: 14px; color: #666; margin-bottom: 30px;'>Bienvenido a SnapPlace. Tu código de verificación es:</p>
                        
                        <div class='code-box'>
                            <p>CÓDIGO DE VERIFICACIÓN</p>
                            <div class='code'>$code</div>
                            <div class='expiration'>Este código expira en 10 minutos</div>
                        </div>
                        
                        <div class='info'>
                            <strong>🔒 Información importante:</strong><br>
                            • Nunca compartas este código con nadie<br>
                            • Nuestro equipo nunca te pedirá este código por mensaje<br>
                            • Si no solicitaste este código, ignora este correo
                        </div>
                        
                        <p style='font-size: 13px; color: #999; margin-top: 30px;'>¿Problemas? Si no recibiste el código, puedes solicitar uno nuevo desde la aplicación.</p>
                    </div>
                    
                    <div class='footer'>
                        <p>Este es un correo automático, por favor no respondas.<br>
                        © 2025 SnapPlace. Todos los derechos reservados.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $response = \Resend\Laravel\Facades\Resend::emails()->send([
                'from' => 'no-reply@pagina-prueba.com',
                'to' => $user->email,
                'subject' => 'Tu código de verificación - 2FA',
                'html' => $html,
            ]);
            
            // Log detallado de la respuesta
            \Log::info('Resend API Response', [
                'response_object' => get_class($response),
                'response_id' => $response->id ?? null,
                'response_properties' => (array)$response,
                'user_email' => $user->email
            ]);
            
            // Verificar si el envío fue exitoso - Resend devuelve un objeto con 'id' si es exitoso
            return !empty($response->id);
        } catch (\Exception $e) {
            \Log::error('Error sending 2FA email', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Genera y guarda el código 2FA para el usuario
     */
    public function generate2FA(User $user)
    {
        $code = rand(100000, 999999);
        $user->twoFactorCodes()->create([
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'used' => false,
        ]);
        return $code;
    }
    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|min:2|max:255',
                'last_name' => 'required|string|min:2|max:255',
                'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
                'password' => 'required|min:8|confirmed'
            ]);

            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);

            // Intentar enviar código 2FA
            $emailSent = $this->send2FA($user);
            
            if (!$emailSent) {
                // Si falla el envío de correo, eliminar el usuario registrado y retornar error
                $user->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo enviar el código de verificación. Intenta de nuevo.',
                    'error' => 'email_failed'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ],
                'message' => 'Usuario registrado. Código 2FA enviado a tu correo',
                'requires_2fa' => true
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
                'message' => 'Failed to register user',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:8',
                'recaptchaResponse' => 'required|string', // <--- nuevo
            ]);

            // Validar reCAPTCHA con Google
            $response = \Illuminate\Support\Facades\Http::asForm()->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret' => env('RECAPTCHA_SECRET'),
                    'response' => $data['recaptchaResponse'],
                ]
            );

            $body = $response->json();

            if (empty($body['success']) || $body['success'] !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'Captcha no válido'
                ], 422);
            }

            // Verificar usuario y contraseña
            $user = User::where('email', $data['email'])->first();
            if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Enviar código 2FA por correo
            $this->send2FA($user);

            return response()->json([
                'success' => true,
                'message' => 'Código 2FA enviado a tu correo',
                'requires_2fa' => true
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $ve->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to login',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ]);
    }
}
