# Security Policy

## Supported Versions

GEOFlow is currently maintained on the `main` branch of this repository.

If you discover a security issue, please verify it against the latest code on `main` before reporting it.

## Reporting a Vulnerability

Please do **not** open a public GitHub issue for security vulnerabilities.

Instead, report the issue privately by contacting the maintainer through GitHub:

1. Open the repository owner profile: [@yaojingang](https://github.com/yaojingang)
2. Use the contact method available on the profile, or open a private security advisory if repository security advisories are enabled
3. Include the following information:
   - A short summary of the issue
   - Affected deployment mode or feature
   - Steps to reproduce
   - Expected impact
   - Any suggested mitigation

## Scope

Examples of issues that should be reported through the security channel:

- Authentication bypass
- Privilege escalation
- SQL injection or unsafe query execution
- XSS or unsafe output rendering
- Sensitive credential disclosure
- Unsafe file upload or path traversal
- API authorization bypass

## Response Expectations

The project maintainer will review credible reports and determine:

- Whether the issue is reproducible
- Whether the issue affects the current public version
- Whether a fix, mitigation, or disclosure note is required

Please avoid publishing exploit details before the maintainer has had a reasonable opportunity to investigate and patch the issue.
