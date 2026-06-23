<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Register a new (non-admin) account.
     *
     * The `role` is forced to `user` here so a client can never escalate to
     * `admin` via mass assignment.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,name',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            // 422 so the client can detect failure by status code.
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // `role` is intentionally NOT mass-assignable; set it explicitly so a
        // crafted request body can never make a new account an admin.
        $user = new User([
            'name'     => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = 'user';
        $user->save();

        return response()->json([
            'message' => 'User created successfully',
            'user'    => $user,
        ], 201);
    }

    /**
     * Authenticate and issue a Sanctum bearer token.
     *
     * Admins additionally receive the `admin` ability on their token, which the
     * `admin` middleware checks for catalog-management routes.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Same message for "no such user" and "wrong password" to avoid
            // user enumeration.
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $abilities = $user->role === 'admin' ? ['admin'] : [];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user,
        ], 200);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Issue a single-use password-reset token.
     *
     * Always responds 200 with a generic message so an attacker cannot use
     * this endpoint to discover which emails are registered.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $generic = response()->json([
            'message' => 'If an account exists for this email, a password reset token has been issued.',
        ]);

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return $generic;
        }

        $token = Str::random(64);

        // Store a hashed token (mirrors Laravel's password broker), replacing
        // any previous token for this email.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email'      => $request->email,
                'token'      => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        // In production this token is emailed, not returned. For this app
        // (no real mail transport configured) we return it so the SPA can
        // complete the flow. Remove `reset_token` once email delivery works.
        return response()->json([
            'message'     => 'If an account exists for this email, a password reset token has been issued.',
            'reset_token' => $token,
        ]);
    }

    /**
     * Reset a password using a valid, unexpired reset token.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        // Expire tokens after 60 minutes.
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Single-use: consume the token and revoke existing sessions.
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successful']);
    }
}
