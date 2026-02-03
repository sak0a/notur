# Extension Registry

Browse community-built extensions for Notur. Extensions add functionality to your Pterodactyl Panel without forking.

## Available Extensions

### notur/hello-world

**Hello World** — Minimal example extension demonstrating Notur's slot registration, API routes, and frontend components.

- **Version:** 1.0.0
- **Author:** Notur Team
- **License:** MIT
- **Tags:** `example`, `starter`
- **Compatibility:** Notur >=1.0.0, Pterodactyl 1.11.*
- **Repository:** [GitHub](https://github.com/sak0a/hello-world)

#### Install

```bash
php artisan notur:install notur/hello-world
```

---

## Submit Your Extension

Want to list your extension in the registry? Follow these steps:

1. Create your extension following the [Extension Development Guide](/extensions/frontend-sdk)
2. Package it with `php artisan notur:export your-extension`
3. Open a pull request to the [Notur Registry](https://github.com/sak0a/registry) adding your extension metadata to `registry.json`

### Requirements

- Valid `extension.yaml` manifest conforming to the [manifest schema](https://github.com/sak0a/notur/blob/master/registry/schema/extension-manifest.schema.json)
- Public source repository (GitHub)
- Semantic versioning
- MIT or compatible license recommended
- No malicious code or data exfiltration
