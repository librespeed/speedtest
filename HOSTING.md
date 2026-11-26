# Public LibreSpeed host requirements

This document describes what it takes to operate a public LibreSpeed test
point, request inclusion in the public server catalog, and remain in good
standing. It is intended for server sponsors and operators, not end users
learning how to install a LibreSpeed client.

This is a proposed host policy. LibreSpeed's public submission form and
maintainer review are currently the source of the public-list onboarding
process; the complete rules below are not claimed to be enforced today.

## Scope

LibreSpeed has two different kinds of deployment:

- a private or personal server for an individual or organization;
- a community test point that operators want included in LibreSpeed's public
  server catalog.

This article applies to the second case.

For private installation instructions, use:

- [README.md](README.md)
- [doc.md](doc.md)
- [doc_docker.md](doc_docker.md)

This file does not replace those installation guides.

## Public server submission

The public submission form asks for:

- a user-facing server name;
- the public server domain;
- download, upload, ping/jitter, and get-IP endpoint paths;
- the backend implementation;
- private operator name and email;
- public sponsor name;
- optional sponsor URL;
- confirmation of at least 1 Gbps bandwidth;
- confirmation that traffic is not throttled or restricted;
- confirmation that the host can handle a relatively high amount of traffic;
- acceptance that the endpoint will be public and exposed to abuse.

The form is:

- <https://librespeed.org/submit/>

An operator may also contact the maintainer directly at
`info@fdossena.com` for sponsorship or catalog changes.

Public listing is a manual process. Submission does not guarantee inclusion.
The public catalog is maintained outside the core application repository.

## Minimum host eligibility

A public candidate should demonstrate the following before submission:

### Network

- at least 1 Gbps of useful public bandwidth;
- no per-IP, burst-only, or application-level limits that prevent sustained
  speed tests;
- enough upload and download capacity for multiple simultaneous clients;
- stable DNS A records and, where supported, AAAA records;
- accurate city and country data;
- predictable routing and peering;
- capacity for public traffic beyond a short benchmark.

A high nominal circuit or provider speed label is not enough. The host must
sustain representative public tests and remain stable under concurrent use.

### Addressing

- public IPv4 reachability;
- IPv6 reachability when it is practical and expected in the host region;
- correct client-IP forwarding through proxies or CDNs;
- no private, reserved, or incorrect IP exposed as the client address.

### Location

- the submitted city and country should match the server's actual network
  location;
- distance and sponsor metadata should be accurate;
- an operator should report material location or datacenter changes.

### Identity and sponsor

- a recognizable sponsor or organization;
- a publicly accessible sponsor website;
- valid public contact information;
- a monitored private operator or security contact;
- no misleading sponsor relationship or impersonation.

## Required test endpoints

A public backend must provide the four LibreSpeed test functions:

| Function | Purpose |
|---|---|
| Download | Deliver random, incompressible data |
| Upload | Receive POSTed upload data |
| Ping/jitter | Return promptly for repeated HTTP timing |
| getIP | Return the client IP and optional ISP/location data |

The default LibreSpeed PHP implementation uses:

- `garbage.php`
- `empty.php`
- `empty.php` for ping/jitter
- `getIP.php`

Other implementations may use different paths if they provide the same behavior.

The public catalog entry should accurately map:

- `server`
- `dlURL`
- `ulURL`
- `pingURL`
- `getIpURL`

## Download endpoint

The download endpoint must:

- return random or incompressible data;
- not cache results;
- not be compressed;
- support repeated HTTP GET requests;
- handle multiple concurrent streams;
- permit the backend's `ckSize` parameter or equivalent.

The host should not proxy `/garbage.php` through a cache or CDN mode that
changes performance or changes response bytes.

## Upload endpoint

The upload endpoint must accept at least 20 MB per POST. The operator should
allow additional overhead, commonly 21–32 MB or more, through every layer:

- nginx `client_max_body_size`;
- PHP `post_max_size`;
- PHP-FPM/FastCGI request limits;
- Apache `LimitRequestBody`;
- IIS `maxAllowedContentLength`;
- reverse proxy and load balancer limits;
- CDN request-body limits.

The endpoint must not silently discard requests, return false success, or
throttle normal upload tests.

## Ping/jitter endpoint

The ping/jitter endpoint must:

