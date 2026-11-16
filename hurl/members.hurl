# Members as a member and as the admin.

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


# Every member has these five keys and no e-mail address, and the first exactly these five.
GET {{baseUrl}}/api/v1/members
HTTP 200
[Asserts]
jsonpath "$.members" isList
jsonpath "$.members" not isEmpty
jsonpath "$.members[?!@.id]" not exists
jsonpath "$.members[?!@.displayName]" not exists
jsonpath "$.members[?!@.name]" not exists
jsonpath "$.members[?!@.phone]" not exists
jsonpath "$.members[?!@.address]" not exists
jsonpath "$.members[?@.email]" not exists
jsonpath "$.members[0].*" count == 5

GET {{baseUrl}}/api/v1/admin/members
HTTP 200
[Captures]
memberId: jsonpath "$.members[0].id"
[Asserts]
jsonpath "$.members[0].email" exists

GET {{baseUrl}}/api/v1/admin/members/{{memberId}}
HTTP 200
[Asserts]
jsonpath "$.id" == "{{memberId}}"

PATCH {{baseUrl}}/api/v1/admin/members/{{memberId}}
{
  "name": null
}
HTTP 422
[Asserts]
jsonpath "$.violations" count == 1
jsonpath "$.violations[0].field" == "name"

POST {{baseUrl}}/api/v1/admin/members
{
  "email": "geen adres",
  "name": "Voorbeeld Lid",
  "displayName": "Voorbeeld L."
}
HTTP 422
[Asserts]
header "Content-Type" contains "application/problem+json"

PUT {{baseUrl}}/api/v1/admin/members/0193a3c0-0000-7000-8000-000000000001/roles
{
  "roles": ["walker"]
}
HTTP 404
[Asserts]
header "Content-Type" contains "application/problem+json"

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204
