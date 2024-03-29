<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

class UserController extends Controller
{
    public function show($id)
    {
      $user = User::find($id);

      return view('pages.user', ['user' => $user]);
    }

    public static function returnUser($id)
    {
      $user = User::find($id);
      return $user;
    }

    public function list()
    {
      $users = User::all();
      return view('pages.usersListing', ['users' => $users]);
    }

    public function showEditForm($id)
    {
      $user = User::find($id);
      try {
        $this->authorize('edit', $user);
    } catch (AuthorizationException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
    }

      return view('pages.editUser', ['id' => $id]);
    }

    public function edit(Request $request, $id)
    {
      $user = User::find($id);
      $user->name = $request->input('name');
      $user->email = $request->input('email');
      $user->username = $request->input('username');
      $user->password = bcrypt($request->input('password'));
      $user->description = $request->input('description');


      try {
        $this->authorize('edit', $user);
        $user->update();
    } catch (AuthorizationException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
    }
      catch (QueryException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
      }

      return redirect('users/'.$id);
    }

    public function resetPassword(Request $request)
    {
      $user = User::where('token', $request->input('token'))->first();

      if ($user) {
        $user->password = bcrypt($request->input('password'));
        $user->token = null;
        $user->save();
        Auth::login($user);
        return redirect(route('home'));
      }
      else {
        return redirect(route('home'))->with('error', 'Invalid token.');
      }
    }

    public function delete($id, Request $request)
    {
      $user = User::find($id);
      $user->username = 'deleted_' . $user->id;
      $user->name = 'deleted';
      $user->email = 'deleted_' . $user->id;
      $user->description = 'deleted';
      $user->password = 'deleted';
      $user->img = 'deleted';
      try {
        $this->authorize('deleteUser', $user);;
        $user->save();
    } 
    catch (AuthorizationException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
    }
      catch (QueryException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
      }
      // if($request->user()->id == $id){
      //   Auth::logout();
      // }

      return redirect(route('home'));
    }


    // do rate function that takes the user id and the rating and adds rating to the mean
    public function rate(Request $request, $id)
    {
      $user = User::find($id);
      $user->rate = ($user->rate * $user->rate_count + $request->input('rate')) / ($user->rate_count + 1);
      $user->rate_count = $user->rate_count + 1;
      $user->save();
      return redirect('users/'.$id);
    }

    public function listBlockedUsers()
    {
        $blockedUsers = User::where('blocked', true)->get();

        return view('pages.blockedListing', ['blockedUsers' => $blockedUsers]);
    }

    public function block(Request $request, $id)
    {

        $user = $request->user();

        // Find the user to be blocked by their ID
        $blocked = User::find($id);

        // Check if the authenticated user is authorized to perform the block action
        try {

            $this->authorize('block', $user);

            // Update the 'blocked' attribute for the user
            $blocked->blocked = true;
            $blocked->save();

        } catch (AuthorizationException $e) {
            return back()->with('error', 'You are not authorized to perform this action.');
        } catch (QueryException $e) {
            return back()->with('error', 'An error occurred while updating the user status.');
        }


        return redirect(route('home'));
    }

    public function unblock(Request $request, $id)
    {

        $user = $request->user();

        // Find the user to be blocked by their ID
        $blocked = User::find($id);

        // Check if the authenticated user is authorized to perform the block action
        try {

            $this->authorize('block', $user);

            // Update the 'blocked' attribute for the user
            $blocked->blocked = false;
            $blocked->save();

        } catch (AuthorizationException $e) {
            return back()->with('error', 'You are not authorized to perform this action.');
        } catch (QueryException $e) {
            return back()->with('error', 'An error occurred while updating the user status.');
        }

        

        return back();
    }

    public function addCreditForm($id)
    {
      Log::info('Inside addCreditForm method.');
      $user = User::find($id);
      try {
        $this->authorize('addCredit', $user);
    } catch (AuthorizationException $e) {
        return back()->with('error', 'You are not authorized to perform this action.');
    }

      return view('pages.addCredit', ['user' => $user]);
    }

    public function addCredit(Request $request)
    {
        $user = Auth::user();

        // Validate the request
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        // Add credit to the user's balance
        $user->credit += $request->amount;
        $user->save();

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Credit added successfully!');
    }
}