- return a small, predictable response;
- not perform a download or upload;
- avoid redirects;
- avoid authentication;
- avoid caching delays;
- use HTTP or a compatible endpoint consistently.

The host should measure HTTP timing with a real LibreSpeed client rather than
only an ICMP ping.

## get-IP endpoint

The get-IP endpoint must:

- identify the real public client address;
- work behind trusted proxies and load balancers;
- preserve IPv4 and IPv6 behavior;
- not expose unnecessary proxy headers;
- not leak secrets or internal IP addresses;
- validate hostile or malformed forwarding inputs.

If ISP, location, or distance data is enabled:

- use accurate and appropriately licensed data sources;
- validate coordinates;
- preserve country/code fallbacks;
- disable the feature if location data cannot be served safely;
- return approximate location only.

## TLS, DNS, and protocol behavior

Public hosts should:

- use a valid publicly trusted TLS certificate;
- keep the certificate and server clock valid;
- serve DNS consistently for the public hostname;
- use HTTPS for public test endpoints;
- avoid HTTP/HTTPS mismatch;
- not require browser plugins or WebSocket support;
- serve CORS headers where cross-origin testing requires them;
- avoid redirects that alter timing or test behavior.

DNS-only routing is preferred over an HTTP-cache/CDN configuration that
interferes with random download and upload traffic.

## Software and runtime

Apache, nginx, and IIS are supported web-server choices.

For PHP hosts:

- use a currently supported PHP release;
- PHP 8 or newer is recommended;
- enable OpenSSL/PHP OpenSSL;
- keep PHP modules and dependencies updated;
- limit filesystem write access;
- keep database credentials outside public paths;
- keep telemetry and stats endpoints protected.

Go, Rust, and custom backends may be public hosts if they implement the same
endpoint contract reliably.

Partial or experimental implementations should not be listed until they pass
the public compatibility check.

## Security requirements

Public hosts must:

- patch the operating system, web server, runtime, and dependencies;
- run test endpoints with least-privilege permissions;
- restrict access to administration, secrets, database, and telemetry paths;
- publish a security contact or vulnerability reporting method;
- publish and apply TLS, DNS, and certificate controls;
- validate proxy forwarding headers;
- prevent directory listing and source/config exposure;
- keep WAF and rate limits from blocking legitimate large tests;
- set timeouts and concurrency limits appropriate to the host;
- monitor disk, CPU, memory, NIC, and log growth;
- protect telemetry and result-sharing endpoints with a privacy policy and
  appropriate authentication.

Public test endpoints are intentionally exposed to large downloads, large
uploads, repeated ping requests, automated traffic, bots, and abuse attempts.

## Abuse and resource protection

Operators should support limits for:

- concurrent tests;
- aggregate outbound bandwidth;
- per-source and global request rates;
- slow or incomplete requests;
- upload body size;
- upload duration;
- CPU and memory consumption;
- disk and log growth.

Controls must not silently lower public test results below the advertised
host role. If a host cannot sustain public traffic, it should not be added or
should be suspended.

## Privacy and telemetry

Operators enabling telemetry or result sharing should:

- publish a privacy policy;
- provide a GDPR/data-deletion contact where applicable;
- restrict or redact IP addresses;
- protect test-ID obfuscation;
- secure the stats page;
- restrict database access;
- define data retention;
- avoid exposing personal fields through public result URLs.

The public catalog should not receive private operator email addresses,
credentials, database details, or internal management URLs.

## Hardware and load planning

The public 1-Gbps statement means the host should have sufficient sustained
public network and server capacity, not simply a large provider plan.

Before submission, verify:

- sustained downstream and upstream throughput;
- NIC queue and interrupt behavior;
- CPU and memory during concurrent tests;
- TCP buffer and proxy behavior;
- disk-free space;
- no provider-level traffic shaping;
- no application or web-server concurrency limit below public demand;
- no packet loss or unstable latency under load.

The host must remain stable under several simultaneous test clients. The
server should be large enough to avoid using the public service as a
capacity-discovery experiment.

## Pre-submission self-check

Before requesting public listing:

