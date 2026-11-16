# Meeting points and outcomes: the lists to choose from, the lists of the admin, and the refusals.

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


GET {{baseUrl}}/api/v1/meeting-points
HTTP 200
[Asserts]
jsonpath "$.meetingPoints" isList
jsonpath "$.meetingPoints[?@.archived != false]" not exists

GET {{baseUrl}}/api/v1/outcomes
HTTP 200
[Asserts]
jsonpath "$.outcomes" isList
jsonpath "$.outcomes[?@.archived != false]" not exists

GET {{baseUrl}}/api/v1/admin/meeting-points
HTTP 200
[Asserts]
jsonpath "$.meetingPoints" isList

GET {{baseUrl}}/api/v1/admin/outcomes
HTTP 200
[Captures]
outcomeName: jsonpath "$.outcomes[0].name"
[Asserts]
jsonpath "$.outcomes" isList

POST {{baseUrl}}/api/v1/admin/outcomes
{
  "name": "{{outcomeName}}",
  "kind": "remarks"
}
HTTP 409
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:outcome_name_taken"
jsonpath "$.existingId" isString

PATCH {{baseUrl}}/api/v1/admin/meeting-points/0193a3c0-0000-7000-8000-000000000001
{
  "latitude": 52.1
}
HTTP 422
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:invalid_coordinates"

POST {{baseUrl}}/api/v1/admin/meeting-points/0193a3c0-0000-7000-8000-000000000001/archive
HTTP 404
[Asserts]
header "Content-Type" contains "application/problem+json"

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204
