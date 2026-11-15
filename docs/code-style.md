# Code style

What the tools enforce, and the conventions a review checks. Texts inside code (comments, test names,
messages) follow [writing.md](writing.md); tests follow [testing.md](testing.md); the structure follows
[architecture.md](architecture.md).

## Enforced by tools

`composer check` fails on any deviation; `composer fix` repairs what can be repaired automatically.

| Tool | Enforces |
| --- | --- |
| `.editorconfig` | UTF-8, LF, final newline, 4 spaces (2 in `compose*.yaml`), lines up to 160 characters in PHP, and the PhpStorm formatter settings for PHP |
| Mago format (`mago.toml`) | The layout of every PHP file, matching the PhpStorm settings in `.editorconfig`, so a PhpStorm reformat and `mago format` agree |
| Mago lint | Strict types, PHPUnit and Symfony rules, `#[Test]` instead of a `test` prefix, `$this->assert…()`, yoda conditions, no shorthand ternary (`?:`), complexity limits. Warnings fail the check |
| Mago analyze | Type errors, unused code and parameters, missing type hints. Errors fail the check |
| Rector | PHP 8.4 syntax, Symfony and PHPUnit sets for the installed versions, dead code removal, type declarations |
| PHPStan | Level max, no deprecated API, PHPUnit and Symfony rules, the architecture rules (PHPat) |

## Symfony recipe files

`src/Kernel.php`, `public/index.php`, `config/preload.php`, `config/bundles.php` and
`tests/bootstrap.php` stay exactly as the Symfony recipes ship them, so `composer recipes:update` applies
cleanly, for instance on the move to Symfony 8.4 LTS. They are excluded from Mago, PHPStan and Rector,
and the conventions below do not apply to them. Change them only through a recipe update.
`config/reference.php` is generated and not in Git.

## Conventions

- **Final classes.** Every class is `final`; a class whose state never changes after construction is
  `final readonly`, such as everything in a use-case folder. Composition over inheritance: no
  abstract base classes for handlers or adapters. The recipe-owned `Kernel` is the exception.
- **Types everywhere.** Native parameter, return, property and constant types (`private const string`).
  A docblock only adds what PHP cannot express, such as `list<string>` or an array shape. `mixed`
  appears only at a boundary (JSON, a request, a console argument) and is narrowed right away.
- **Constructor promotion** for dependencies and value fields.
- **Dependency injection**, never fetching a service from the container. Configuration through
  attributes (`#[Route]`, `#[AsCommand]`, `#[Autowire]`), never annotations.
- **Named arguments for literal flags.** A bare `true`, `false` or `null` gets its parameter name, so
  the call says what it switches: `Selector::classname($pattern, regex: true)`,
  `mkdir($path, 0o777, recursive: true)`. Strings and numbers whose meaning is clear from the call stay
  positional.
- **Early return and early continue** over nested conditions: handle the exception first, keep the
  main path at the lowest indentation.
- **`match` over `switch`**, and over chains of `if` that map a value to a result.
- **`\assert()` states its invariant** as its second argument, for a fact the type system cannot carry,
  never for input validation.
- **`#[SensitiveParameter]`** on every parameter that carries a secret (password, API token, tick key),
  so it never shows up in a stack trace or log.
- **Small, single-purpose classes.** A method that needs a comment to separate its steps becomes
  several methods or classes.
