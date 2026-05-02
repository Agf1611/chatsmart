<div class="tab-pane fade e" id="disconnectdevice" role="tabpanel">
    <h3>Disconnect device</h3>
    <p>Method : <code class="text-success">POST</code>
    <p>Endpoint: <code>{{ env('APP_URL') }}/logout-device</code></p>
    <p>Security note: send credentials in JSON body, not in query string.</p>

    <p>Request Body : (JSON If POST)
    <table class="table">
        <thead>
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Required</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>sender</td>
                <td>string</td>
                <td>Yes</td>
                <td>Device you want log out</td>

            </tr>
            <tr>
                <td>api_key</td>
                <td>string</td>
                <td>Yes</td>
                <td>API Key</td>
            </tr>

        </tbody>
    </table>
    <br>
    <pre class="bg-dark text-white">
      <code>
{
    "sender": "6281234567890",
    "api_key": "your-api-key"
}
      </code>
    </pre>
    <p>Normal Response</p>
    <pre class="bg-dark text-white">
      <code>
{
    "status": true,
    "message": "device disconnected "
}


      </code>
      </pre>



</div>
