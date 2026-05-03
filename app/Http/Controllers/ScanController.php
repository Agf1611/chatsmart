<?php

namespace App\Http\Controllers;

use App\Models\Device;

class ScanController extends Controller
{
    private function buildSocketRuntime(): array
    {
        return [
            'serverType' => getEnvValue('TYPE_SERVER', (string) env('TYPE_SERVER', '')),
            'nodeUrl' => getNodeRuntimePublicUrl(),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'portNode' => (string) getEnvValue('PORT_NODE', (string) env('PORT_NODE', '3100')),
            'localNodeUrl' => getNodeRuntimeInternalUrl(),
        ];
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function scan(Device $number)
    {

        return view('scan', [
            'number' => $number,
            'socketRuntime' => $this->buildSocketRuntime(),
        ]);
    }
    public function code(Device $number)
    {

        return view('connect-via-code', [
            'number' => $number,
            'socketRuntime' => $this->buildSocketRuntime(),
        ]);
    }
}
