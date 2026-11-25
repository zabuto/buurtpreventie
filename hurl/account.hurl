# Logging in with a code from the mail, the own account and API tokens. Needs mailpitUrl: the code is read from the
# newest mail in Mailpit. The only file that asks for a code, so a run uses one of the three codes an hour.

POST {{baseUrl}}/api/v1/auth/login-code
{
  "email": "{{email}}"
}
HTTP 202
[Captures]
challengeId: jsonpath "$.challengeId"
[Asserts]
jsonpath "$.challengeId" matches /^[0-9a-f]{32}$/

POST {{baseUrl}}/api/v1/auth/login-code/verify
{
  "challengeId": "{{challengeId}}",
  "code": "000 000"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:login_code_wrong"
jsonpath "$.attemptsLeft" == 4

GET {{mailpitUrl}}/api/v1/message/latest
HTTP 200
[Captures]
code: jsonpath "$.Text" regex /(\d{3} \d{3})/
[Asserts]
jsonpath "$.To[0].Address" == "{{email}}"

POST {{baseUrl}}/api/v1/auth/login-code/verify
{
  "challengeId": "{{challengeId}}",
  "code": "{{code}}",
  "rememberMe": true
}
HTTP 204

GET {{baseUrl}}/api/v1/me
HTTP 200
[Captures]
memberId: jsonpath "$.id"
[Asserts]
jsonpath "$.email" == "{{email}}"

PATCH {{baseUrl}}/api/v1/me
{}
HTTP 204

PATCH {{baseUrl}}/api/v1/me
{
  "phone": ""
}
HTTP 422
[Asserts]
jsonpath "$.violations" count == 1
jsonpath "$.violations[0].field" == "phone"

PUT {{baseUrl}}/api/v1/me/password
{
  "password": "te kort"
}
HTTP 422
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:invalid_input"

POST {{baseUrl}}/api/v1/me/tokens
{
  "name": "Hurl",
  "scopes": ["read"],
  "expiresAt": "{{inAWeek}}"
}
HTTP 201
[Captures]
tokenId: jsonpath "$.id"
apiToken: jsonpath "$.token"
[Asserts]
jsonpath "$.token" matches /^bpt_[0-9a-f]{64}$/

# The list names the new token but never its secret.
GET {{baseUrl}}/api/v1/me/tokens
HTTP 200
[Asserts]
jsonpath "$.tokens[?@.id == '{{tokenId}}']" exists
body not contains "{{apiToken}}"

# The member of this file is the admin, who sees the new token among every active token, without its secret.
GET {{baseUrl}}/api/v1/admin/api-tokens
HTTP 200
[Asserts]
jsonpath "$.tokens[?@.id == '{{tokenId}}'].memberId" includes "{{memberId}}"
body not contains "{{apiToken}}"

GET {{baseUrl}}/api/v1/me
Authorization: Bearer {{apiToken}}
HTTP 200

GET {{baseUrl}}/api/v1/me/tokens
Authorization: Bearer {{apiToken}}
HTTP 403
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:browser_only"

DELETE {{baseUrl}}/api/v1/me/tokens/{{tokenId}}
HTTP 204

GET {{baseUrl}}/api/v1/me
Authorization: Bearer {{apiToken}}
HTTP 401
[Asserts]
header "WWW-Authenticate" contains "Bearer"

# The admin revokes every token of a member at once; the second token stops working.
POST {{baseUrl}}/api/v1/me/tokens
{
  "name": "Hurl",
  "scopes": ["read"],
  "expiresAt": "{{inAWeek}}"
}
HTTP 201
[Captures]
secondToken: jsonpath "$.token"

POST {{baseUrl}}/api/v1/admin/members/{{memberId}}/api-tokens/revoke
HTTP 204

GET {{baseUrl}}/api/v1/me
Authorization: Bearer {{secondToken}}
HTTP 401

GET {{baseUrl}}/api/v1/me/activity
HTTP 200
[Asserts]
jsonpath "$.entries[?@.action == 'api_token.created']" exists
jsonpath "$.entries[?@.action == 'api_token.revoked']" exists

GET {{baseUrl}}/api/v1/me/export
HTTP 200
[Asserts]
header "Content-Disposition" contains "attachment"
jsonpath "$.profile.email" == "{{email}}"

POST {{baseUrl}}/api/v1/auth/confirm
{
  "password": "{{password}}"
}
HTTP 204

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204

POST {{baseUrl}}/api/v1/auth/login-code/verify
{
  "challengeId": "{{challengeId}}",
  "code": "{{code}}"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:login_code_ended"