1. Verify DNS and HTTPS.
2. Verify `/garbage.php` is random and incompressible.
3. Verify `/empty.php` accepts 20 MB or more of POST data.
4. Verify `/getIP.php` identifies the real client IP.
5. Verify ping/jitter latency on the real public network.
6. Verify CORS for cross-origin tests.
7. Verify no CDN/cache/compression changes transfer bytes or timing.
8. Run several consecutive full download/upload tests.
9. Run several concurrent full tests.
10. Test from at least two independent network regions.
11. Verify upload limits at the public hostname, not only localhost.
12. Verify logs, CPU, memory, disk, and bandwidth remain healthy.
13. Verify that secrets and internal configuration are not public.

Useful first checks:

```sh
curl -I https://speed.example.test/backend/garbage.php
curl -I https://speed.example.test/backend/empty.php
curl -i "https://speed.example.test/backend/getIP.php?isp=true"
openssl s_client -connect speed.example.test:443 -servername speed.example.test
```

Use a real LibreSpeed-compatible client for final throughput validation.

## Application review

Public-list review should verify:

- endpoint reachability;
- DNS and TLS;
- server name, city, country, and sponsor accuracy;
- download behavior;
- upload behavior;
- ping/jitter behavior;
- get-IP behavior;
- backend protocol compatibility;
- concurrent-load stability;
- public bandwidth and traffic shaping;
- security and abuse posture;
- operator and sponsor contact validity.

Reviewers should record:

- review date;
- test origins;
- download median/P10/P90;
- upload median/P10/P90;
- latency median/P10/P90;
- jitter median/P10/P90;
- HTTP/DNS/TLS failures;
- concurrent-load observations;
- requested changes;
- final disposition.

## Approval status

Suggested status values:

- `submitted`
- `technical-review`
- `changes-requested`
- `trial`
- `active`
- `maintenance`
- `degraded`
- `suspended`
- `retired`

An entry should become `active` only after:

- all required endpoints pass;
- HTTPS and DNS checks pass;
- at least two independent test origins pass;
- declared public bandwidth is supportable;
- severe throttling or shaping is ruled out;
- public security and sponsor contacts are valid;
- the operator accepts abuse and maintenance responsibility.

An active status is not a lifetime capacity guarantee.

## Ongoing operations

Active hosts should:

- monitor DNS and TLS;
- monitor endpoint availability;
- monitor CPU, memory, NIC, disk, logs, and concurrency;
- update LibreSpeed and runtime dependencies;
- maintain accurate public catalog metadata;
- report planned maintenance;
- remove stale endpoints promptly;
- respond to abuse and security reports;
- preserve the public-list contact address;
- rerun health tests after network or software changes.

Suggested monitoring cadence:

- DNS/TLS/endpoint health: every 5–15 minutes;
- basic availability probe: hourly;
- throughput validation: weekly;
- full regional retest: monthly;
- manual review after material network changes;
- incident and security review when needed.

## Suspension and removal

A host may be suspended or removed for:

- persistent DNS, TLS, or endpoint failure;
- incompatible or missing endpoints;
- severe repeated underperformance;
- rejecting or misconfiguring upload requests;
- traffic shaping that conflicts with the public-host statement;
- stale sponsor or operator contacts;
- misleading public metadata;
- abuse or malicious behavior;
- certificate expiration;
- compromise or resource-exhaustion incidents;
- operator request.

Reinstatement should require:

- root-cause remediation;
- a new or updated submission;
- repeated automated health checks;
- a complete manual retest;
- documented security approval for compromise or incident-related removal.

## Public catalog metadata

A public server entry should expose only public-facing metadata:

```json
{
  "id": 1,
  "name": "City, Country",
  "server": "https://speed.example.test/",
  "dlURL": "backend/garbage.php",
  "ulURL": "backend/empty.php",
  "pingURL": "backend/empty.php",
  "getIpURL": "backend/getIP.php",
  "sponsorName": "Sponsor",
  "sponsorURL": "https://sponsor.example/",
  "status": "active"
}
```

Private operator email, credentials, database details, and management URLs
must not appear in public catalog JSON.

Status and health fields are proposed additions. Existing clients should ignore
unknown fields.

## Out of scope

This document does not describe how to install LibreSpeed for a private
user, configure a browser UI, build a custom frontend, or choose a database.
Those topics belong in the [installation documentation](doc.md)
and [Docker documentation](doc_docker.md).

This document does not describe a complete LibreSpeed client deployment;
it defines what a public host must provide and how public listing may be
reviewed and maintained.
