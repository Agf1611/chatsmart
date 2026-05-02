<div class="tab-pane fade " id="examplewebhook" role="tabpanel">
    <h3>Webhook Example</h3>
    <p>Webhook is a feature that allows you to receive a callback from our server when a message is incoming to your
        device.
        You can use this feature for made a dinamic chatbot or whatever you want. </p>
    <p>We will send a POST request to your webhook url with a JSON body. Here is an example of the JSON body we will
        send:</p>
    <pre class="bg-dark text-white">
        <code class="json">
{
  "message" : "message",
  "from" : "the number of the whatsapp sender",
  "bufferImage" : "base64 image, null if message not contain image",
}

        </code>
      </pre>
      <br>
      <p>Build your webhook endpoint so it accepts a JSON `POST` request and validates any shared secret or token you require on your side.</p>
      



</div>
