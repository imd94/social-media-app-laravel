<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;

class UserController extends Controller
{
  public function storeAvatar(Request $request) {
    //$request->file('avatar')->store('avatars', 'public');

    $request->validate([
      'avatar' => 'required|image|max:40960' // 40960kb = 40mb
    ]);

    $user = auth()->user();

    $filename = $user->id . '-' . uniqid() . '.jpg';

    $manager = new ImageManager(new Driver());
    $image = $manager->read($request->file("avatar"));
    $imgData = $image->cover(120, 120)->toJpeg();
    Storage::put("public/avatars/" . $filename, $imgData);

    $oldAvatar = $user->avatar;

    $user->avatar = $filename; // save avatar in the database
    /** @var \App\Models\User $user */
    $user->save(); // actually save

    if($oldAvatar != '/fallback-avatar.jpg') {
      Storage::disk('public')->delete(str_replace('/storage/', '', $oldAvatar));
    }

    return back()->with('success', 'New avatar has been applied.');
  }

  public function showAvatarForm() {
    return view('avatar-form');
  }

  public function profile(User $user) {
    //dd(PHP_BINARY);
    $posts = $user->posts()->latest()->get();
    return view('profile-posts', ['username' => $user->username, 'avatar' => $user->avatar, 'posts' => $posts, 'postCount' => $user->posts()->count()]);
  }

  public function logout() {
    auth()->logout();
    return redirect('/')->with('success', 'You are now logged out.');
  }

  public function showCorrectHomepage() {
    if(auth()->check()) {
      return view('homepage-feed');
    } else {
      return view('homepage');
    }
  }

  public function login(Request $request) {
    $incomingFields = $request->validate([
      'loginusername' => [
        'required'
      ],
      'loginpassword' => [
        'required'
      ]
    ]);

    if(auth()->attempt([
      'username' => $incomingFields['loginusername'], 
      'password' => $incomingFields['loginpassword']
    ])) {
      $request->session()->regenerate();
      return redirect('/')->with('success', 'You have successfully logged in.');
    } else {
      return redirect('/')->with('danger', 'Invalid login.');
    }
  }

  public function register(Request $request) {
    $incomingFields = $request->validate([
      'username' => [
        'required',
        'min:3',
        'max:20',
        Rule::unique('users', 'username') // username must be unique
      ],
      'email' => [
        'required',
        'email',
        Rule::unique('users', 'email') // email must be unique
      ],
      'password' => [
        'required',
        'min:8',
        'confirmed'
      ],
    ]);

    $user = User::create($incomingFields);

    auth()->login($user);

    return redirect('/')->with('success', 'Thank you for creating an account.');
  }
}
