# versions/ — packaged plugin releases

Each subfolder is named only after a version (`1.0.0`, `1.0.1`, …) and contains a
WordPress-installable zip built from committed `HEAD`:

```
versions/1.0.0/sidrena-cijena.zip
versions/1.0.0/SHA256SUMS.txt
```

Install: upload the zip via *Plugins → Add New → Upload Plugin*, or unzip it
into `wp-content/plugins/` (the archive contains a top-level
`sidrena-cijena/` folder).

## Build

```bash
# 1. Bump the version: sidrena-cijena.php (header + SIDRENA_CIJENA_VERSION),
#    readme.txt (Stable tag + changelog), package.json.
# 2. Commit everything (the builder refuses a dirty tree).
# 3. Build:
tools/build-zip.sh 1.0.1
```

The builder aborts if the requested version does not match the plugin
header, and aborts if dev-only files (`tests/`, `specs/`, `tools/`,
`AGENTARD/`, …) leak into the zip.

## What gets uploaded to GitHub

Every version bump, including patch releases, gets a numeric version directory,
an installable ZIP and SHA256SUMS.txt committed and pushed to GitHub. Preserve
older packages; never replace an already shipped version with different code.
This policy supersedes the earlier local-only `0.0.x` rule in AGENTARD.
