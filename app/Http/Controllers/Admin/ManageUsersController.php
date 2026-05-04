<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ManageUsersController extends Controller
{
    public function index (){
         $users = User::query()
            ->orderByRaw("CASE WHEN level = 'user' AND status = 'inactive' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(10);
         $pendingApprovals = User::where('level', 'user')->where('status', 'inactive')->count();

         return view('pages.admin.manageusers', compact('users', 'pendingApprovals'));
    }

      public function store(Request $request){
        $request->validate([
            'username' => 'required|unique:users',
            'email' => 'required|unique:users',
            'password' => 'required',
            'limit_device' => 'required|numeric|max:10',
            'status' => 'required|in:active,inactive',
            'active_subscription' => 'required|',

        ]);

        $status = $request->status;
        $subscription = $status === 'active' ? $request->active_subscription : 'inactive';
        $subscriptionExpired = $subscription === 'active' ? $request->subscription_expired : null;

        if($status == 'active' && $subscription == 'active'){
            $request->validate([
               'subscription_expired' => 'required|date',
            ]);

            // subscription expired must be greater than today
            if($subscriptionExpired < date('Y-m-d')){
                return redirect()->back()->with('alert' , ['type' => 'danger', 'msg' => 'Subscription expired must be greater than today']);
            }
        }
         
        $user = new User();
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->api_key = Str::random(32);
        $user->chunk_blast = 0;
        $user->limit_device = $request->limit_device;
        $user->status = $status;
        $user->active_subscription = $subscription;
        $user->subscription_expired = $subscriptionExpired;
        $user->save();
        return redirect()->back()->with('alert', ['type' => 'success', 'msg' => 'User created successfully']);
         
    }

     public function edit(){
        $id = request()->id;
        $user = User::find($id);
        // return data user to ajax
       return json_encode($user);
    }
    public function update(Request $request){
        
        $request->validate([
            'username' => 'required|unique:users,username,'.$request->id,
            'email' => 'required|unique:users,email,'.$request->id,
            'limit_device' => 'required|numeric|max:10',
            'status' => 'required|in:active,inactive',
            'active_subscription' => 'required|',

        ]);
        $status = $request->status;
        $subscription = $status === 'active' ? $request->active_subscription : 'inactive';
        $subscriptionExpired = $subscription === 'active' ? $request->subscription_expired : null;

        if($status == 'active' && $subscription == 'active'){
            $request->validate([
               'subscription_expired' => 'required|date',
            ]);

            // subscription expired must be greater than today
            if($subscriptionExpired < date('Y-m-d')){
                return redirect()->back()->with('alert' , ['type' => 'danger', 'msg' => 'Subscription expired must be greater than today']);
            }
        }
       
        if($request->password != ''){
            $request->validate([
                'password' => 'min:6',
            ]);
        }
        $user = User::find($request->id);
        $user->username = $request->username;
        $user->email = $request->email;
        $user->password = $request->password != '' ? bcrypt($request->password) : $user->password;
        $user->limit_device = $request->limit_device;
        $user->status = $status;
        $user->active_subscription = $subscription;
        $user->subscription_expired = $subscriptionExpired;
        $user->save();
        return redirect()->back()->with('alert', ['type' => 'success', 'msg' => 'User updated successfully']);
    }

    public function delete($id){
        $user = User::find($id);
        if($user->level == 'admin'){
            return redirect()->back()->with('alert', ['type' => 'danger', 'msg' => 'You can not delete admin']);
        }
        
        $user->delete();
        return redirect()->back()->with('alert', ['type' => 'success', 'msg' => 'User deleted successfully']);
    }
}
