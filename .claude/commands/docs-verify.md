# Docs Verify

Validate documentation edits without changing the repository structure.

## Use When

- Any Markdown file under `docs/` changed.
- The root `README.md`, `CONTRIBUTING.md`, or AI guidance docs changed.

## What To Check

1. Numbered documentation structure still matches `docs/00-architecture` through `docs/04-development`.
2. Relative Markdown links resolve to existing files.
3. AI guidance references point to real files instead of placeholders.

## Link Verification Command

Run this from the repository root:

```bash
rg -n --no-heading --glob '*.md' '\[[^]]+\]\((?!https?://|mailto:|#)([^)#]+)(?:#[^)]+)?\)' README.md CONTRIBUTING.md docs .claude .cursor antigravity jetbrains \
  | /usr/bin/perl -MFile::Basename=dirname -ne '
      if (/^([^:]+):\d+:.*\]\(([^)#]+)(?:#[^)]+)?\)/) {
          my ($file, $target) = ($1, $2);
          next if $target =~ m{^(https?://|mailto:|#)};
          my $path = $target =~ m{^/} ? $target : dirname($file) . "/" . $target;
          if (!-e $path) {
              print "$file -> $target\n";
              $bad = 1;
          }
      }
      END { exit($bad // 0); }
  '
```

## Expected Outcome

- The command prints nothing and exits successfully.
- If it reports missing targets, fix the links or the referenced files before completion.