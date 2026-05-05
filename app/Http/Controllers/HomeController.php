<?php


namespace App\Http\Controllers;
use App\Services\OperationalHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller
{
    

    public function index(Request $request, OperationalHealthService $healthService){
        try {
            $numbers = $request->user()->devices()->latest()->paginate(15);

            $user = $request->user()->withCount(['devices','campaigns'])->
            withCount(['blasts as blasts_pending' => function($q){
                return $q->where('status', 'pending');
            }])->withCount(['blasts as blasts_success' => function($q){
                return $q->where('status', 'success');
            }])->withCount(['blasts as blasts_failed' => function($q){
                return $q->where('status', 'failed');
            }])->withCount('messageHistories')->find($request->user()->id);

            $user['expired_subscription_status'] = $user->expiredSubscription;
            $user['subscription_status'] = $user->isExpiredSubscription ? 'Expired' : $user->active_subscription;
            $selectedDevice = null;
            if (session()->has('selectedDevice')) {
                $selectedDevice = $request->user()->devices()->find(session()->get('selectedDevice')['device_id']);
            }

            $operational = $healthService->buildDashboardSummary($request->user(), $selectedDevice);
        } catch (\Throwable $th) {
            Log::error('Home dashboard failed, using fallback payload.', [
                'message' => $th->getMessage(),
            ]);

            $numbers = $request->user()->devices()->latest()->paginate(15);
            $user = $request->user()->loadCount(['devices', 'campaigns', 'messageHistories', 'blasts as blasts_pending' => function ($q) {
                return $q->where('status', 'pending');
            }, 'blasts as blasts_success' => function ($q) {
                return $q->where('status', 'success');
            }, 'blasts as blasts_failed' => function ($q) {
                return $q->where('status', 'failed');
            }]);
            $user['expired_subscription_status'] = $user->expiredSubscription;
            $user['subscription_status'] = $user->isExpiredSubscription ? 'Expired' : $user->active_subscription;
            $operational = [
                'health' => [],
                'metrics' => [
                    'incoming_active_chats_today' => 0,
                    'incoming_messages_tracked_today' => 0,
                    'auto_reply_success_today' => 0,
                    'auto_reply_failed_today' => 0,
                    'ai_fallback_today' => 0,
                    'paused_conversations' => 0,
                ],
                'alerts' => [[
                    'level' => 'warning',
                    'title' => 'Dashboard sedang dalam mode aman',
                    'message' => 'Ada komponen operasional yang gagal dimuat, tetapi login tetap aman. Silakan cek log server.',
                ]],
                'setup' => [],
            ];
        }

        return view('home',compact('numbers','user', 'operational'));
    }

    public function store(Request $request){
       $validate =  validator($request->all(),[
            'sender' => ['required', 'regex:/^[0-9]{8,15}$/', 'unique:devices,body'],
            'urlwebhook' => ['nullable', 'url', 'max:2048'],
        ]);

        if($request->user()->isExpiredSubscription){
            return back()->with('alert',['type' => 'danger','msg' => 'Your subscription has expired, please renew your subscription.']);
        }
        if($validate->fails()){
            return back()->with('alert',['type' => 'danger','msg' => $validate->errors()->first()]);
        }

       if($request->user()->limit_device <= $request->user()->devices()->count() ){
            return back()->with('alert',['type' => 'danger','msg' => 'You have reached the limit of devices!']);
        }
        $request->user()->devices()->create(['body' => $request->sender,'webhook' => $request->urlwebhook]);
        return back()->with('alert',['type' => 'success','msg' => 'Devices Added!']);
    }


    public function destroy(Request $request){
        try {
             $device = $request->user()->devices()->find($request->deviceId);
             if (!$device) {
                return back()->with('alert',['type' => 'danger','msg' => 'Device not found!']);
             }

            $device->delete();
            Session::forget('selectedDevice');
            if (!empty($device->body)) {
                $path = getNodeCredentialPath($device->body);
                if(file_exists($path)){
                    File::deleteDirectory($path);
                }
            }
            return back()->with('alert',['type' => 'success','msg' => 'Devices Deleted!']);
        } catch (\Throwable $th) {
            return back()->with('alert',['type' => 'danger','msg' => 'Something went wrong!']);
        }
    }


    public function setHook(Request $request){
      $request->validate([
        'number' => ['required'],
        'webhook' => ['nullable', 'url', 'max:2048'],
      ]);

      clearCacheNode();
      $updated = $request->user()->devices()->whereBody($request->number)->update(['webhook' => $request->webhook]);

      return response()->json(['status' => (bool) $updated]);
    }

    public function setHookRead(Request $request)
    {
        return $this->updateDevicePreference($request, 'webhook_read', 'Read status updated');
    }

    public function setHookReject(Request $request)
    {
        return $this->updateDevicePreference($request, 'webhook_reject_call', 'Reject call status updated');
    }

    public function setAvailable(Request $request)
    {
        return $this->updateDevicePreference($request, 'set_available', 'Online availability updated');
    }

    public function setHookTyping(Request $request)
    {
        return $this->updateDevicePreference($request, 'webhook_typing', 'Typing webhook status updated');
    }

    protected function updateDevicePreference(Request $request, string $field, string $message)
    {
        $allowedFields = [
            'webhook_read',
            'webhook_reject_call',
            'set_available',
            'webhook_typing',
        ];

        abort_unless(in_array($field, $allowedFields, true), 404);

        $request->validate([
            'id' => ['required'],
            $field => ['required'],
        ]);

        $value = filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN);
        $updated = $request->user()->devices()->whereBody($request->id)->update([$field => $value]);

        return response()->json([
            'error' => !$updated,
            'msg' => $updated ? $message : 'Device not found',
        ]);
    }


    public function setSelectedDeviceSession(Request $request){
        $device = $request->user()->devices()->whereId($request->device)->first();
        if(!$device){
            Session::forget('selectedDevice');
            return response()->json(['error' => true, 'msg' => 'Device not found!']);
        }
        session()->put('selectedDevice', [
            'device_id' => $device->id,
            'device_body' => $device->body,
        ]);
        return response()->json(['error' => false, 'msg' => 'Device selected!']);
    }


    


    

}
