# The settings of the association: reading, an empty change, and the refusals of a weak colour and a file that is no
# image. Nothing here changes a setting.

POST {{baseUrl}}/api/v1/auth/login
{
  "email": "{{email}}",
  "password": "{{password}}",
  "rememberMe": false
}
HTTP 204

POST {{baseUrl}}/api/v1/auth/confirm
{
  "password": "{{password}}"
}
HTTP 204


GET {{baseUrl}}/api/v1/admin/settings
HTTP 200
[Asserts]
jsonpath "$.associationName" exists
jsonpath "$.primaryColor" exists
jsonpath "$.minimumWalkers" exists
jsonpath "$.apiAccess" exists
jsonpath "$.defaults" exists
jsonpath "$.welcomeTextNl" isString
jsonpath "$.welcomeTextNl" not isEmpty

PATCH {{baseUrl}}/api/v1/admin/settings
{}
HTTP 204

PATCH {{baseUrl}}/api/v1/admin/settings
{
  "primaryColor": "#777777"
}
HTTP 422
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:insufficient_contrast"
jsonpath "$.setting" == "brand.primary"
jsonpath "$.ratio" == 4.47
jsonpath "$.required" == 4.5

PUT {{baseUrl}}/api/v1/admin/settings/images/logo
Content-Type: image/png
`Buurtpreventie`
HTTP 422
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:unsupported_image"
jsonpath "$.condition" == "format"

# A version of an image that no setting names is not found.
GET {{baseUrl}}/api/v1/media/brand/logo-512-0000000000000000.png
HTTP 404
[Asserts]
header "Content-Type" contains "application/problem+json"

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204
