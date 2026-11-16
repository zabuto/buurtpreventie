# What anyone may read, without logging in.

GET {{baseUrl}}/api/v1/health
HTTP 200
[Asserts]
jsonpath "$.status" == "ok"

GET {{baseUrl}}/api/v1/docs.json
HTTP 200
[Asserts]
jsonpath "$.openapi" startsWith "3.1"

# A visitor gets contact and group with their keys and no values: both are for logged-in members only.
GET {{baseUrl}}/api/v1/config
HTTP 200
[Captures]
iconUrl: jsonpath "$.icons[1].url"
[Asserts]
jsonpath "$.associationName" isString
jsonpath "$.contact.*" count == 3
jsonpath "$.contact.name" == null
jsonpath "$.contact.email" == null
jsonpath "$.contact.phone" == null
jsonpath "$.group.*" count == 2
jsonpath "$.group.url" == null
jsonpath "$.group.service" == null
jsonpath "$.icons" count == 4
jsonpath "$.icons[0].size" == 512
jsonpath "$.icons[1].size" == 192
jsonpath "$.icons[2].size" == 180
jsonpath "$.icons[3].size" == 32

GET {{baseUrl}}{{iconUrl}}
HTTP 200
[Asserts]
header "Content-Type" == "image/png"
header "Cache-Control" contains "max-age=31536000"

GET {{baseUrl}}/api/v1/manifest.webmanifest
HTTP 200
[Asserts]
header "Content-Type" contains "application/manifest+json"
jsonpath "$.start_url" endsWith "/app/"
jsonpath "$.icons" count == 2
jsonpath "$.icons[0].sizes" == "192x192"
jsonpath "$.icons[1].sizes" == "512x512"
body not contains "/public/"
