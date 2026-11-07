# CircleCI credit controls

The baseline successful run (`e7082c2`, 7 November 2026) used a medium Docker job for verification (about 45 seconds) and a medium VM for integration/browser checks (about 9 minutes). The VM spent approximately 34 seconds installing a second PHP toolchain, 35 seconds installing dependencies, 149 seconds starting wp-env, and 261 seconds running Playwright. Package checks took another 34 seconds.

The pipeline now reduces repeated work while preserving the runtime checks:

- Verification runs on a small Docker executor. The VM depends on successful verification, so syntax/style/static-analysis failures cannot start it.
- Installed Composer and Node dependencies are cached, rather than only their downloaded archives. Composer refreshes its autoloader on every run. Node caches are isolated by executor, operating system, architecture, Node major version and native-module ABI.
- Node dependency keys ignore the plugin's release version/name but retain dependency and npm configuration changes. A version bump alone no longer forces another complete installation.
- The verified pure-PHP `vendor/` tree passes to the VM through a workspace. The VM no longer adds a PHP PPA, installs PHP or reinstalls Composer dependencies. WordPress/PHPUnit still run inside wp-env's PHP containers.
- Chromium caches contain the headless shell needed by this suite. A launch probe installs missing OS libraries only when required.
- E2E WP-CLI commands execute directly in the same running `tests-cli` container, with the same UID/GID and working directory that wp-env uses. Arguments remain separate executable arguments; a failed command is never silently retried. Local use retains the wp-env CLI.
- All 42 browser tests still run with one worker because their shared database cannot safely support parallel writers. Integration tests, Plugin Check and both standalone/WooCommerce activation checks remain in place.
- Documentation-only branches halt the integration job before installing dependencies. Runtime/tooling changes, unknown bases and main builds keep full coverage.
- Environment startup retries now fail the job after the final unsuccessful attempt.

Auto-cancel redundant workflows has also been enabled in the CircleCI project settings. New commits cancel obsolete feature-branch workflows; CircleCI excludes the default branch and certain reruns/schedules from auto-cancellation. See [CircleCI auto-cancel documentation](https://circleci.com/docs/guides/orchestrate/skip-build/).

CircleCI-hosted VMs remain disposable. This configuration deliberately does not cache databases, wp-env configuration state or `/var/lib/docker`; every run starts with fresh WordPress data. Paid Docker layer caching is disabled: its [200-credit per-job fee](https://circleci.com/docs/guides/optimize/docker-layer-caching/) would exceed the baseline VM's approximate compute cost at the documented medium rate. Dependency caches are retained for seven days.

To keep iteration costs down, batch edits and lightweight local checks before pushing. Push a coherent revision for remote heavy verification, inspect its first failure, and avoid pushing a series of formatting-only commits that each start a workflow. Do not run PHPCS, PHPStan, Docker or browser suites on the host PC.

Cache changes must be measured in the CircleCI step timings. The first run populates the new immutable caches and may spend extra time uploading them; later runs skip dependency installation on cache hits. Do not report projected warm-run savings as measured results.
