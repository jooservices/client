# Upgrade to v4

v4 is a breaking rebuild. Use `ClientBuilder::create()->build()` and send PSR-7 requests through `sendRequest()`. Build convenience requests with `RequestBuilder`, and opt into HTTP-status exceptions through `Response::from($response)->throw()`.

Client verbs and Guzzle option bags are removed. Per-request portable options belong to `send($request, $options)`.
