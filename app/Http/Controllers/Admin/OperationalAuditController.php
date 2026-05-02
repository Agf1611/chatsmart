<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OperationalHealthService;
use Illuminate\Http\Request;

class OperationalAuditController extends Controller
{
    protected $healthService;

    public function __construct(OperationalHealthService $healthService)
    {
        $this->middleware('admin');
        $this->healthService = $healthService;
    }

    public function index(Request $request)
    {
        $audit = $this->healthService->buildOperationalAudit(null);

        return view('pages.admin.operational-audit', $audit);
    }
}
