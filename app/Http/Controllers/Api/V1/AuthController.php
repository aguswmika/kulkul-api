<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'username'    => 'required',
            'password'    => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 'validator',
                'message'   => $validator->errors()->all()
            ]);
        }
        try {
            DB::beginTransaction();
            $user = User::where('username', '=', $request->username)->first();

            if(!$user || !Hash::check($request->password, $user->password)){
                throw new Exception('The provided credentials are incorrect.');
            }
            $token = $user->createToken(env('AUTH_TOKEN'))->plainTextToken;
            DB::commit();
            return response()->json([
                'status'  => 'success',
                'data'    => $token
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function me(){
        return response()->json([
            'status'    => 'success',
            'data'      => Auth::user()
        ]);
    }
}
