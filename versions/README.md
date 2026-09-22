# versions/ — packaged plugin releases

Each subfolder is named after a version (`0.1.0`, `0.1.1`, …) and contains a
WordPress-installable zip built from committed `HEAD`:

```
versions/0.1.0/sidrena-cijena.zip
versions/0.1.0/SHA256SUMS.txt
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
tools/build-zip.sh 0.1.1
```

The builder aborts if the requested version does not match the plugin
header, and aborts if dev-only files (`tests/`, `specs/`, `tools/`,
`AGENTARD/`, …) leak into the zip.

## What gets uploaded to GitHub

| Version | Example | Destination |
|---------|---------|-------------|
| Patch `0.0.x` | `0.0.1` | **Local only** — gitignored, never pushed (keeps GitHub storage lean) |
| Minor `0.x.0` | `0.1.0` | Committed and pushed |
| Major `x.0.0` | `1.0.0` | Committed and pushed |

So: bump the patch number for everyday fixes (stays on your machine), bump
minor/major when the result should be shared.
