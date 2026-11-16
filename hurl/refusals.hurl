# The login endpoints refuse without a session; each answer is a problem with its own type.

POST {{baseUrl}}/api/v1/auth/login
{
  "email": "{{email}}",
  "password": "not the password"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:login_failed"

POST {{baseUrl}}/api/v1/auth/confirm
{
  "password": "not the password"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:unauthorized"

POST {{baseUrl}}/api/v1/auth/invitation
{
  "secret": "bpi_0000000000000000000000000000000000000000000000000000000000000000"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:invitation_ended"

POST {{baseUrl}}/api/v1/auth/login-code/verify
{
  "challengeId": "00000000000000000000000000000000",
  "code": "000000"
}
HTTP 401
[Asserts]
header "Content-Type" contains "application/problem+json"
jsonpath "$.type" == "urn:buurtpreventie:problem:login_code_ended"
