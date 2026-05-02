<div class="tab-pane fade show active" id="sendmessage" role="tabpanel">
    <h3>Send Message API</h3>
    <p>Method : <code class="text-success">POST</code></p>
    <p>Endpoint: <code>{{ env('APP_URL') }}/send-message</code></p>
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
                <td>api_key</td>
                <td>string</td>
                <td>Yes</td>
                <td>API Key</td>
            </tr>
            <tr>
                <td>sender</td>
                <td>string</td>
                <td>Yes</td>
                <td>Number of your device</td>
            </tr>
            <tr>
                <td>number</td>
                <td>string</td>
                <td>Yes</td>
                <td>recipient number ex 72888xxxx|62888xxxx</td>
            </tr>
            <tr>
                <td>message</td>
                <td>string</td>
                <td>Yes</td>
                <td>Messsage to be sent</td>
            </tr>
        </tbody>
    </table>
    <br>
    <p>Examplo JSON Request</p>
    <pre class="bg-dark text-white">
      <code>
        {
          "api_key": "your-api-key",
          "sender": "6281234567890",
          "number": "6281234567891",
          "message": "Hello World"
        }
      </code>
      </pre>
    <p>Use JSON body with `Content-Type: application/json`.</p>


</div>
