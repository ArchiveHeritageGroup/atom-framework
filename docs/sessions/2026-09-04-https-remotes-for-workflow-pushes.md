# HTTPS remotes, so bin/release can push workflow files

**Date:** 4 September 2026
**Releases:** framework v2.18.37, v2.18.38; plugins v3.106.87

## Why the remotes changed

Adding a CI step to `.github/workflows/ci-cd.yml` was rejected on push:

    ! [remote rejected] main -> main
      refusing to allow an OAuth App to create or update workflow
      `.github/workflows/ci-cd.yml` without `workflow` scope

Both repositories pushed over SSH with a per-repository key, set in local config as
`core.sshcommand = ssh -i ~/.ssh/id_ed25519_framework -o IdentitiesOnly=yes` and the
equivalent for plugins. The commit and tag were created locally; only the push was
refused, which is worth knowing because the failure looks like a release that did
not happen when in fact it half did.

Curiously the same change to `atom-ahg-plugins` pushed over its own SSH key without
complaint. So the refusal is not simply "deploy keys cannot write workflow files" -
the two keys or repositories differ in some way not visible from this host. The
first explanation offered for this was wrong and is corrected here rather than left
standing.

## What changed

Both repositories now use HTTPS with the GitHub CLI as a per-repository credential
helper:

    git config --local remote.origin.url https://github.com/ArchiveHeritageGroup/<repo>.git
    git config --local credential.helper '!gh auth git-credential'

Per-repository deliberately. `gh auth setup-git` would have rewritten the global
gitconfig and affected every repository on the host.

The `core.sshcommand` entries are left in place. They are inert while the URL is
HTTPS, and keeping them makes reverting a one-line change. Prior configuration is
recorded in the session scratchpad.

## Consequences worth knowing

Pushes now authenticate as the user's GitHub account rather than a repository-scoped
deploy key. That is what allows workflow files through, and it is a wider credential
than before; anything downstream that distinguishes deploy-key pushes from user
pushes will see the difference.

`bin/release` also has a step at line 134 that only runs when `gh auth status`
succeeds. It previously did not, so releases pushed tags without creating GitHub
Releases. It does now, so Releases will start appearing.

## Verification

`git push --dry-run` authenticates against GitHub without transferring anything, and
both repositories report clean for `main` and for the current tag. That proves the
credential path, not the write authorisation - only a real push of a workflow file
proves that, and one succeeded on this repository earlier the same day by this
route.
