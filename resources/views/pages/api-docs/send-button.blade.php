 <div class="tab-pane fade  " id="sendbutton" role="tabpanel">
     <h3>Send Button API</h3>
     <p>Method : <code class="text-success">POST</code></p>
     <p>Endpoint: <code>{{ env('APP_URL') }}/send-button</code></p>
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
                 <td>Text of message</td>
             </tr>
             <tr>
                 <td>button</td>
                 <td>array</td>
                 <td>Yes</td>
                 <td>Button array 5</td>
             </tr>
             <tr>
                 <td>footer</td>
                 <td>string</td>
                 <td>No</td>
                 <td>The footer text of message</td>
             </tr>
             <tr>
                 <td>url</td>
                 <td>string</td>
                 <td>No</td>
                 <td>Image or video url</td>
             </tr>

         </tbody>
     </table>
     <p>Example json</p>
 <pre class="bg-dark text-white">
                            <code class="json">
 {
     "sender" : "6281234567890",
     "api_key" : "your-api-key",
     "number" : "6281234567891",
     "url" : null,
     "footer" : "optional",
     "message" : "Halo,ini pesan button",
     "button" : ["button 1","button 2","button 3"]

 }
                            </code>
                        </pre>
     <p>Use JSON body with `Content-Type: application/json`.</p>


 </div>
