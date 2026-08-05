# Cantaloupe delegate

`delegates.rb` is the authorisation hook for the IIIF image server. Copy it to the
Cantaloupe installation directory and point `delegate_script.pathname` at it.

## Why it matters

The image server reads files straight off disk. It does not know about AtoM's ACL,
so without this delegate every master under `uploads/r/` is downloadable through
the IIIF endpoint by anyone who can form the path — and those paths appear in every
manifest. The `/uploads/r/` nginx rules do not help: this is a different route to
the same bytes.

## Three things that are easy to get wrong

**It calls HTTPS, not HTTP.** Certbot installs a server-level
`if ($host = <site>) { return 301 https://... }` in the port-80 block. That runs
before location matching, so no location can exempt the auth path, and a plain HTTP
call is redirected and never returns 200.

**It forwards the originating `Host`.** One image server usually fronts several
AtoM instances. Without the header the check lands on whichever vhost answers by
default, which knows nothing about the file and answers "allow".

**The endpoint answers 200 even when refusing.** The verdict is in the body
(`{"allowed": false}`). This method treats any non-200 as a failed check and then
fails open — so returning 403 to refuse a request has the opposite effect.

Each of those failed silently and independently, and any one of them leaves the
check inert while appearing to be configured.

## Verifying it actually runs

    curl -sk -H "Host: <site>" \
      "https://127.0.0.1/iiif/auth/cantaloupe-check?identifier=<url-encoded-path>"

A master must answer `{"allowed": false}`. Then request the same master through
`/iiif/2/<identifier>/full/max/0/default.jpg` as an anonymous caller and confirm
403. Restart Cantaloupe first — the delegate caches verdicts.
