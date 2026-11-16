# The audit log of the admin, as JSON and as CSV.

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


# The newest entries, among them this login.
GET {{baseUrl}}/api/v1/admin/audit
HTTP 200
[Asserts]
jsonpath "$.entries[?@.action == 'account.logged_in']" exists

GET {{baseUrl}}/api/v1/admin/audit?action=account.logged_in
Accept: text/csv
HTTP 200
[Asserts]
header "Content-Type" contains "text/csv"
body startsWith "occurredAt,action,actor,actorId,actorName,subjectType,subjectId,channel,changes\n"

GET {{baseUrl}}/api/v1/admin/audit
Accept: application/xml
HTTP 406
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:not_acceptable"

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204
