  <div class="tab-pane fade  " id="sendmedia" role="tabpanel">
      <h3>Send Media API</h3>
      <p>Method : <code class="text-success">POST</code></p>
      <p>Endpoint: <code>{{ env('APP_URL') }}/send-media</code></p>
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
                  <td>media_type</td>
                  <td>string</td>
                  <td>Yes</td>
                  <td>allow : image,video,audio,document </td>
              </tr>
              <tr>
                  <td>caption</td>
                  <td>string</td>
                  <td>No</td>
                  <td>caption/message</td>
              </tr>
              <tr>
                  <td>url</td>
                  <td>string</td>
                  <td>Yes</td>
                  <td>URL of media, must direct link</td>
              </tr>

          </tbody>
      </table>

      <p>Note: Make sure the url is direct link, not a link from google drive or other cloud storage</p>
      <br>
      <p>Example JSON Request </p>
      <pre class="bg-dark text-white">
        <code>
          {
            "api_key": "your-api-key",
            "sender": "6281234567890",
            "number": "6281234567891",
            "media_type": "image",
            "caption": "Hello World",
            "url": "https://example.com/image.jpg"
          }
        </code>

        
      </pre>
      <p>Use JSON body with `Content-Type: application/json`.</p>
  </div>
