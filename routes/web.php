<?php


use App\Http\Controllers\Admin\ManageUsersController;
use App\Http\Controllers\Admin\DatabaseToolController;
use App\Http\Controllers\Admin\OperationalAuditController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\AiBotController;
use App\Http\Controllers\AiConversationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\AutoreplyController;
use App\Http\Controllers\BlastController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\MessagesHistoryController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RestapiController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShowMessageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redirect;


require_once 'custom-route.php';
Route::get('/', function()
{
    return Redirect::to( '/login');
    // OR: return Redirect::intended('/bands'); // if using authentication
});
Route::get('/language/{locale}', [LocaleController::class, 'switch'])->name('language.switch');
Route::middleware(['auth', 'approved'])->group(function (){

    Route::get('/home',[HomeController::class,'index'])->name('home');
    Route::post('/home/setSessionSelectedDevice',[HomeController::class,'setSelectedDeviceSession'])->name('home.setSessionSelectedDevice');
    Route::post('/home/sethook',[HomeController::class,'setHook'])->name('setHook');
    Route::post('/home/sethookread',[HomeController::class,'setHookRead'])->name('setHookRead');
    Route::post('/home/sethookreject',[HomeController::class,'setHookReject'])->name('setHookReject');
    Route::post('/home/setavailable',[HomeController::class,'setAvailable'])->name('setAvailable');
    Route::post('/home/sethooktyping',[HomeController::class,'setHookTyping'])->name('setHookTyping');
    Route::post('/home',[HomeController::class,'store'])->name('addDevice');
    Route::delete('/home',[HomeController::class,'destroy'])->name('deleteDevice');
    Route::get('/file-manager',[FileManagerController::class,'index'])->name('file-manager');

    Route::get('/scan/{number:body}',[ScanController::class,'scan'])->name('scan');
    Route::get('/code/{number:body}',[ScanController::class,'code'])->name('connect-via-code');

    Route::get('/autoreply',[AutoreplyController::class,'index'])->name('autoreply');
    Route::get('/autoreply/create',[AutoreplyController::class,'create'])->name('autoreply.create');
    Route::post('/autoreply',[AutoreplyController::class,'store'])->name('autoreply.store');
    Route::get('/autoreply/{autoreply:id}/edit',[AutoreplyController::class,'edit'])->name('autoreply.edit');
    Route::put('/autoreply/{autoreply:id}',[AutoreplyController::class,'update'])->name('autoreply.update');
    Route::delete('/autoreply/{autoreply:id}',[AutoreplyController::class,'destroy'])->name('autoreply.delete');
    Route::post('/autoreply/{autoreply:id}/duplicate',[AutoreplyController::class,'duplicate'])->name('autoreply.duplicate');
    Route::post('/autoreply/{autoreply:id}/toggle-status',[AutoreplyController::class,'toggleStatus'])->name('autoreply.toggle-status');
    Route::post('/autoreply/simulate',[AutoreplyController::class,'simulate'])->middleware('throttle:preview-message')->name('autoreply.simulate');

    Route::get('/ai-bots',[AiBotController::class,'index'])->name('ai-bots.index');
    Route::get('/ai-bots/create',[AiBotController::class,'create'])->name('ai-bots.create');
    Route::post('/ai-bots',[AiBotController::class,'store'])->name('ai-bots.store');
    Route::get('/ai-bots/{aiBot:id}/edit',[AiBotController::class,'edit'])->name('ai-bots.edit');
    Route::put('/ai-bots/{aiBot:id}',[AiBotController::class,'update'])->name('ai-bots.update');
    Route::delete('/ai-bots/{aiBot:id}',[AiBotController::class,'destroy'])->name('ai-bots.delete');
    Route::post('/ai-bots/{aiBot:id}/duplicate',[AiBotController::class,'duplicate'])->name('ai-bots.duplicate');
    Route::get('/ai-conversations',[AiConversationController::class,'index'])->name('ai-conversations.index');
    Route::post('/ai-conversations/{conversation:id}/pause',[AiConversationController::class,'pause'])->name('ai-conversations.pause');
    Route::post('/ai-conversations/{conversation:id}/resume',[AiConversationController::class,'resume'])->name('ai-conversations.resume');

    
    
    Route::get('/phonebook',[TagController::class,'index'])->name('phonebook');
    Route::get('/get-phonebook',[TagController::class,'getPhonebook'])->name('getPhonebook');
    Route::delete('/clear-phonebook',[TagController::class,'clearPhonebook'])->name('clearPhonebook');
    Route::get('get-contact/{id}',[ContactController::class,'getContactByTagId']);
    Route::post('/contact/store',[ContactController::class,'store'])->name('contact.store');
    Route::delete('/contact/delete/{contact:id}',[ContactController::class,'destroy'])->name('contact.delete');
    Route::delete('/contact/delete-all/{id}',[ContactController::class,'DestroyAll'])->name('deleteAll');
    Route::post('/contact/import',[ContactController::class,'import'])->middleware('throttle:import-contacts')->name('import');
    Route::get('/contact/export/{id}',[ContactController::class,'export'])->name('exportContact');

  Route::post('/tags',[TagController::class,'store'])->name('tag.store');
  Route::delete('/tags',[TagController::class,'destroy'])->name('tag.delete');
  Route::post('fetch-groups',[TagController::class ,'fetchGroups'])->name('fetch.groups');

  Route::get('/campaigns',[CampaignController::class,'index'])->name('campaigns');
  Route::get('/campaign/create',[CampaignController::class,'create'])->name('campaign.create');
  Route::post('/campaign/store',[CampaignController::class,'store'])->name('campaign.store');
  Route::get('/get-phonebook-list',[CampaignController::class,'getPhonebookList'])->name('getPhonebookList');
  Route::post('/campaign/pause/{id}',[CampaignController::class,'pause'])->name('campaign.pause');
  Route::post('/campaign/resume/{id}',[CampaignController::class,'resume'])->name('campaign.resume');
  Route::delete('/campaign/delete/{id}',[CampaignController::class,'destroy'])->name('campaign.delete');
  Route::get('/campaign/show/{id}',[CampaignController::class,'show'])->name('campaign.show');
  Route::delete('/campaign/clear',[CampaignController::class,'destroyAll'])->name('campaigns.delete.all');
  Route::get('/campaign/blast/{campaign:id}',[BlastController::class,'index'])->name('campaign.blasts');

  Route::post('/preview-message',[ShowMessageController::class,'index'])->middleware('throttle:preview-message')->name('previewMessage');
  Route::get('/form-message/{type}',[ShowMessageController::class,'getFormByType'])->name('formMessage');
  


  Route::get('/message/test',[MessagesController::class,'index'])->name('messagetest');
  Route::post('/message/test',[MessagesController::class,'store'])->middleware('throttle:message-send')->name('messagetest');

  Route::get('/api-docs',RestapiController::class)->name('rest-api');

  Route::get('/user/settings',[UserController::class,'settings'])->name('user.settings');
  Route::post('/user/change-password',[UserController::class,'changePasswordPost'])->name('changePassword');
  Route::post('/user/setting/apikey',[UserController::class,'generateNewApiKey'])->name('generateNewApiKey');

  
  Route::get('/admin/settings',[SettingController::class,'index'])->name('admin.settings')->middleware('admin');
  Route::post('/settings/server',[SettingController::class,'setServer'])->name('setServer')->middleware('admin');
  Route::post('/settings/history-cleanup',[SettingController::class,'setHistoryCleanup'])->name('settings.history-cleanup')->middleware('admin');
  Route::post('/settings/history-cleanup/run',[SettingController::class,'runHistoryCleanup'])->name('settings.history-cleanup.run')->middleware('admin');
  Route::post('/settings/ai-bot',[SettingController::class,'setAiBotSettings'])->name('settings.ai-bot')->middleware('admin');
  Route::get('/admin/update',[UpdateController::class,'index'])->name('admin.update')->middleware('admin');
  Route::post('/admin/update/sync',[UpdateController::class,'sync'])->name('admin.update.sync')->middleware('admin');
  Route::get('/admin/operational-audit',[OperationalAuditController::class,'index'])->name('admin.operational-audit')->middleware('admin');
  Route::get('/admin/database-tools',[DatabaseToolController::class,'index'])->name('admin.database-tools')->middleware('admin');
  Route::post('/admin/database-tools/backup',[DatabaseToolController::class,'createBackup'])->name('admin.database-tools.backup')->middleware('admin');
  Route::post('/admin/database-tools/restore',[DatabaseToolController::class,'restore'])->name('admin.database-tools.restore')->middleware('admin');
  Route::get('/admin/database-tools/download',[DatabaseToolController::class,'download'])->name('admin.database-tools.download')->middleware('admin');
  Route::delete('/admin/database-tools/delete',[DatabaseToolController::class,'delete'])->name('admin.database-tools.delete')->middleware('admin');


  Route::get('/admin/manage-users',[ManageUsersController::class,'index'])->name('admin.manage-users')->middleware('admin');
  Route::post('/admin/user/store',[ManageUsersController::class,'store'])->name('user.store')->middleware('admin');
  Route::delete('/admin/user/delete/{id}',[ManageUsersController::class,'delete'])->name('user.delete')->middleware('admin');
  Route::get('admin/user/edit',[ManageUsersController::class,'edit'])->name('user.edit')->middleware('admin');
  Route::post('admin/user/update',[ManageUsersController::class,'update'])->name('user.update')->middleware('admin');

  Route::get('/messages-history',[MessagesHistoryController::class,'index'])->name('messages.history');
  Route::post('/resend-message',[MessagesHistoryController::class,'resend'])->middleware('throttle:message-send')->name('resend.message');
  Route::post('/messages-history/resend-failed',[MessagesHistoryController::class,'resendFailed'])->middleware('throttle:message-send')->name('messages.history.resend-failed');
  Route::delete('/messages-history/{messageHistory:id}',[MessagesHistoryController::class,'destroy'])->name('messages.history.delete');
  Route::delete('/messages-history',[MessagesHistoryController::class,'clear'])->name('messages.history.clear');


    Route::post('/logout', LogoutController::class)->name('logout');

  

});

Route::middleware('guest')->group(function(){

  Route::get('/login',[LoginController::class,'index'])->name('login');
    Route::get('/register',[RegisterController::class,'index'])->name('register');
    Route::post('/register',[RegisterController::class,'store'])->name('register');
    Route::post('/login',[LoginController::class,'store'])->name('login')->middleware('throttle:5,1');
});
Route::get('/install', [SettingController::class,'install'])->name('setting.install_app');
Route::post('/install', [SettingController::class,'install'])->name('settings.install_app');

Route::post('/settings/check_database_connection',[SettingController::class,'test_database_connection'])->name('connectDB');
Route::post('/settings/activate_license',[SettingController::class,'activate_license'])->name('activateLicense');


