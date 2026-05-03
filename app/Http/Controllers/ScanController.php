<?php

namespace App\Http\Controllers;

use App\Models\Device;

class ScanController extends Controller
{
    private function buildSocketRuntime(): array
    {
        return [
            'serverType' => getEnvValue('TYPE_SERVER', (string) env('TYPE_SERVER', '')),
            'nodeUrl' => rtrim((string) getEnvValue('WA_URL_SERVER', (string) env('WA_URL_SERVER', '')), '/'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'portNode' => (string) getEnvValue('PORT_NODE', (string) env('PORT_NODE', '3100')),
            'localNodeUrl' => 'http://127.0.0.1:' . getEnvValue('PORT_NODE', (string) env('PORT_NODE', '3100')),
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
