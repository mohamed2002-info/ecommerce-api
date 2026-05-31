<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users,name',  // Change 'username' to 'name' here
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        // Now save the 'name' field (not 'username') in the database
        $user = User::create([
            'name' => $request->username,  // Ensure we are saving the 'name' field
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    public function login(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',  // Email is required and should be valid
            'password' => 'required|string',  // Password is required
        ]);

        // If validation fails, return errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // If user doesn't exist or password is incorrect, return error
        if (!$user || !Hash::check($request->password, $user->password)) {  // Check the password using Hash::check
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // If login is successful, return a success response (You can add token generation here if needed)
        return response()->json(['message' => 'Login successful', 'user' => $user], 200);
    }

    public function forgotPassword(Request $request)
    {
        // Validate the incoming email
        $request->validate([
            'email' => 'required|email|exists:users,email',  // Validate email exists in users table
        ]);

        // Respond with a success message if the email exists
        return response()->json([
            'message' => 'If the email exists, you will be redirected to the reset password page.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6',  // Ensure password is confirmed
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // If the user doesn't exist
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password reset successful']);
    }
}
