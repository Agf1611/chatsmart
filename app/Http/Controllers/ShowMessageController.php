<?php


namespace App\Http\Controllers;

use App\Services\AutoreplyRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShowMessageController extends Controller
{
    protected $ruleService;

    public function __construct(AutoreplyRuleService $ruleService)
    {
        $this->ruleService = $ruleService;
    }

    public function index(Request $request)
    {
        try {
            $table = $request->table;
            $column_message = $request->column;
            $data = DB::table($table)
                ->where('id', $request->id)
                ->first();
            $type = $data->type;
            // if not exists $data->keyword, fill keyword with name table
            $keyword = $data->keyword ?? 'Preview ' . $table;
            $message = ($data->$column_message);

            return $this->ruleService->renderPreview($keyword, $type, json_decode($message, true));
        } catch (\Throwable $th) {
            Log::error($th);
            return view('ajax.messages.emptyshow')->render();
        }
    }

    public function getFormByType($type, Request $request)
    {
        if ($request->ajax()) {
            return view('ajax.messages.form' . $type)->render();
        }
        return 'http request';
    }
}
