# Rounds as the admin, who reads them but walks none. Needs inAWeek: the end of the period, a week ahead.

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


# The moment of the server starts the period; a + in a query string reads as a space, so it ends in Z.
GET {{baseUrl}}/api/v1/health
HTTP 200
[Captures]
now: header "Date" toDate "%a, %d %b %Y %H:%M:%S GMT" dateFormat "%Y-%m-%dT%H:%M:%SZ"

GET {{baseUrl}}/api/v1/rounds?from={{now}}&to={{inAWeek}}
HTTP 200
[Captures]
roundId: jsonpath "$.rounds[?@.walkerCount > 0].id" nth 0
[Asserts]
jsonpath "$.rounds" isList
jsonpath "$.rounds[?!@.status]" not exists
jsonpath "$.rounds[?!@.walkerCount]" not exists
jsonpath "$.rounds[?!@.minimumWalkers]" not exists
jsonpath "$.rounds[?!@.maximumWalkers]" not exists
jsonpath "$.rounds[?!@.openSpots]" not exists
jsonpath "$.rounds[?!@.daypart]" not exists

# A round with walkers: each walker has these four keys, and the first exactly these.
GET {{baseUrl}}/api/v1/rounds/{{roundId}}
HTTP 200
[Asserts]
jsonpath "$.walkers" isList
jsonpath "$.walkers" not isEmpty
jsonpath "$.status" exists
jsonpath "$.walkerCount" exists
jsonpath "$.minimumWalkers" exists
jsonpath "$.maximumWalkers" exists
jsonpath "$.openSpots" exists
jsonpath "$.daypart" exists
jsonpath "$.walkers[?!@.id]" not exists
jsonpath "$.walkers[?!@.displayName]" not exists
jsonpath "$.walkers[?!@.name]" not exists
jsonpath "$.walkers[?!@.phone]" not exists
jsonpath "$.walkers[0].*" count == 4

GET {{baseUrl}}/api/v1/staffing-gaps
HTTP 200
[Asserts]
jsonpath "$.gaps" isList

# Only walked rounds without a report.
GET {{baseUrl}}/api/v1/me/rounds?view=to_report
HTTP 200
[Asserts]
jsonpath "$.rounds" isList
jsonpath "$.rounds[?@.status != 'walked']" not exists
jsonpath "$.rounds[?@.reported != false]" not exists

GET {{baseUrl}}/api/v1/me/rounds?view=all
HTTP 422
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:invalid_period"

GET {{baseUrl}}/api/v1/rounds?from=2026-01-01T00:00:00Z&to=2026-06-01T00:00:00Z
HTTP 422
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:invalid_period"

# The admin walks no rounds: signing up is refused before the round is looked up.
PUT {{baseUrl}}/api/v1/rounds/0193a3c0-0000-7000-8000-000000000001/walkers/me
HTTP 403
[Asserts]
jsonpath "$.type" == "urn:buurtpreventie:problem:access_denied"

# Only analysts and coordinators list the reports.
GET {{baseUrl}}/api/v1/reports?from=2026-11-01T00:00:00Z&to=2026-12-01T00:00:00Z
HTTP 403
[Asserts]
header "Content-Type" contains "application/problem+json"

# Only a coordinator reads the history of a round.
GET {{baseUrl}}/api/v1/rounds/0193a3c0-0000-7000-8000-000000000001/history
HTTP 403
[Asserts]
header "Content-Type" contains "application/problem+json"

POST {{baseUrl}}/api/v1/rounds/0193a3c0-0000-7000-8000-000000000001/comments
{
  "text": "Mooie avond."
}
HTTP 404
[Asserts]
header "Content-Type" contains "application/problem+json"

DELETE {{baseUrl}}/api/v1/reports/0193a3c0-0000-7000-8000-000000000001
HTTP 404
[Asserts]
header "Content-Type" contains "application/problem+json"

POST {{baseUrl}}/api/v1/auth/logout
HTTP 204
