<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    //Find All users
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    //Find user by id
    public function show($id)
    {
        $user = User::find($id);
        return response()->json($user);
    }

    //Create user
    public function store(Request $request)
    {
        $user = User::create($request->all());
        return response()->json($user, 201);
    }

    //Update user
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        $user->update($request->all());
        return response()->json($user, 200);
    }

    //Delete user
    public function destroy($id)
    {
        User::destroy($id);
        return response()->json(null, 204);
    }

    //Restore user
    public function restore($id)
    {
        User::withTrashed()->find($id)->restore();
        return response()->json(null, 200);
    }
}


